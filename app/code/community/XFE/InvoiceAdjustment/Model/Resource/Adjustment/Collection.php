<?php
/**
 * Invoice Adjustment Collection
 *
 * @category   Community
 * @package    XFE_InvoiceAdjustment
 */
class XFE_InvoiceAdjustment_Model_Resource_Adjustment_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Init collection
     */
    protected function _construct()
    {
        $this->_init('invoiceadjustment/adjustment');
    }
}
