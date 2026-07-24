<?php

/**
 * UploadResult DTO
 *
 * Plain carrier so the controller does not have to inspect raw arrays
 * or call the model directly to know whether an upload succeeded.
 */
class XFE_InvoiceUpload_Service_Invoice_UploadResult
{
    /** @var bool */
    protected $_success = false;

    /** @var string */
    protected $_message = '';

    /** @var int|null */
    protected $_invoiceId = null;

    public function __construct(array $data = array())
    {
        if (isset($data['success']))   { $this->_success = (bool)$data['success']; }
        if (isset($data['message']))   { $this->_message = (string)$data['message']; }
        if (isset($data['invoice_id'])){ $this->_invoiceId = (int)$data['invoice_id']; }
    }

    /** @return bool */
    public function isSuccess()       { return $this->_success; }

    /** @return string */
    public function getMessage()      { return $this->_message; }

    /** @return int|null */
    public function getInvoiceId()    { return $this->_invoiceId; }
}
