<?php

/**
 * Carrier Rule Model
 *
 * Persistence layout:
 *   - xfe_carrier_carrier_rule          (this model)
 *   - xfe_carrier_rule_condition_group  (nested groups; tree)
 *   - xfe_carrier_rule_condition        (leaf conditions inside a group)
 *
 * Save flow:
 *   - _afterSave() reads `$this->getGroupsData()` (JSON or array)
 *     and writes the condition tree, replacing whatever was there.
 *   - The form's `groups_data` hidden field is mapped onto
 *     `groupsData` by the controller before saveRuleAction saves the
 *     model.
 *
 * Load flow:
 *   - The resource model walks the condition tree and sets
 *     `conditionsData` (array) on the model.
 *   - The Edit form serialises `conditionsData` into the
 *     `groups_data_hidden` hidden field so the JS condition builder
 *     can rehydrate.
 */
class XFE_Carrier_Model_Carrier_Rule extends Mage_Core_Model_Abstract
{
    protected $_eventPrefix = 'xfe_carrier_rule';
    protected $_eventObject = 'rule';

    protected function _construct()
    {
        $this->_init('xfe_carrier/carrier_rule');
    }

    /**
     * Load conditions after saving rule.
     *
     * Reads `groups_data` (set by the controller from the form's
     * `groups_data` field) and persists the nested condition tree.
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _afterSave()
    {
        parent::_afterSave();

        if (!$this->getId()) {
            return $this;
        }

        $resource   = Mage::getSingleton('core/resource');
        $write      = $resource->getConnection('core_write');
        $condTable  = $resource->getTableName('xfe_carrier/rule_condition');
        $groupTable = $resource->getTableName('xfe_carrier/rule_condition_group');

        // Delete old conditions belonging to this rule's groups
        $write->delete($condTable, array(
            'group_id IN (?)' => $write->select()
                ->from($groupTable, 'group_id')
                ->where('rule_id = ?', $this->getId())
        ));

        // Delete all groups
        $write->delete($groupTable, array(
            'rule_id = ?' => $this->getId()
        ));

        // Save new conditions
        $groupsData = $this->getGroupsData();
        if (is_string($groupsData)) {
            $groupsData = Mage::helper('core')->jsonDecode($groupsData);
        }

        if (!is_array($groupsData) || empty($groupsData)) {
            return $this;
        }

        $sortOrder = 0;
        foreach ($groupsData as $groupData) {
            $this->_saveConditionGroup($groupData, $this->getId(), null, $sortOrder++);
        }

        return $this;
    }

    /**
     * Recursively save a condition group
     *
     * @param array $groupData
     * @param int $ruleId
     * @param int|null $parentGroupId
     * @param int $sortOrder
     * @return int Saved group ID
     */
    protected function _saveConditionGroup(array $groupData, $ruleId, $parentGroupId = null, $sortOrder = 0)
    {
        $resource   = Mage::getSingleton('core/resource');
        $write      = $resource->getConnection('core_write');
        $groupTable = $resource->getTableName('xfe_carrier/rule_condition_group');
        $condTable  = $resource->getTableName('xfe_carrier/rule_condition');

        $groupDataRow = array(
            'rule_id'    => $ruleId,
            'sort_order' => $sortOrder,
            'aggregator' => isset($groupData['aggregator']) ? $groupData['aggregator'] : 'all',
        );
        if ($parentGroupId !== null) {
            $groupDataRow['parent_group_id'] = $parentGroupId;
        }
        $write->insert($groupTable, $groupDataRow);
        $groupId = $write->lastInsertId($groupTable);

        if (isset($groupData['conditions']) && is_array($groupData['conditions'])) {
            $condSortOrder = 0;
            $subGroupSortOrder = 0;

            foreach ($groupData['conditions'] as $item) {
                if (isset($item['type']) && $item['type'] === 'group') {
                    $this->_saveConditionGroup($item, $ruleId, $groupId, $subGroupSortOrder++);
                } else {
                    $condRow = array(
                        'group_id'   => $groupId,
                        'sort_order' => $condSortOrder++,
                        'attribute'  => isset($item['attribute']) ? $item['attribute'] : '',
                        'operator'   => isset($item['operator']) ? $item['operator'] : '==',
                        'value'      => isset($item['value']) ? $item['value'] : '',
                    );
                    $write->insert($condTable, $condRow);
                }
            }
        }

        return $groupId;
    }

    /**
     * Get the conditions tree as a PHP array.
     *
     * @return array
     */
    public function getConditionsData()
    {
        $data = $this->_getData('conditions_data');
        if (is_string($data)) {
            $data = Mage::helper('core')->jsonDecode($data);
            if (!is_array($data)) {
                $data = array();
            }
            $this->setData('conditions_data', $data);
        }
        // Safety net: when this rule was obtained via a Db collection the
        // resource model's _afterLoad() never runs, so conditions_data is
        // empty even though the row may have a saved condition tree.
        // Trigger afterLoad() once so getConditionsDescription() (and any
        // other consumer) sees the real conditions rather than the
        // "匹配所有" fallback. No-op for collection rows already pre-loaded
        // by XFE_Carrier_Model_Resource_Rule_Collection::loadConditions().
        if ((!is_array($data) || empty($data)) && $this->getId()) {
            $this->afterLoad();
            $data = $this->_getData('conditions_data');
            if (is_string($data)) {
                $data = Mage::helper('core')->jsonDecode($data);
            }
        }
        return is_array($data) ? $data : array();
    }

    /**
     * Set the conditions tree (array form).
     *
     * @param array $data
     * @return $this
     */
    public function setConditionsData($data)
    {
        return $this->setData('conditions_data', $data);
    }

    /**
     * Get the conditions tree as a JSON string. Used by the Edit form
     * to seed the `groups_data_hidden` field for the JS builder.
     *
     * @return string
     */
    public function getConditionsDataJson()
    {
        $data = $this->getConditionsData();
        return Mage::helper('core')->jsonEncode($data);
    }

    /**
     * Get a human-readable description of the condition tree.
     *
     * @return string
     */
    public function getConditionsDescription()
    {
        $groups = $this->getConditionsData();
        if (empty($groups)) {
            return Mage::helper('xfe_carrier')->__('匹配所有');
        }

        $groupDescriptions = array();
        foreach ($groups as $group) {
            $groupDescriptions[] = $this->_formatConditionGroup($group, false);
        }

        if (count($groupDescriptions) === 1) {
            return $groupDescriptions[0];
        }

        return Mage::helper('xfe_carrier')->__('全部条件组（AND）：%s', implode('；', $groupDescriptions));
    }

    /**
     * @param array $group
     * @param bool $nested
     * @return string
     */
    protected function _formatConditionGroup(array $group, $nested)
    {
        $aggregator = isset($group['aggregator']) ? strtolower($group['aggregator']) : 'all';
        $items = isset($group['conditions']) && is_array($group['conditions'])
            ? $group['conditions']
            : array();
        $descriptions = array();

        foreach ($items as $item) {
            if (isset($item['type']) && $item['type'] === 'group') {
                $descriptions[] = $this->_formatConditionGroup($item, true);
            } else {
                $descriptions[] = $this->_formatCondition($item);
            }
        }

        if (empty($descriptions)) {
            return $nested ? '（匹配所有）' : '匹配所有';
        }

        $separator = $aggregator === 'any' ? ' 或 ' : ' 且 ';
        $description = implode($separator, $descriptions);
        return $nested ? '（' . $description . '）' : $description;
    }

    /**
     * @param array $condition
     * @return string
     */
    protected function _formatCondition(array $condition)
    {
        $attribute = isset($condition['attribute']) ? (string)$condition['attribute'] : '';
        $operator = isset($condition['operator']) ? (string)$condition['operator'] : '==';
        $value = isset($condition['value']) ? $condition['value'] : '';
        $attributeOptions = Mage::helper('xfe_carrier')->getConditionAttributeOptions();
        $operatorOptions = Mage::helper('xfe_carrier')->getOperatorOptions();
        $attributeLabel = isset($attributeOptions[$attribute]) ? $attributeOptions[$attribute] : $attribute;
        $operatorLabel = isset($operatorOptions[$operator]) ? $operatorOptions[$operator] : $operator;
        $description = $attributeLabel . ' ' . $operatorLabel;

        if ($operator !== 'is_null' && $operator !== 'is_not_null') {
            $description .= ' ' . (string)$value;
        }

        return $description;
    }
}
