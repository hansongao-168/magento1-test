<?php
/**
 * XFE ShippingRule Rule Collection
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Model_Resource_Rule_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeshippingrule/rule');
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
     * Join type data for grid display
     *
     * @return $this
     */
    public function joinTypeData()
    {
        $this->getSelect()
            ->joinLeft(
                array('t' => $this->getTable('xfeshippingrule/type')),
                'main_table.type_id = t.type_id',
                array('type_description' => 't.description')
            );
        return $this;
    }
}
