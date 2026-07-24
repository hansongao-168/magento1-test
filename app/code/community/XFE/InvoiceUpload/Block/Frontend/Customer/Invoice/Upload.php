<?php

/**
 * Block\Frontend\Customer\Invoice\Upload
 *
 * Single responsibility: PRESENT the upload form + history.
 *
 * Uses:
 *   - XFE_InvoiceUpload_Service_Registry::uploader()  (read-only listForOrder)
 *   - Mage_Customer model (read-only, for "is logged in" + display name)
 *
 * Does NOT import the controller, the service implementation or any
 * filesystem helper. The block never writes data - only reads.
 */
class XFE_InvoiceUpload_Block_Frontend_Customer_Invoice_Upload
    extends Mage_Core_Block_Template
{
    /** @var string */
    protected $_template = 'invoiceupload/upload.phtml';

    /** @var int|null Cached current order id from registry */
    protected $_orderId = null;

    /**
     * Render the block only when all of the following hold:
     *   1. the customer is logged in
     *   2. an order id is in the registry ("current_order")
     *   3. that order belongs to this customer
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
            return '';
        }
        if (!$this->_resolveOrderId()) {
            return '';
        }
        return parent::_toHtml();
    }

    /**
     * Returns the integer order id from the "current_order" registry
     * (the standard registry key used by Mage_Sales) only when the
     * order belongs to the logged-in customer.
     *
     * @return int|null
     */
    protected function _resolveOrderId()
    {
        if ($this->_orderId !== null) {
            return $this->_orderId;
        }
        $order = Mage::registry('current_order');
        if (!$order || !$order->getId()) {
            return null;
        }
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        if ((int)$order->getCustomerId() !== (int)$customer->getId()) {
            return null;
        }
        return $this->_orderId = (int)$order->getId();
    }

    public function getOrderId()
    {
        return (int)$this->_resolveOrderId();
    }

    /**
     * @return XFE_InvoiceUpload_Model_Resource_Invoice_Collection
     */
    public function getInvoices()
    {
        $orderId    = $this->getOrderId();
        $customerId = (int)Mage::getSingleton('customer/session')->getCustomerId();
        if ($orderId <= 0 || $customerId <= 0) {
            return Mage::getModel('xfe_invoiceupload/invoice')->getCollection();
        }
        return XFE_InvoiceUpload_Service_Registry::uploader()
            ->listForOrder($orderId, $customerId);
    }

    public function getFormActionUrl()
    {
        return Mage::helper('xfe_invoiceupload')->getUploadUrl($this->getOrderId());
    }

    public function getDeleteUrl($invoiceId)
    {
        return Mage::helper('xfe_invoiceupload')->getDeleteUrl((int)$invoiceId);
    }

    /**
     * Render a single invoice row (label / size / date / delete button).
     *
     * @param XFE_InvoiceUpload_Model_Invoice $invoice
     * @return string
     */
    public function renderRow(XFE_InvoiceUpload_Model_Invoice $invoice)
    {
        $helper = Mage::helper('xfe_invoiceupload');
        $size = (int)$invoice->getSizeBytes();
        $label = $invoice->getLabel() ?: $invoice->getOriginalName();
        $url = $invoice->getDownloadUrl();
        $deleteUrl = $this->getDeleteUrl((int)$invoice->getId());

        return sprintf(
            '<li class="xfe-invoice-row" data-id="%d">'
            . '<a href="%s" target="_blank" rel="noopener">%s</a>'
            . ' <span class="size">(%s)</span>'
            . ' <span class="date">%s</span>'
            . ' <form method="post" action="%s" style="display:inline" '
            .   'onsubmit="return confirm(\'%s\');">'
            .   '<input type="hidden" name="invoice_id" value="%d" />'
            .   '<input type="hidden" name="order_id" value="%d" />'
            .   '<input type="hidden" name="form_key" value="%s" />'
            .   '<button type="submit" class="xfe-invoice-delete">%s</button>'
            . '</form>'
            . '</li>',
            (int)$invoice->getId(),
            htmlspecialchars((string)$url, ENT_QUOTES),
            htmlspecialchars((string)$label, ENT_QUOTES),
            htmlspecialchars($helper->formatBytes($size), ENT_QUOTES),
            htmlspecialchars((string)$invoice->getCreatedAt(), ENT_QUOTES),
            htmlspecialchars((string)$deleteUrl, ENT_QUOTES),
            htmlspecialchars(
                str_replace("'", "\\'", $helper->__('Delete this invoice?')),
                ENT_QUOTES
            ),
            (int)$invoice->getId(),
            $this->getOrderId(),
            htmlspecialchars((string)Mage::getSingleton('core/session')->getFormKey(), ENT_QUOTES),
            htmlspecialchars((string)$helper->__('Delete'), ENT_QUOTES)
        );
    }
}
