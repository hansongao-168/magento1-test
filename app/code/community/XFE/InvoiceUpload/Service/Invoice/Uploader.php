<?php

/**
 * Service InvoiceUploader
 *
 * Owns the lifecycle of an invoice file:
 *   - validate the upload (size, extension)
 *   - write file to disk via File\Store
 *   - create the XFE_InvoiceUpload_Model_Invoice row
 *   - list a customer's invoices for an order
 *   - delete a customer's invoice (file + row + ownership check)
 *
 * Single direction:
 *   Uploader ─▶ File\Store         (filesystem layout)
 *   Uploader ─▶ File\Validator     (input validation)
 *   Uploader ─▶ Model\Invoice      (DB persistence)
 *
 * No other service is referenced.
 *
 * Ownership / authorisation: every public method that accepts a
 * customer_id enforces "the invoice belongs to this customer" by
 * filtering rows on customer_id. The controller is still the right
 * place to authenticate the customer session.
 */
class XFE_InvoiceUpload_Service_Invoice_Uploader
{
    /** @var self|null */
    protected static $_instance = null;

    /** @var XFE_InvoiceUpload_Service_File_Store */
    protected $_fileStore;

    /** @var XFE_InvoiceUpload_Service_File_Validator */
    protected $_fileValidator;

    public static function instance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self(
                new XFE_InvoiceUpload_Service_File_Store(),
                new XFE_InvoiceUpload_Service_File_Validator()
            );
        }
        return self::$_instance;
    }

    public static function setInstance($instance)
    {
        self::$_instance = $instance;
    }

    public function __construct(
        XFE_InvoiceUpload_Service_File_Store $fileStore,
        XFE_InvoiceUpload_Service_File_Validator $fileValidator
    ) {
        $this->_fileStore = $fileStore;
        $this->_fileValidator = $fileValidator;
    }

    /**
     * Handle a fresh upload.
     *
     * @param int   $orderId
     * @param int   $customerId
     * @param array $fileData   $_FILES row
     * @return XFE_InvoiceUpload_Service_Invoice_UploadResult
     */
    public function uploadForOrder($orderId, $customerId, array $fileData)
    {
        $orderId    = (int)$orderId;
        $customerId = (int)$customerId;
        if ($orderId <= 0 || $customerId <= 0) {
            return $this->_failure('Invalid order or customer.');
        }
        $helper = Mage::helper('xfe_invoiceupload');

        try {
            $meta = $this->_fileValidator->validate(
                $fileData,
                (int)$helper->getMaxBytes(),
                $helper->getAllowedExtensions()
            );
        } catch (Exception $e) {
            return $this->_failure($e->getMessage());
        }

        // Create the DB row first so we can use its ID for the file name.
        $invoice = Mage::getModel('xfe_invoiceupload/invoice');
        $invoice->setData(array(
            'customer_id'    => $customerId,
            'order_id'       => $orderId,
            'label'          => $meta['name'],
            'original_name'  => (string)$fileData['name'],
            'mime_type'      => isset($fileData['type']) ? (string)$fileData['type'] : null,
            'size_bytes'     => $meta['size'],
            'sha1'           => @sha1_file($fileData['tmp_name']) ?: null,
        ));
        $invoice->save();

        $paths = $this->_fileStore->buildPaths(
            $customerId, $orderId, (int)$invoice->getId(), $meta['ext']
        );
        $this->_fileStore->ensureOrderDir($customerId, $orderId);

        if (!@move_uploaded_file($fileData['tmp_name'], $paths['absolute'])) {
            // Fall back to copy in case move_uploaded_file is unavailable
            // (e.g. when the uploader runs in a non-PHP uploader context)
            if (!@copy($fileData['tmp_name'], $paths['absolute'])) {
                $invoice->delete();
                return $this->_failure('Failed to store uploaded file on disk.');
            }
        }

        $invoice->setData('path', $paths['relative'])->save();

        return new XFE_InvoiceUpload_Service_Invoice_UploadResult(array(
            'success'    => true,
            'invoice_id' => (int)$invoice->getId(),
            'message'    => 'OK',
        ));
    }

    /**
     * @param int $orderId
     * @param int $customerId
     * @return XFE_InvoiceUpload_Model_Resource_Invoice_Collection
     */
    public function listForOrder($orderId, $customerId)
    {
        $orderId    = (int)$orderId;
        $customerId = (int)$customerId;
        if ($orderId <= 0 || $customerId <= 0) {
            return Mage::getModel('xfe_invoiceupload/invoice')->getCollection();
        }
        return Mage::getModel('xfe_invoiceupload/invoice')->getCollection()
            ->addCustomerFilter($customerId)
            ->addOrderFilter($orderId)
            ->setOrder('created_at', 'DESC');
    }

    /**
     * Delete a single invoice IFF it belongs to $customerId.
     *
     * @param int $invoiceId
     * @param int $customerId
     * @return bool
     */
    public function deleteForCustomer($invoiceId, $customerId)
    {
        $invoiceId = (int)$invoiceId;
        $customerId = (int)$customerId;
        if ($invoiceId <= 0 || $customerId <= 0) {
            return false;
        }

        $invoice = Mage::getModel('xfe_invoiceupload/invoice')->load($invoiceId);
        if (!$invoice->getId() || (int)$invoice->getCustomerId() !== $customerId) {
            return false;
        }

        $this->_fileStore->deleteByRelativePath($invoice->getData('path'));
        $invoice->delete();
        $this->_fileStore->removeEmptyOrderDir(
            (int)$invoice->getCustomerId(),
            (int)$invoice->getOrderId()
        );
        return true;
    }

    /**
     * Convenience for shell/error responses: build a failure DTO.
     *
     * @param string $msg
     * @return XFE_InvoiceUpload_Service_Invoice_UploadResult
     */
    protected function _failure($msg)
    {
        return new XFE_InvoiceUpload_Service_Invoice_UploadResult(array(
            'success' => false,
            'message' => (string)$msg,
        ));
    }
}
