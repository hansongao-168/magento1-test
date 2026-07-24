<?php

/**
 * Helper\Data
 *
 * Module-wide read helpers - intentionally thin. The Service does the
 * real work; the helper exposes tunables from system config and a few
 * presentation helpers (URL, message strings) used by the controller
 * and template.
 *
 * Single direction: views/controllers read this, helpers never call
 * Services back.
 */
class XFE_InvoiceUpload_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Module-config key for tunable limits.
     */
    const XML_PATH_MAX_BYTES         = 'xfe_invoiceupload/general/max_bytes';
    const XML_PATH_ALLOWED_EXTS      = 'xfe_invoiceupload/general/allowed_extensions';

    /**
     * @param int|null $storeId
     * @return int
     */
    public function getMaxBytes($storeId = null)
    {
        return (int)Mage::getStoreConfig(self::XML_PATH_MAX_BYTES, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return array Lower-case list of extensions WITHOUT leading dot
     */
    public function getAllowedExtensions($storeId = null)
    {
        $raw = (string)Mage::getStoreConfig(self::XML_PATH_ALLOWED_EXTS, $storeId);
        if ($raw === '') {
            return array();
        }
        $parts = preg_split('/\s*,\s*/', strtolower($raw), -1, PREG_SPLIT_NO_EMPTY);
        return array_map(function ($e) { return ltrim($e, '.'); }, $parts);
    }

    /**
     * Bytes-to-human format (e.g. "5 MB") for the upload limit message.
     *
     * @param int $bytes
     * @return string
     */
    public function formatBytes($bytes)
    {
        $bytes = (int)$bytes;
        if ($bytes <= 0) {
            return '0';
        }
        $units = array('B', 'KB', 'MB', 'GB');
        $i = (int)min(floor(log($bytes, 1024)), count($units) - 1);
        return number_format($bytes / pow(1024, $i), $i ? 1 : 0) . ' ' . $units[$i];
    }

    /**
     * Frontend URL to the upload action.
     *
     * @param int $orderId
     * @return string
     */
    public function getUploadUrl($orderId)
    {
        return Mage::getUrl('xfe_invoiceupload/invoice/upload', array(
            'order_id' => (int)$orderId,
        ));
    }

    /**
     * Frontend URL to the delete action.
     *
     * @param int $invoiceId
     * @return string
     */
    public function getDeleteUrl($invoiceId)
    {
        return Mage::getUrl('xfe_invoiceupload/invoice/delete', array(
            'invoice_id' => (int)$invoiceId,
        ));
    }
}
