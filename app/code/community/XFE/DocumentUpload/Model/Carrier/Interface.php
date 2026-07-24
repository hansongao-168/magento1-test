<?php

/**
 * Carrier Adapter Interface
 *
 * Contract that all carrier document upload adapters must implement.
 *
 * @category   Community
 * @package    XFE_DocumentUpload
 */
interface XFE_DocumentUpload_Model_Carrier_Interface
{
	/**
	 * Upload a document to the carrier API
	 *
	 * @param array $params Keys: parcel_number, document_type, file_path, filename, parcel_number_list (optional)
	 * @return array Keys: success (bool), document_id (string), errors (array)
	 */
	public function upload(array $params);

	/**
	 * Get supported document types for this carrier
	 *
	 * @return array Associative array: code => label
	 */
	public function getDocumentTypes();

	/**
	 * Get carrier code identifier
	 *
	 * @return string e.g. 'colissimo'
	 */
	public function getCarrierCode();

	/**
	 * Get carrier display name
	 *
	 * @return string e.g. 'Colissimo'
	 */
	public function getCarrierName();

	/**
	 * Check if this carrier is enabled in system config
	 *
	 * @return bool
	 */
	public function isEnabled();

	/**
	 * Validate a file for this carrier
	 *
	 * @param array $fileInfo Keys: name, size, type (mime)
	 * @return array Keys: valid (bool), errors (array of strings)
	 */
	public function validateFile(array $fileInfo);
}
