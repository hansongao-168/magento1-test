<?php
/**
 * XFE LogisticsCarriersLabel Condition Collection
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_Model_Resource_Condition_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xcarrierslabel/condition');
    }

    /**
     * Add group filter
     *
     * @param int $groupId
     * @return $this
     */
    public function addGroupFilter($groupId)
    {
        $this->addFieldToFilter('group_id', $groupId);
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
