<?php
/**
 * @method XFE_LabelPrint_Model_Resource_Print _getResource()
 * @method XFE_LabelPrint_Model_Resource_Print_Collection getCollection()
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

    public function getFileAbsolutePath()
    {
        $path = (string)$this->getPathFile();
        if ($path === '') {
            return null;
        }
        return Mage::getBaseDir('var') . DS . str_replace('/', DS, $path);
    }

    public function getOldFileAbsolutePath()
    {
        $path = (string)$this->getOldPathFile();
        if ($path === '') {
            return null;
        }
        return Mage::getBaseDir('var') . DS . str_replace('/', DS, $path);
    }

    public function isFileReadable()
    {
        $abs = $this->getFileAbsolutePath();
        return ($abs !== null) && is_file($abs) && is_readable($abs);
    }

    public function getAdditionalDataArray()
    {
        $raw = (string)$this->getAdditionalData();
        if ($raw === '') {
            return array();
        }
        $decoded = Mage::helper('core')->jsonDecode($raw);
        return is_array($decoded) ? $decoded : array();
    }

    public function setAdditionalDataArray($arr)
    {
        $data = $this->getAdditionalDataArray();
        if (is_array($arr)) {
            foreach ($arr as $key => $value) {
                $data[$key] = $value;
            }
        }
        $value = Mage::helper('core')->jsonEncode($data);
        return $this->setAdditionalData((string)$value);
    }

    public function loadByOldPathFile($orderId, $trackingNumberId, $oldPathFile)
    {
        $this->_beforeLoad($oldPathFile, 'old_path_file');
        $this->_getResource()->loadByOldPathFile(
            $this, $orderId, $trackingNumberId, $oldPathFile
        );
        $this->_afterLoad();
        $this->setOrigData();
        $this->_hasDataChanges = false;
        return $this;
    }

}