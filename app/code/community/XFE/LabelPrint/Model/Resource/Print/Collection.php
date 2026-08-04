<?php

class XFE_LabelPrint_Model_Resource_Print_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_labelprint/print');
    }

    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()->order(array('id DESC'));
        return $this;
    }

}