<?php
/**
 * XFE ShippingRule Rule Resource Model
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Model_Resource_Rule extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeshippingrule/rule', 'rule_id');
    }

    /**
     * Load conditions after loading rule
     * Supports recursive nested condition groups
     * Uses direct SQL to avoid ORM autoloading issues.
     *
     * @param Mage_Core_Model_Abstract $object
     * @return $this
     */
    protected function _afterLoad(Mage_Core_Model_Abstract $object)
    {
        /** @var XFE_ShippingRule_Model_Rule $object */
        $read       = $this->_getReadAdapter();
        $groupTable = $this->getTable('xfeshippingrule/condition_group');
        $condTable  = $this->getTable('xfeshippingrule/condition');

        $conditionsData = $this->_loadConditionGroups($object->getId(), $read, $groupTable, $condTable);
        $object->setConditionsData($conditionsData);
        return $this;
    }

    /**
     * Recursively load condition groups using direct SQL
     *
     * @param int $ruleId
     * @param Varien_Db_Adapter_Interface $read
     * @param string $groupTable
     * @param string $condTable
     * @param int|null $parentGroupId
     * @return array
     */
    protected function _loadConditionGroups($ruleId, $read, $groupTable, $condTable, $parentGroupId = null)
    {
        $select = $read->select()
            ->from($groupTable)
            ->where('rule_id = ?', $ruleId)
            ->order('sort_order', Varien_Data_Collection::SORT_ORDER_ASC);

        if ($parentGroupId === null) {
            $select->where('parent_group_id IS NULL');
        } else {
            $select->where('parent_group_id = ?', $parentGroupId);
        }

        $rows = $read->fetchAll($select);
        $result = array();

        foreach ($rows as $row) {
            $items = $this->_loadGroupConditions($row['group_id'], $read, $condTable);

            // Load nested sub-groups recursively
            $subGroups = $this->_loadConditionGroups($ruleId, $read, $groupTable, $condTable, $row['group_id']);
            foreach ($subGroups as $subGroup) {
                $items[] = $subGroup;
            }

            $groupData = array(
                'aggregator' => $row['aggregator'],
                'conditions' => $items,
            );

            // Mark as sub-group if it has a parent
            if ($parentGroupId !== null) {
                $groupData['type'] = 'group';
            }

            $result[] = $groupData;
        }

        return $result;
    }

    /**
     * Load conditions for a given group using direct SQL
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
            ->where('group_id = ?', $groupId)
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
