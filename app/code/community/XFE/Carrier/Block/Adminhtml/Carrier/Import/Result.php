<?php

/**
 * Result page for bulk-import. Reads XFE_Carrier_Model_Service_Importer_Result
 * from the registry and exposes its counters + error list to the template.
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Import_Result extends Mage_Adminhtml_Block_Template
{
    /**
     * @var XFE_Carrier_Model_Service_Importer_Result|null
     */
    protected $_result;

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('xfe_carrier/carrier/import/result.phtml');
        $this->_result = Mage::registry('xfe_carrier_import_result');
    }

    /**
     * @return XFE_Carrier_Model_Service_Importer_Result|null
     */
    public function getResult()
    {
        return $this->_result;
    }

    /**
     * @return int
     */
    public function getCreatedCount()
    {
        return $this->_result ? (int)$this->_result->created : 0;
    }

    /**
     * @return int
     */
    public function getUpdatedCount()
    {
        return $this->_result ? (int)$this->_result->updated : 0;
    }

    /**
     * @return int
     */
    public function getSkippedCount()
    {
        return $this->_result ? (int)$this->_result->skipped : 0;
    }

    /**
     * @return array<int, string>
     */
    public function getErrors()
    {
        return $this->_result ? $this->_result->getErrors() : array();
    }

    public function getBackUrl()
    {
        return $this->getUrl('*/carrier/import');
    }

    public function getListUrl()
    {
        return $this->getUrl('*/carrier/');
    }
}