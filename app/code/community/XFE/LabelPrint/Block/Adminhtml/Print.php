<?php

class XFE_LabelPrint_Block_Adminhtml_Print extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'xfe_labelprint';
        $this->_controller = 'adminhtml_print';
        $this->_headerText = Mage::helper('xfe_labelprint')->__('Label Print');
        $this->_addButtonLabel = null;
        parent::__construct();
    }

    /**
     * @return string
     */
    public function getHeaderHtml()
    {
        return '';
    }

}