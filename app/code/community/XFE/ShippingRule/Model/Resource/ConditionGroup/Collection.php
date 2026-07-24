<?php
/**
 * XFE ShippingRule ConditionGroup Collection
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Model_Resource_ConditionGroup_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeshippingrule/condition_group');
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
