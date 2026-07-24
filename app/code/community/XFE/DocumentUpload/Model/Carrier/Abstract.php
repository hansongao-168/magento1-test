<?php

/**
 * Abstract Carrier Adapter
 *
 * Provides shared logic for all carrier adapters:
 * - HTTP multipart/form-data requests via cURL
 * - File validation (format, MIME type, size)
 * - Result formatting helpers
 *
 * @category   Community
 * @package    XFE_DocumentUpload
 */
abstract class XFE_DocumentUpload_Model_Carrier_Abstract implements XFE_DocumentUpload_Model_Carrier_Interface
{
	/** @var string Config path prefix, e.g. 'xfe_documentupload/colissimo' */
	protected $_configPathPrefix = '';

	/** @var int Max file size in bytes (500 KB) */
	protected $_maxFileSize = 512000;

	/** @var array Accepted MIME types */
	protected $_acceptedMimeTypes = array(
		'application/pdf',
		'image/jpeg',
		'image/png',
		'image/tiff',
	);

	/** @var array Accepted file extensions (lowercase) */
	protected $_acceptedExtensions = array(
		'pdf',
		'jpg',
		'jpeg',
		'png',
		'tiff',
		'tif',
	);

	/**
	 * Get a config value for this carrier from system config
	 *
	 * @param string $field
	 * @return string
	 */
	protected function _getConfig($field)
	{
		return (string)Mage::getStoreConfig($this->_configPathPrefix . '/' . $field);
	}

	/**
	 * Get a config value for this carrier as JSON-decoded array
	 *
	 * @param string $field
	 * @return array|null
	 */
	protected function _getConfigJson($field)
	{
		$value = Mage::getStoreConfig($this->_configPathPrefix . '/' . $field);
		if (empty($value)) {
			return null;
		}
		$decoded = @json_decode($value, true);
		return is_array($decoded) ? $decoded : null;
	}

	/**
	 * Get all accounts configured for this carrier
	 *
	 * @return array Each element: {title, login, password, api_key, account_number}
	 */
	public function getAccounts()
	{
		$accounts = $this->_getConfigJson('accounts_json');
		if (!$accounts) {
			return array();
		}

		// The config value has password/api_key as plaintext (decrypted by backend model on load)
		// or they may still be encrypted if the config was set programmatically.
		// Try to decrypt them on-the-fly.
		$encryption = Mage::getModel('core/encryption');
		foreach ($accounts as &$account) {
			if (is_array($account)) {
				if (!empty($account['password']) && $this->_isProbablyEncrypted($account['password'])) {
					$account['password'] = $encryption->decrypt($account['password']);
				}
				if (!empty($account['api_key']) && $this->_isProbablyEncrypted($account['api_key'])) {
					$account['api_key'] = $encryption->decrypt($account['api_key']);
				}
			}
		}

		return $accounts;
	}

	/**
	 * Check if a string looks like an encrypted Magento config value
	 *
	 * @param string $value
	 * @return bool
	 */
	protected function _isProbablyEncrypted($value)
	{
		return (bool)preg_match('/^[a-zA-Z0-9\/+]+=*$/', $value) && strlen($value) > 20;
	}

	/**
	 * Check if carrier is enabled in system config
	 *
	 * @return bool
	 */
	public function isEnabled()
	{
		return Mage::getStoreConfigFlag($this->_configPathPrefix . '/enabled');
	}

	/**
	 * Validate file format, MIME type and size
	 *
	 * @param array $fileInfo Keys: name (string), size (int), type (string, MIME)
	 * @return array Keys: valid (bool), errors (array of string)
	 */
	public function validateFile(array $fileInfo)
	{
		$errors = array();
		$helper = Mage::helper('xfe_documentupload');

		// Check file extension
		$ext = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
		if (!in_array($ext, $this->_acceptedExtensions)) {
			$errors[] = $helper->__(
				'Invalid file format. Accepted formats: %s',
				implode(', ', $this->_acceptedExtensions)
			);
		}

		// Check MIME type if provided
		if (!empty($fileInfo['type']) && !in_array($fileInfo['type'], $this->_acceptedMimeTypes)) {
			$errors[] = $helper->__('Invalid MIME type: %s', $fileInfo['type']);
		}

		// Check file size upper limit
		if (isset($fileInfo['size']) && $fileInfo['size'] > $this->_maxFileSize) {
			$maxKb = (int)($this->_maxFileSize / 1024);
			$errors[] = $helper->__('File too large. Maximum size: %s KB', $maxKb);
		}

		// Check file is not empty
		if (isset($fileInfo['size']) && $fileInfo['size'] <= 0) {
			$errors[] = $helper->__('File is empty.');
		}

		return array(
			'valid'  => empty($errors),
			'errors' => $errors,
		);
	}

	/**
	 * Send an HTTP POST request with multipart/form-data body
	 *
	 * Builds the multipart body manually to support both form fields and file uploads.
	 *
	 * @param string $url      Full endpoint URL
	 * @param array  $headers  Associative array of header name => value
	 * @param array  $fields   Associative array of form field name => value
	 * @param array  $files    Associative array of field_name => ['path'=>..., 'name'=>..., 'type'=>...]
	 * @return array           Keys: http_code (int), body (string), error (string|null)
	 */
	protected function _httpPostMultipart($url, array $headers, array $fields, array $files = array())
	{
		$ch = curl_init();

		// Build multipart body
		$boundary = '----XFEUpload' . md5(microtime());
		$body = '';

		// Append form fields
		foreach ($fields as $name => $value) {
			$body .= "--{$boundary}\r\n";
			$body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
			$body .= "{$value}\r\n";
		}

		// Append files
		foreach ($files as $name => $fileInfo) {
			$filePath = $fileInfo['path'];
			$fileName = $fileInfo['name'];
			$fileType = isset($fileInfo['type']) ? $fileInfo['type'] : 'application/octet-stream';
			$fileData = file_get_contents($filePath);

			$body .= "--{$boundary}\r\n";
			$body .= "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$fileName}\"\r\n";
			$body .= "Content-Type: {$fileType}\r\n\r\n";
			$body .= "{$fileData}\r\n";
		}

		$body .= "--{$boundary}--\r\n";

		// Build cURL headers
		$curlHeaders = array('Content-Type: multipart/form-data; boundary=' . $boundary);
		foreach ($headers as $key => $value) {
			$curlHeaders[] = "{$key}: {$value}";
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$response  = curl_exec($ch);
		$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		if ($curlError) {
			return array(
				'http_code' => 0,
				'body'      => '',
				'error'     => $curlError,
			);
		}

		return array(
			'http_code' => $httpCode,
			'body'      => $response,
			'error'     => null,
		);
	}

	/**
	 * Build a success result array
	 *
	 * @param string $documentId The document ID returned by the carrier API
	 * @return array
	 */
	protected function _successResult($documentId)
	{
		return array(
			'success'     => true,
			'document_id' => $documentId,
			'errors'      => array(),
		);
	}

	/**
	 * Build an error result array
	 *
	 * @param array  $errors     Array of error message strings
	 * @param string $errorCode  Global error code from the API
	 * @param string $errorLabel Global error label from the API
	 * @return array
	 */
	protected function _errorResult(array $errors, $errorCode = '', $errorLabel = '')
	{
		return array(
			'success'     => false,
			'document_id' => '',
			'errors'      => $errors,
			'error_code'  => $errorCode,
			'error_label' => $errorLabel,
		);
	}
}
