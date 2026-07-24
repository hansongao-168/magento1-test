<?php

/**
 * Frontend Controller
 *
 * HTTP entry for the customer-facing upload / delete actions.
 *   POST /xfe_invoiceupload/invoice/upload    -> upload
 *   POST /xfe_invoiceupload/invoice/delete   -> delete (deletes by id)
 *
 * The controller does:
 *   1. CSRF + session checks
 *   2. Resolves customer + order ids (never trusts raw input blindly)
 *   3. Delegates to Service\Registry::uploader()
 *
 * Single direction: Controller -> Service -> (model + file helpers).
 * No business logic in here.
 */
class XFE_InvoiceUpload_InvoiceController extends Mage_Core_Controller_Front_Action
{
    /**
     * Pre-dispatch hook: require a logged-in customer.
     */
    public function preDispatch()
    {
        parent::preDispatch();
        if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
            $this->_redirect('customer/account/login');
            return;
        }
    }

    /**
     * POST file + render the block again.
     */
    public function uploadAction()
    {
        $this->_ensurePost();
        $orderId    = (int)$this->getRequest()->getParam('order_id');
        $customerId = (int)Mage::getSingleton('customer/session')->getCustomerId();

        $result = XFE_InvoiceUpload_Service_Registry::uploader()
            ->uploadForOrder($orderId, $customerId, $_FILES);

        $this->_addNotice($result);
        $this->_redirectBackToOrder($orderId);
    }

    /**
     * POST delete (id + form_key); redirects back.
     */
    public function deleteAction()
    {
        $this->_ensurePost();
        $invoiceId  = (int)$this->getRequest()->getPost('invoice_id');
        $orderId    = (int)$this->getRequest()->getPost('order_id');
        $customerId = (int)Mage::getSingleton('customer/session')->getCustomerId();

        $ok = XFE_InvoiceUpload_Service_Registry::uploader()
            ->deleteForCustomer($invoiceId, $customerId);

        if ($ok) {
            Mage::getSingleton('customer/session')->addSuccess(
                Mage::helper('xfe_invoiceupload')->__('Invoice deleted.')
            );
        } else {
            Mage::getSingleton('customer/session')->addError(
                Mage::helper('xfe_invoiceupload')->__('Invoice not found.')
            );
        }

        $this->_redirectBackToOrder($orderId);
    }

    /**
     * Enforce POST + form-key.
     */
    protected function _ensurePost()
    {
        if (!$this->getRequest()->isPost()) {
            Mage::throwException('POST required.');
        }
        Mage::getSingleton('core/session')->getFormKey();  // forces form_key init
        if (!$this->_validateFormKey()) {
            Mage::throwException('Invalid form key.');
        }
    }

    /**
     * Upload result -> notice on the customer session.
     *
     * @param XFE_InvoiceUpload_Service_Invoice_UploadResult $result
     */
    protected function _addNotice(XFE_InvoiceUpload_Service_Invoice_UploadResult $result)
    {
        $session = Mage::getSingleton('customer/session');
        if ($result->isSuccess()) {
            $session->addSuccess(
                Mage::helper('xfe_invoiceupload')->__('Invoice uploaded.')
            );
        } else {
            $session->addError($result->getMessage());
        }
    }

    /**
     * Try to send the customer back to the order view page; fall back
     * to the customer account dashboard when no order id is provided.
     *
     * @param int $orderId
     */
    protected function _redirectBackToOrder($orderId)
    {
        if ($orderId > 0) {
            $url = Mage::getUrl('sales/order/view', array('order_id' => $orderId));
        } else {
            $url = Mage::getUrl('customer/account');
        }
        $this->_redirectUrl($url);
    }
}
