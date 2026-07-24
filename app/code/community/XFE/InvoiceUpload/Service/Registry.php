<?php

/**
 * Service Registry
 *
 * One-stop factory for the InvoiceUpload module. Keeps call-sites short
 * and lets tests inject doubles via setUploader().
 *
 *     $result = XFE_InvoiceUpload_Service_Registry::uploader()
 *              ->uploadForOrder($orderId, $customerId, $_FILES['invoice']);
 */
class XFE_InvoiceUpload_Service_Registry
{
    /** @var XFE_InvoiceUpload_Service_Invoice_Uploader|null */
    protected static $_uploader = null;

    /**
     * @return XFE_InvoiceUpload_Service_Invoice_Uploader
     */
    public static function uploader()
    {
        if (self::$_uploader === null) {
            self::$_uploader = XFE_InvoiceUpload_Service_Invoice_Uploader::instance();
        }
        return self::$_uploader;
    }

    /**
     * Test hook.
     *
     * @param XFE_InvoiceUpload_Service_Invoice_Uploader|null $instance
     */
    public static function setUploader($instance)
    {
        self::$_uploader = $instance;
    }
}
