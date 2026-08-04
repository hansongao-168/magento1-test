<?php

class XFE_LabelPrint_Model_Resource_Print_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_labelprint/print');
    }

    /**
     * Default sort: most recent print first.
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()->order(array('id DESC'));
        return $this;
    }
}
