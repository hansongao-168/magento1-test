<?php

class XFE_LabelPrint_Block_Adminhtml_Print extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_print';
        $this->_blockGroup = 'xfe_labelprint';
        $this->_headerText = Mage::helper('xfe_labelprint')->__('Label Print');
        $this->_addButtonLabel = null;
        parent::__construct();
    }

    /**
     * Suppress the "Add New" button: rows are produced by callers,
     * not edited in the admin.
     *
     * @return string
     */
    public function getHeaderHtml()
    {
        return '';
    }
}
