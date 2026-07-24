<?php

/**
 * File Validator
 *
 * Validates a $_FILES row and produces a normalized description (or
 * throws Mage_Core_Exception on failure). Pure - no DB, no models.
 *
 * Constraints come from system config (core_config_data) read via
 * the helper module-level helper, but the validator itself can also
 * be invoked directly with explicit caps. The InvoiceUploader passes
 * caps in to keep coupling one-directional.
 */
class XFE_InvoiceUpload_Service_File_Validator
{
    /**
     * @param array  $fileData  $_FILES['invoice'] row
     * @param int    $maxBytes
     * @param array  $allowedExtensions  Lower-case list, no dot
     * @return array{ext:string,size:int,name:string} Normalized description
     * @throws Mage_Core_Exception
     */
    public function validate(array $fileData, $maxBytes, array $allowedExtensions)
    {
        if (empty($fileData['tmp_name']) || empty($fileData['name'])
            || empty($fileData['size']) || !is_uploaded_file($fileData['tmp_name'])) {
            Mage::throwException(
                Mage::helper('xfe_invoiceupload')->__('No file was uploaded.')
            );
        }

        $size = (int)$fileData['size'];
        if ($size <= 0) {
            Mage::throwException(
                Mage::helper('xfe_invoiceupload')->__('Empty file.')
            );
        }
        if ($maxBytes > 0 && $size > $maxBytes) {
            Mage::throwException(
                Mage::helper('xfe_invoiceupload')->__('File exceeds the maximum allowed size.')
            );
        }

        $ext = strtolower((string)pathinfo($fileData['name'], PATHINFO_EXTENSION));
        $allowedExtensions = array_map('strtolower', $allowedExtensions);
        if ($allowedExtensions && !in_array($ext, $allowedExtensions, true)) {
            Mage::throwException(
                Mage::helper('xfe_invoiceupload')->__('File type %s is not allowed.', $ext)
            );
        }

        $name = (string)pathinfo($fileData['name'], PATHINFO_FILENAME);
        $name = trim(preg_replace('/[^\\p{L}\\p{N}\\s._-]/u', '', $name));
        if ($name === '') {
            $name = 'invoice';
        }

        return array(
            'ext'  => $ext,
            'size' => $size,
            'name' => $name,
        );
    }
}
