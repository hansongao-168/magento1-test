<?php

class XFE_Carrier_Model_Resource_Carrier_Logo_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_carrier/carrier_logo');
    }

    /**
     * Default sort order by sort_order ASC, then logo_id ASC
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()->order(array('sort_order ASC', 'logo_id ASC'));
        return $this;
    }
}
