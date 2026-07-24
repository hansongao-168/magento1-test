<?php

/**
 * Frontend Upload Controller
 *
 * Handles the upload page rendering and AJAX document upload requests.
 *
 * Routes:
 *   GET  /xfe_documentupload/upload/index         — Render upload page
 *   POST /xfe_documentupload/upload/upload         — AJAX upload endpoint
 *   GET  /xfe_documentupload/upload/documentTypes  — AJAX get document types for carrier
 *
 * @category   Community
 * @package    XFE_DocumentUpload
 */
class XFE_DocumentUpload_UploadController extends Mage_Core_Controller_Front_Action
{
	/**
	 * Render the upload page
	 */
	public function indexAction()
	{
		$this->loadLayout();
		$this->_initLayoutMessages('customer/session');
		$this->renderLayout();
	}

	/**
	 * AJAX endpoint: upload a document to the selected carrier
	 *
	 * Expects multipart/form-data POST with fields:
	 *   - carrier_code (string)
	 *   - parcel_number (string)
	 *   - document_type (string)
	 *   - file (uploaded file)
	 *   - parcel_number_list (string, optional)
	 *
	 * Returns JSON:
	 *   Success: {"success": true, "document_id": "..."}
	 *   Error:   {"success": false, "errors": [...], "error_code": "...", "error_label": "..."}
	 */
	public function uploadAction()
	{
		$this->getResponse()->setHeader('Content-Type', 'application/json');

		// Only accept POST
		if (!$this->getRequest()->isPost()) {
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
				'success' => false,
				'errors'  => array(Mage::helper('xfe_documentupload')->__('Invalid request method.')),
			)));
			return;
		}

		$helper = Mage::helper('xfe_documentupload');

		// --- Get POST params ---
		$carrierCode     = $this->getRequest()->getPost('carrier_code', '');
		$parcelNumber    = trim($this->getRequest()->getPost('parcel_number', ''));
		$documentType    = $this->getRequest()->getPost('document_type', '');
		$parcelNumberList = trim($this->getRequest()->getPost('parcel_number_list', ''));

		// --- Validate required fields ---
		$errors = array();
		if (empty($carrierCode)) {
			$errors[] = $helper->__('Please select a carrier.');
		}
		if (empty($parcelNumber)) {
			$errors[] = $helper->__('Parcel number is required.');
		}
		if (empty($documentType)) {
			$errors[] = $helper->__('Document type is required.');
		}

		// --- Get uploaded file ---
		$file = isset($_FILES['file']) ? $_FILES['file'] : null;
		if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
			$uploadErrors = array(
				UPLOAD_ERR_INI_SIZE   => $helper->__('File exceeds server upload limit.'),
				UPLOAD_ERR_FORM_SIZE  => $helper->__('File exceeds form limit.'),
				UPLOAD_ERR_PARTIAL    => $helper->__('File was only partially uploaded.'),
				UPLOAD_ERR_NO_FILE    => $helper->__('No file was uploaded.'),
				UPLOAD_ERR_NO_TMP_DIR => $helper->__('Server temporary directory missing.'),
				UPLOAD_ERR_CANT_WRITE => $helper->__('Failed to write file to disk.'),
			);
			$errCode = $file ? $file['error'] : UPLOAD_ERR_NO_FILE;
			$errors[] = isset($uploadErrors[$errCode])
				? $uploadErrors[$errCode]
				: $helper->__('Unknown upload error.');
		}

		if (!empty($errors)) {
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
				'success' => false,
				'errors'  => $errors,
			)));
			return;
		}

		// --- Get carrier adapter ---
		$adapter = $helper->getCarrierAdapter($carrierCode);
		if (!$adapter) {
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
				'success' => false,
				'errors'  => array($helper->__('Unknown carrier: %s', $carrierCode)),
			)));
			return;
		}

		if (!$adapter->isEnabled()) {
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
				'success' => false,
				'errors'  => array($helper->__('Carrier %s is not enabled.', $adapter->getCarrierName())),
			)));
			return;
		}

		// --- Validate file via adapter ---
		$fileValidation = $adapter->validateFile(array(
			'name' => $file['name'],
			'size' => $file['size'],
			'type' => $file['type'],
		));

		if (!$fileValidation['valid']) {
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
				'success' => false,
				'errors'  => $fileValidation['errors'],
			)));
			return;
		}

		// --- Upload to carrier API ---
		$result = $adapter->upload(array(
			'parcel_number'      => $parcelNumber,
			'document_type'      => $documentType,
			'file_path'          => $file['tmp_name'],
			'filename'           => $file['name'],
			'parcel_number_list' => $parcelNumberList,
		));

		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
	}

	/**
	 * AJAX endpoint: get document types for a carrier
	 *
	 * Expects GET param: carrier_code
	 * Returns JSON: { "types": { "CODE": "Label", ... } }
	 */
	public function documentTypesAction()
	{
		$this->getResponse()->setHeader('Content-Type', 'application/json');

		$carrierCode = $this->getRequest()->getParam('carrier_code', '');
		if (empty($carrierCode)) {
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
				'types' => array(),
			)));
			return;
		}

		$types = Mage::helper('xfe_documentupload')->getDocumentTypeOptions($carrierCode);
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
			'types' => $types,
		)));
	}
}
