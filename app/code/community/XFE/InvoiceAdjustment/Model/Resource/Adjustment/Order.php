<?php
/**
 * Invoice Adjustment Order Resource Model
 *
 * @category   Community
 * @package    XFE_InvoiceAdjustment
 */
class XFE_InvoiceAdjustment_Model_Resource_Adjustment_Order extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Init resource
     */
    protected function _construct()
    {
        $this->_init('invoiceadjustment/adjustment_order', 'id');
    }
}
