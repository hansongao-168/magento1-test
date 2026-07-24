<?php
class XFE_CheckoutErrors_Model_Resource_CheckoutError_Collection
    extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_checkouterrors/checkoutError');
    }
}
