<?php

/**
 * Carrier Rule Resource Model
 *
 * Owns persistence for xfe_carrier_carrier_rule plus the nested
 * condition-group tree (xfe_carrier_rule_condition_group +
 * xfe_carrier_rule_condition).
 *
 * Loading semantics:
 *   - _afterLoad() materialises the saved condition tree into
 *     model->setConditionsData() so the Edit form can seed the
 *     conditions builder JS with existing data.
 *   - Save / delete of the tree is performed by the model's
 *     _afterSave() (see XFE_Carrier_Model_Carrier_Rule), so the
 *     resource model stays read-only for the tree.
 */
class XFE_Carrier_Model_Resource_Carrier_Rule extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_carrier/carrier_rule', 'rule_id');
    }

    /**
     * After loading a rule, walk the condition-group tree and
     * attach it to the model as `conditions_data` (array).
     *
     * Tree shape (matches ShippingRule):
     *   [
     *     { aggregator: 'all'|'any', conditions: [
     *         { attribute, operator, value } | { type:'group', ...subgroup }
     *     ]},
     *     ...
     *   ]
     *
     * @param Mage_Core_Model_Abstract $object
     * @return $this
     */
    protected function _afterLoad(Mage_Core_Model_Abstract $object)
    {
        /** @var XFE_Carrier_Model_Carrier_Rule $object */
        if (!$object->getId()) {
            return $this;
        }

        $read       = $this->_getReadAdapter();
        $groupTable = $this->getTable('xfe_carrier/rule_condition_group');
        $condTable  = $this->getTable('xfe_carrier/rule_condition');

        $conditionsData = $this->_loadConditionGroups(
            (int)$object->getId(),
            $read,
            $groupTable,
            $condTable
        );
        $object->setConditionsData($conditionsData);

        return $this;
    }

    /**
     * Recursively load condition groups using direct SQL.
     * Mirrors XFE_ShippingRule_Model_Resource_Rule::_loadConditionGroups()
     * so the condition-builder JS sees an identical data shape across
     * both modules.
     *
     * @param int $ruleId
     * @param Varien_Db_Adapter_Interface $read
     * @param string $groupTable
     * @param string $condTable
     * @param int|null $parentGroupId
     * @return array
     */
    protected function _loadConditionGroups(
        $ruleId,
        $read,
        $groupTable,
        $condTable,
        $parentGroupId = null
    ) {
        $select = $read->select()
            ->from($groupTable)
            ->where('rule_id = ?', $ruleId)
            ->order('sort_order', Varien_Data_Collection::SORT_ORDER_ASC);

        if ($parentGroupId === null) {
            $select->where('parent_group_id IS NULL');
        } else {
            $select->where('parent_group_id = ?', (int)$parentGroupId);
        }

        $rows = $read->fetchAll($select);
        $result = array();

        foreach ($rows as $row) {
            $items = $this->_loadGroupConditions((int)$row['group_id'], $read, $condTable);

            // Sub-groups come after leaf conditions, but the builder JS
            // doesn't care about order within a group - it preserves the
            // order rendered in DOM.
            $subGroups = $this->_loadConditionGroups(
                $ruleId,
                $read,
                $groupTable,
                $condTable,
                (int)$row['group_id']
            );
            foreach ($subGroups as $subGroup) {
                $items[] = $subGroup;
            }

            $groupData = array(
                'aggregator' => $row['aggregator'],
                'conditions' => $items,
            );
            if ($parentGroupId !== null) {
                $groupData['type'] = 'group';
            }
            $result[] = $groupData;
        }

        return $result;
    }

    /**
     * Load simple (non-group) conditions for one group.
     *
     * @param int $groupId
     * @param Varien_Db_Adapter_Interface $read
     * @param string $condTable
     * @return array
     */
    protected function _loadGroupConditions($groupId, $read, $condTable)
    {
        $select = $read->select()
            ->from($condTable)
            ->where('group_id = ?', (int)$groupId)
            ->order('sort_order', Varien_Data_Collection::SORT_ORDER_ASC);

        $rows = $read->fetchAll($select);
        $items = array();
        foreach ($rows as $row) {
            $items[] = array(
                'attribute' => $row['attribute'],
                'operator'  => $row['operator'],
                'value'     => $row['value'],
            );
        }
        return $items;
    }
}