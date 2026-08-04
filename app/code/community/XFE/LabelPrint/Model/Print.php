<?php

/**
 * Label Print model
 *
 * Represents a single label file produced for a shipment. Other modules
 * call XFE_LabelPrint_Helper_Data::record() to insert a row; this model
 * is the read side used by the admin grid.
 *
 * additional_data is stored as a JSON string but accessed via
 * getAdditionalDataArray() / setAdditionalDataArray() for callers that
 * want a structured array.
 *
 * Files live under var/ rather than media/, so the public "URL"
 * accessors return admin download URLs (which stream the file through
 * the controller) instead of media-base URLs. var/ is not web-served,
 * which keeps the stored artifacts inaccessible by URL guessing.
 */
class XFE_LabelPrint_Model_Print extends Mage_Core_Model_Abstract
{
    /** @var string */
    protected $_eventPrefix = 'xfe_labelprint_print';

    /** @var string */
    protected $_eventObject = 'print';

    protected function _construct()
    {
        $this->_init('xfe_labelprint/print');
    }

    /**
     * Admin download URL for the current label file. Streams the file
     * through the controller because the file lives under var/ and is
     * not directly web-accessible.
     *
     * @return string Empty string when no file is attached.
     */
    public function getFileUrl()
    {
        if ((string)$this->getPathFile() === '') {
            return '';
        }
        return Mage::helper('adminhtml')->getUrl('*/*/download', array(
            'id'   => (int)$this->getId(),
            'kind' => 'current',
        ));
    }

    /**
     * Admin download URL for the previous label file (after a re-print).
     *
     * @return string Empty string when no previous file exists.
     */
    public function getOldFileUrl()
    {
        if ((string)$this->getOldPathFile() === '') {
            return '';
        }
        return Mage::helper('adminhtml')->getUrl('*/*/download', array(
            'id'   => (int)$this->getId(),
            'kind' => 'old',
        ));
    }

    /**
     * Absolute filesystem path to the current label file.
     *
     * @return string|null
     */
    public function getFileAbsolutePath()
    {
        $path = (string)$this->getPathFile();
        if ($path === '') {
            return null;
        }
        return Mage::getBaseDir('var') . DS . str_replace('/', DS, $path);
    }

    /**
     * Absolute filesystem path to the previous label file.
     *
     * @return string|null
     */
    public function getOldFileAbsolutePath()
    {
        $path = (string)$this->getOldPathFile();
        if ($path === '') {
            return null;
        }
        return Mage::getBaseDir('var') . DS . str_replace('/', DS, $path);
    }

    /**
     * Is the current file still readable on disk?
     *
     * @return bool
     */
    public function isFileReadable()
    {
        $abs = $this->getFileAbsolutePath();
        return ($abs !== null) && is_file($abs) && is_readable($abs);
    }

    /**
     * additional_data accessor returning a decoded array.
     *
     * @return array
     */
    public function getAdditionalDataArray()
    {
        $raw = (string)$this->getAdditionalData();
        if ($raw === '') {
            return array();
        }
        $decoded = Mage::helper('core')->jsonDecode($raw);
        return is_array($decoded) ? $decoded : array();
    }

    /**
     * additional_data mutator accepting an array; encoded as JSON.
     *
     * @param array|string $value
     * @return $this
     */
    public function setAdditionalDataArray($value)
    {
        if (is_array($value)) {
            $value = Mage::helper('core')->jsonEncode($value);
        } elseif ($value === null) {
            $value = '';
        }
        return $this->setAdditionalData((string)$value);
    }
}
