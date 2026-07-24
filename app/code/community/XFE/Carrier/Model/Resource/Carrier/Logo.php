<?php

class XFE_Carrier_Model_Resource_Carrier_Logo extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_carrier/carrier_logo', 'logo_id');
    }
}
