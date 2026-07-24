<?php

/**
 * XFE DocumentUpload Helper
 *
 * Provides carrier adapter registry, file validation utilities,
 * and data accessors used by blocks and controllers.
 *
 * @category   Community
 * @package    XFE_DocumentUpload
 */
class XFE_DocumentUpload_Helper_Data extends Mage_Core_Helper_Abstract
{
	/** @var XFE_DocumentUpload_Model_Carrier_Interface[]|null Cached carrier adapters */
	protected $_carrierAdapters = null;

	/**
	 * Get all registered carrier adapter instances
	 *
	 * Reads carrier registrations from config.xml under
	 * <default><xfe_documentupload><carriers><colissimo><model>...
	 *
	 * @return XFE_DocumentUpload_Model_Carrier_Interface[] Keyed by carrier code
	 */
	public function getCarrierAdapters()
	{
		if ($this->_carrierAdapters === null) {
			$this->_carrierAdapters = array();
			$carriers = Mage::getConfig()->getNode('default/xfe_documentupload/carriers');
			if ($carriers) {
				foreach ($carriers->children() as $code => $config) {
					$modelAlias = (string)$config->model;
					if ($modelAlias) {
						$model = Mage::getModel($modelAlias);
						if ($model instanceof XFE_DocumentUpload_Model_Carrier_Interface) {
							$this->_carrierAdapters[$code] = $model;
						}
					}
				}
			}
		}
		return $this->_carrierAdapters;
	}

	/**
	 * Get only enabled carrier adapters
	 *
	 * @return XFE_DocumentUpload_Model_Carrier_Interface[]
	 */
	public function getEnabledCarriers()
	{
		$enabled = array();
		foreach ($this->getCarrierAdapters() as $code => $adapter) {
			if ($adapter->isEnabled()) {
				$enabled[$code] = $adapter;
			}
		}
		return $enabled;
	}

	/**
	 * Get a specific carrier adapter by code
	 *
	 * @param string $code Carrier code, e.g. 'colissimo'
	 * @return XFE_DocumentUpload_Model_Carrier_Interface|null
	 */
	public function getCarrierAdapter($code)
	{
		$adapters = $this->getCarrierAdapters();
		return isset($adapters[$code]) ? $adapters[$code] : null;
	}

	/**
	 * Get document type options for a specific carrier
	 *
	 * @param string $carrierCode
	 * @return array Associative array: code => label
	 */
	public function getDocumentTypeOptions($carrierCode)
	{
		$adapter = $this->getCarrierAdapter($carrierCode);
		if ($adapter) {
			return $adapter->getDocumentTypes();
		}
		return array();
	}

	/**
	 * Get all accepted MIME types
	 *
	 * @return array
	 */
	public function getAcceptedMimeTypes()
	{
		return array(
			'application/pdf',
			'image/jpeg',
			'image/png',
			'image/tiff',
		);
	}

	/**
	 * Get maximum file size in bytes (500 KB)
	 *
	 * @return int
	 */
	public function getMaxFileSize()
	{
		return 512000;
	}

	/**
	 * Get accepted file extensions as a comma-separated string
	 * suitable for the HTML accept attribute
	 *
	 * @return string e.g. '.pdf,.jpg,.jpeg,.png,.tiff,.tif'
	 */
	public function getAcceptedExtensions()
	{
		return '.pdf,.jpg,.jpeg,.png,.tiff,.tif';
	}

	/**
	 * Build carrier options array for a select dropdown
	 *
	 * @return array Each element: ['value' => code, 'label' => name]
	 */
	public function getCarrierOptions()
	{
		$options = array();
		foreach ($this->getEnabledCarriers() as $code => $adapter) {
			$options[] = array(
				'value' => $code,
				'label' => $adapter->getCarrierName(),
			);
		}
		return $options;
	}

	/**
	 * Get all document types for all enabled carriers as a JSON-encodable array
	 *
	 * Structure: { carrier_code: { type_code: label, ... }, ... }
	 *
	 * @return array
	 */
	public function getAllDocumentTypesByCarrier()
	{
		$result = array();
		foreach ($this->getEnabledCarriers() as $code => $adapter) {
			$result[$code] = $adapter->getDocumentTypes();
		}
		return $result;
	}
}
