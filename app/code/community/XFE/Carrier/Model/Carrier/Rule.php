<?php

class XFE_Carrier_Model_Carrier_Rule extends Mage_Core_Model_Abstract
{
    protected $_eventPrefix = 'xfe_carrier_rule';
    protected $_eventObject = 'rule';

    protected function _construct()
    {
        $this->_init('xfe_carrier/carrier_rule');
    }

    /**
     * Save conditions after saving rule
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
}
