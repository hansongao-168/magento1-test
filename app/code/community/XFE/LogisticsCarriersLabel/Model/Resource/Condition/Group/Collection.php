<?php
/**
 * XFE LogisticsCarriersLabel ConditionGroup Collection
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_Model_Resource_Condition_Group_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xcarrierslabel/condition_group');
    }

    /**
     * Add rule filter
     *
     * @param int $ruleId
     * @return $this
     */
    public function addRuleFilter($ruleId)
    {
        $this->addFieldToFilter('rule_id', $ruleId);
        return $this;
    }

    /**
     * Set sort order
     *
     * @return $this
     */
    public function setSortOrder()
    {
        $this->setOrder('sort_order', Varien_Data_Collection::SORT_ORDER_ASC);
        return $this;
    }
}
