<?php

class XFE_Carrier_Model_Carrier_Account extends Mage_Core_Model_Abstract
{
    protected $_eventPrefix = 'xfe_carrier_account';
    protected $_eventObject = 'account';

    protected function _construct()
    {
        $this->_init('xfe_carrier/carrier_account');
    }

    protected function _beforeSave()
    {
        parent::_beforeSave();

        $now = Varien_Date::now();
        if ($this->isObjectNew()) {
            $this->setCreatedAt($now);
        }
        $this->setUpdatedAt($now);

        return $this;
    }
}
