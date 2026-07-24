<?php
/**
 * Invoice Adjustment Order Collection
 *
 * @category   Community
 * @package    XFE_InvoiceAdjustment
 */
class XFE_InvoiceAdjustment_Model_Resource_Adjustment_Order_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Init collection
     */
    protected function _construct()
    {
        $this->_init('invoiceadjustment/adjustment_order');
    }

    /**
     * Add adjustment filter
     *
     * @param int $adjustmentId
     * @return $this
     */
    public function addAdjustmentFilter($adjustmentId)
    {
        $this->addFieldToFilter('adjustment_id', $adjustmentId);
        return $this;
    }
}
