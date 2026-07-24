<?php

/**
 * Invoice model - pure data.
 *
 * Owns ONLY the row <-> object mapping and a convenience `getDownloadUrl()`
 * helper. NEVER imports the upload service or any helper / block. All
 * business operations (validate, move bytes, ownership checks) live in
 * XFE_InvoiceUpload_Service_InvoiceUploader.
 */
class XFE_InvoiceUpload_Model_Invoice extends Mage_Core_Model_Abstract
{
    /** @var string */
    protected $_eventPrefix = 'xfe_invoiceupload_invoice';

    /** @var string */
    protected $_eventObject = 'invoice';

    protected function _construct()
    {
        $this->_init('xfe_invoiceupload/invoice');
    }

    /**
     * Public URL of the stored file.
     * NOTE: returns a URL even for arbitrary files - the controller is
     * responsible for authorising the caller.
     *
     * @return string|null
     */
    public function getDownloadUrl()
    {
        $path = $this->getData('path');
        if (!$path) {
            return null;
        }
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . $path;
    }

    protected function _beforeSave()
    {
        parent::_beforeSave();
        if ($this->isObjectNew()) {
            $this->setCreatedAt(Varien_Date::now());
        }
        return $this;
    }
}
