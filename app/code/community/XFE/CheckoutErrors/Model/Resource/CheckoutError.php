<?php
class XFE_CheckoutErrors_Model_Resource_CheckoutError extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_checkouterrors/checkout_error', 'error_id');
    }
}
