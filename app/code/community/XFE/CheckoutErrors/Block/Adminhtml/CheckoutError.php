<?php
class XFE_CheckoutErrors_Block_Adminhtml_CheckoutError extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_checkoutError';
        $this->_blockGroup = 'xfe_checkouterrors_adminhtml';
        $this->_headerText = $this->__('Checkout 错误记录');
        parent::__construct();
        $this->_removeButton('add');
    }
}
