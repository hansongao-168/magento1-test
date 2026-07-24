<?php

class XFE_Carrier_Model_Resource_Rule_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_carrier/carrier_rule');
    }
}
