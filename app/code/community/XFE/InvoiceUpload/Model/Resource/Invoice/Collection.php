<?php

class XFE_InvoiceUpload_Model_Resource_Invoice_Collection
    extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_invoiceupload/invoice');
    }

    /**
     * Restrict to a single customer's invoices.
     * Ownership concerns MUST also be enforced by the caller.
     *
     * @param int $customerId
     * @return $this
     */
    public function addCustomerFilter($customerId)
    {
        return $this->addFieldToFilter('customer_id', (int)$customerId);
    }

    /**
     * Restrict to a single order's invoices.
     *
     * @param int $orderId
     * @return $this
     */
    public function addOrderFilter($orderId)
    {
        return $this->addFieldToFilter('order_id', (int)$orderId);
    }
}
