<?php
class XFE_CheckoutErrors_Adminhtml_Xfe_CheckoutErrorController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->_title($this->__('Checkout 错误记录'));
        $this->loadLayout();
        $this->_setActiveMenu('sales/xfe_checkout_errors');
        $this->renderLayout();
    }

    public function gridAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/xfe_checkout_errors');
    }
}
