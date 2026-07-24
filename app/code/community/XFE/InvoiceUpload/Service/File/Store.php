<?php

/**
 * File Store
 *
 * Single responsibility: filesystem layout for stored invoice files.
 * Knows nothing about invoices, customers, uploads, Magento models.
 *
 *   media/
 *     xfe/invoiceupload/{customer_id}/{order_id}/{invoice_id}.{ext}
 *
 * The directory tree is derived from customer/order IDs (both already
 * known to be integers in the calling context) so the store stays
 * completely stateless.
 */
class XFE_InvoiceUpload_Service_File_Store
{
    /** @var string */
    protected $_baseRel = 'xfe/invoiceupload';

    /**
     * Absolute target directory for one (customer, order) pair.
     *
     * @param int $customerId
     * @param int $orderId
     * @return string
     */
    public function getOrderDir($customerId, $orderId)
    {
        $customerId = (int)$customerId;
        $orderId = (int)$orderId;
        return Mage::getBaseDir('media') . DS . $this->_baseRel
             . DS . $customerId . DS . $orderId;
    }

    /**
     * Make sure the directory exists.
     *
     * @param int $customerId
     * @param int $orderId
     * @return string Absolute path to the (now existing) directory
     */
    public function ensureOrderDir($customerId, $orderId)
    {
        $dir = $this->getOrderDir($customerId, $orderId);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Build both the relative and absolute paths for a future file.
     *
     * @param int    $customerId
     * @param int    $orderId
     * @param int    $invoiceId
     * @param string $extension  Without leading dot
     * @return array{relative:string,absolute:string}
     */
    public function buildPaths($customerId, $orderId, $invoiceId, $extension)
    {
        $customerId = (int)$customerId;
        $orderId = (int)$orderId;
        $invoiceId = (int)$invoiceId;
        $extension = preg_replace('/[^a-z0-9]/i', '', $extension);
        $extension = strtolower($extension ?: 'bin');

        $filename = $invoiceId . '.' . $extension;
        $relative = $this->_baseRel . DS . $customerId . DS . $orderId . DS . $filename;
        $absolute = Mage::getBaseDir('media') . DS . $relative;

        return array('relative' => $relative, 'absolute' => $absolute);
    }

    /**
     * Delete a stored file by its relative path. Idempotent.
     *
     * @param string $relativePath
     * @return bool true if file did not exist OR was removed
     */
    public function deleteByRelativePath($relativePath)
    {
        if (!$relativePath) {
            return true;
        }
        $abs = Mage::getBaseDir('media') . DS . $relativePath;
        if (!file_exists($abs)) {
            return true;
        }
        return (bool)@unlink($abs);
    }

    /**
     * Best-effort prune of an empty order directory. Never throws.
     *
     * @param int $customerId
     * @param int $orderId
     * @return void
     */
    public function removeEmptyOrderDir($customerId, $orderId)
    {
        $customerDir = Mage::getBaseDir('media') . DS . $this->_baseRel
                     . DS . (int)$customerId . DS . (int)$orderId;
        if (is_dir($customerDir)) {
            @rmdir($customerDir);
        }
        $customerRoot = Mage::getBaseDir('media') . DS . $this->_baseRel
                      . DS . (int)$customerId;
        if (is_dir($customerRoot)) {
            @rmdir($customerRoot);
        }
    }
}
