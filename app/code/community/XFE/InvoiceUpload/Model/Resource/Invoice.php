<?php

class XFE_InvoiceUpload_Model_Resource_Invoice extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_invoiceupload/invoice', 'invoice_id');
    }
}
