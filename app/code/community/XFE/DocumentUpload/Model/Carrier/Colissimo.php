<?php

/**
 * Colissimo Carrier Adapter
 *
 * Implements document upload via the Colissimo API Documents REST API.
 * Endpoint: POST https://ws.colissimo.fr/api-document/rest/storedocument
 *
 * Authentication is sent in HTTP headers (login+password OR apiKey).
 * Request body is multipart/form-data with accountNumber, parcelNumber,
 * documentType, file, filename, and optional parcelNumberList.
 *
 * @category   Community
 * @package    XFE_DocumentUpload
 * @see        DocTechnique-WS-Documents_EN.pdf
 */
class XFE_DocumentUpload_Model_Carrier_Colissimo
extends XFE_DocumentUpload_Model_Carrier_Abstract
{
	/** @var string Colissimo API base URL */
	const API_BASE_URL = 'https://ws.colissimo.fr/api-document';

	/** @var string storedocument endpoint path */
	const STORE_DOCUMENT_PATH = '/rest/storedocument';

	/** @var string Config path prefix */
	protected $_configPathPrefix = 'xfe_documentupload/colissimo';

	/**
	 * Get carrier code
	 *
	 * @return string
	 */
	public function getCarrierCode()
	{
		return 'colissimo';
	}

	/**
	 * Get carrier display name
	 *
	 * @return string
	 */
	public function getCarrierName()
	{
		return 'Colissimo';
	}

	/**
	 * Get supported document types for Colissimo
	 *
	 * These are the functional nature codes accepted by the storedocument API
	 * for overseas parcels and FTD/DDP option parcels.
	 *
	 * @return array Associative array: code => label
	 */
	public function getDocumentTypes()
	{
		$helper = Mage::helper('xfe_documentupload');
		return array(
			'CN23'                  => $helper->__('CN23 - Customs Declaration'),
			'CERTIFICATE_OF_ORIGIN' => $helper->__('Certificate of Origin'),
			'EXPORT_LICENSE'        => $helper->__('Export License'),
			'COMMERCIAL_INVOICE'    => $helper->__('Commercial Invoice'),
			'OTHER'                 => $helper->__('Other'),
		);
	}

	/**
	 * Upload a document to the Colissimo API
	 *
	 * @param array $params Keys:
	 *   - parcel_number (string, required): Parcel tracking number
	 *   - document_type (string, required): One of the supported document type codes
	 *   - file_path (string, required): Absolute path to the file on disk
	 *   - filename (string, required): Original filename
	 *   - parcel_number_list (string, optional): Comma-separated list for multi-parcel
	 *   - account_index (int, optional): Index of the account to use (0-based). Defaults to 0.
	 * @return array Keys: success (bool), document_id (string), errors (array),
	 *               error_code (string), error_label (string)
	 */
	public function upload(array $params)
	{
		$helper = Mage::helper('xfe_documentupload');

		// --- Validate required params ---
		if (empty($params['parcel_number'])) {
			return $this->_errorResult(array($helper->__('Parcel number is required.')));
		}
		if (empty($params['document_type'])) {
			return $this->_errorResult(array($helper->__('Document type is required.')));
		}
		if (empty($params['file_path']) || !is_file($params['file_path'])) {
			return $this->_errorResult(array($helper->__('File not found.')));
		}
		if (empty($params['filename'])) {
			return $this->_errorResult(array($helper->__('Filename is required.')));
		}

		// --- Read credentials from system config (multi-account support) ---
		$accounts  = $this->getAccounts();
		$accIdx    = isset($params['account_index']) ? (int)$params['account_index'] : 0;

		if (empty($accounts)) {
			return $this->_errorResult(array($helper->__('No Colissimo accounts configured.')));
		}
		if (!isset($accounts[$accIdx])) {
			return $this->_errorResult(array(
				$helper->__('Account index %s not found. Configured accounts: %s', $accIdx, count($accounts))
			));
		}

		$account = $accounts[$accIdx];
		$apiLogin      = isset($account['login']) ? $account['login'] : '';
		$apiPassword   = isset($account['password']) ? $account['password'] : '';
		$apiKey        = isset($account['api_key']) ? $account['api_key'] : '';
		$accountNumber = isset($account['account_number']) ? $account['account_number'] : '';

		if (empty($accountNumber)) {
			return $this->_errorResult(array($helper->__('Colissimo account number is not configured for the selected account.')));
		}

		// --- Build authentication headers ---
		// Two modes: login+password OR apiKey (mutually exclusive)
		$headers = array();
		if (!empty($apiKey)) {
			$headers['apiKey'] = $apiKey;
		} elseif (!empty($apiLogin) && !empty($apiPassword)) {
			$headers['login']    = $apiLogin;
			$headers['password'] = $apiPassword;
		} else {
			return $this->_errorResult(array($helper->__('Colissimo API credentials are not configured for the selected account.')));
		}

		// --- Build form fields ---
		$fields = array(
			'accountNumber' => $accountNumber,
			'parcelNumber'  => $params['parcel_number'],
			'documentType'  => $params['document_type'],
			'filename'      => $params['filename'],
		);

		// Optional: multi-parcel list
		if (!empty($params['parcel_number_list'])) {
			$fields['parcelNumberList'] = $params['parcel_number_list'];
		}

		// --- Build file array ---
		$mimeType = 'application/octet-stream';
		if (!empty($params['file_path']) && function_exists('mime_content_type')) {
			$detected = mime_content_type($params['file_path']);
			if ($detected) {
				$mimeType = $detected;
			}
		}

		$files = array(
			'file' => array(
				'path' => $params['file_path'],
				'name' => $params['filename'],
				'type' => $mimeType,
			),
		);

		// --- Send HTTP POST ---
		$url = self::API_BASE_URL . self::STORE_DOCUMENT_PATH;
		$result = $this->_httpPostMultipart($url, $headers, $fields, $files);

		// --- Handle cURL error ---
		if ($result['error']) {
			return $this->_errorResult(
				array($helper->__('Connection error: %s', $result['error']))
			);
		}

		// --- Parse API response ---
		$responseBody = $result['body'];
		$responseHttp = $result['http_code'];
		$responseData = json_decode($responseBody, true);

		// HTTP-level error
		if ($responseHttp >= 400) {
			$errorMessage = $helper->__('HTTP error %s', $responseHttp);
			if (isset($responseData['errorLabel'])) {
				$errorMessage = $responseData['errorLabel'];
			}
			$errorCode  = isset($responseData['errorCode']) ? $responseData['errorCode'] : '';
			$errorLabel = isset($responseData['errorLabel']) ? $responseData['errorLabel'] : '';
			$errors     = array($errorMessage);

			// Append detailed errors if present
			if (isset($responseData['errors']) && is_array($responseData['errors'])) {
				foreach ($responseData['errors'] as $detail) {
					if (isset($detail['message'])) {
						$errors[] = $detail['message'];
					}
				}
			}

			return $this->_errorResult($errors, $errorCode, $errorLabel);
		}

		// API-level error (errorCode != "000")
		if (!isset($responseData['errorCode']) || $responseData['errorCode'] !== '000') {
			$errorCode  = isset($responseData['errorCode']) ? $responseData['errorCode'] : '';
			$errorLabel = isset($responseData['errorLabel']) ? $responseData['errorLabel'] : '';
			$errors     = array($errorLabel ?: $helper->__('Unknown API error'));

			if (isset($responseData['errors']) && is_array($responseData['errors'])) {
				foreach ($responseData['errors'] as $detail) {
					if (isset($detail['message'])) {
						$errors[] = $detail['message'];
					}
				}
			}

			return $this->_errorResult($errors, $errorCode, $errorLabel);
		}

		// --- Success ---
		$documentId = isset($responseData['documentId']) ? $responseData['documentId'] : '';
		return $this->_successResult($documentId);
	}

	/**
	 * 旁路方法：通过 XML 注入从 XFE_Carrier 读取账号列表。
	 *
	 * 与 getAccounts() 共存。getAccounts() 仍读 system config（旧路径），
	 * 本方法走 XML 注入（新路径）。两条路径行为可对照。
	 *
	 * 未来数据迁移时，把 getAccounts() 内部实现改为调用本方法即可。
	 *
	 * 单向依赖：本方法仅依赖 XFE_Injection 公共模块，
	 * 不出现 use XFE_Carrier_* / new XFE_Carrier_*，实现 Carrier ↔ DocumentUpload 零 PHP 类型耦合。
	 *
	 * @param array $contextValues 业务上下文（可选，当前未使用，仅保持签名一致）
	 * @return int[] account_id 列表
	 */
	public function getAccountsViaInjection(array $contextValues = array())
	{
		$injCtx = new XFE_Injection_Domain_InjectionContext(array(
			'carrierCode'   => $this->getCarrierCode(),
			'contextValues' => $contextValues,
		));
		$result = XFE_Injection_Model_Runner::trigger(
			'hook_documentupload_resolve_accounts',
			$injCtx
		);
		$first = $result->first();
		return is_array($first) ? $first : array();
	}
}
