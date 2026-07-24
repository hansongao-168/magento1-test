<?php
class XFE_CheckoutErrors_Model_CheckoutError extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_checkouterrors/checkoutError');
    }

    protected function _beforeSave()
    {
        parent::_beforeSave();
        if (!$this->getCreatedAt()) {
            $this->setCreatedAt(Mage::getSingleton('core/date')->gmtDate());
        }
        return $this;
    }
}
