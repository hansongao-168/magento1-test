<?php

/**
 * Upload Form Block
 *
 * Provides data accessors for the upload page template:
 * - Enabled carriers for the select dropdown
 * - Document type options per carrier (as JSON for Alpine.js)
 * - Upload URL and document types AJAX URL
 * - File validation constraints
 *
 * Used by both frontend and admin upload pages.
 *
 * @category   Community
 * @package    XFE_DocumentUpload
 */
class XFE_DocumentUpload_Block_Upload_Form extends Mage_Core_Block_Template
{
	/**
	 * Get the AJAX upload endpoint URL
	 *
	 * @return string
	 */
	public function getUploadUrl()
	{
		if ($this->getIsAdmin()) {
			return $this->getUrl('adminhtml/documentupload/upload');
		}
		return $this->getUrl('xfe_documentupload/upload/upload');
	}

	/**
	 * Get the AJAX endpoint URL for fetching document types by carrier
	 *
	 * @return string
	 */
	public function getDocumentTypesUrl()
	{
		if ($this->getIsAdmin()) {
			return $this->getUrl('adminhtml/documentupload/documentTypes');
		}
		return $this->getUrl('xfe_documentupload/upload/documentTypes');
	}

	/**
	 * Get the AJAX endpoint URL for fetching accounts by carrier
	 *
	 * @return string
	 */
	public function getAccountsUrl()
	{
		if ($this->getIsAdmin()) {
			return $this->getUrl('adminhtml/documentupload/accounts');
		}
		return '';
	}

	/**
	 * Get enabled carrier adapters
	 *
	 * @return XFE_DocumentUpload_Model_Carrier_Interface[]
	 */
	public function getEnabledCarriers()
	{
		return Mage::helper('xfe_documentupload')->getEnabledCarriers();
	}

	/**
	 * Get carrier options for select dropdown
	 *
	 * @return array Each element: ['value' => code, 'label' => name]
	 */
	public function getCarrierOptions()
	{
		return Mage::helper('xfe_documentupload')->getCarrierOptions();
	}

	/**
	 * Get document type options for a specific carrier
	 *
	 * @param string $carrierCode
	 * @return array Associative array: code => label
	 */
	public function getDocumentTypeOptions($carrierCode)
	{
		return Mage::helper('xfe_documentupload')->getDocumentTypeOptions($carrierCode);
	}

	/**
	 * Get all document types for all enabled carriers as JSON string
	 *
	 * @return string JSON object
	 */
	public function getAllDocumentTypesJson()
	{
		$types = Mage::helper('xfe_documentupload')->getAllDocumentTypesByCarrier();
		return Mage::helper('core')->jsonEncode($types);
	}

	/**
	 * Get maximum file size in bytes
	 *
	 * @return int
	 */
	public function getMaxFileSize()
	{
		return Mage::helper('xfe_documentupload')->getMaxFileSize();
	}

	/**
	 * Get accepted file extensions for HTML accept attribute
	 *
	 * @return string e.g. '.pdf,.jpg,.jpeg,.png,.tiff,.tif'
	 */
	public function getAcceptedExtensions()
	{
		return Mage::helper('xfe_documentupload')->getAcceptedExtensions();
	}

	/**
	 * Get maximum file size in KB for display
	 *
	 * @return int
	 */
	public function getMaxFileSizeKb()
	{
		return (int)($this->getMaxFileSize() / 1024);
	}
}
