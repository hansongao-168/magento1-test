<?php
/**
 * Invoice Adjustment Order Model
 *
 * @category   Community
 * @package    XFE_InvoiceAdjustment
 */
class XFE_InvoiceAdjustment_Model_Adjustment_Order extends Mage_Core_Model_Abstract
{
    /**
     * Init model
     */
    protected function _construct()
    {
        $this->_init('invoiceadjustment/adjustment_order');
    }

    /**
     * Get the associated Magento order
     *
     * @return Mage_Sales_Model_Order|null
     */
    public function getOrder()
    {
        if ($this->getOrderId()) {
            return Mage::getModel('sales/order')->load($this->getOrderId());
        }
        return null;
    }

    /**
     * Get the parent adjustment
     *
     * @return XFE_InvoiceAdjustment_Model_Adjustment|null
     */
    public function getAdjustment()
    {
        if ($this->getAdjustmentId()) {
            return Mage::getModel('invoiceadjustment/adjustment')->load($this->getAdjustmentId());
        }
        return null;
    }
}
