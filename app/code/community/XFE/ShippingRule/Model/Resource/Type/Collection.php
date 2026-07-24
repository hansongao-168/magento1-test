<?php
/**
 * XFE ShippingRule Type Collection
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Model_Resource_Type_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeshippingrule/type');
    }

    /**
     * Add active filter
     *
     * @return $this
     */
    public function addActiveFilter()
    {
        $this->addFieldToFilter('status', 1);
        return $this;
    }

    /**
     * Get options array for dropdown
     *
     * @return array
     */
    public function toOptionArray()
    {
        return $this->_toOptionArray('type_id', 'description');
    }

    /**
     * Get options hash for dropdown (id => label)
     *
     * @return array
     */
    public function toOptionHash()
    {
        return $this->_toOptionHash('type_id', 'description');
    }
}
