<?php

/**
 * Admin Document Upload Controller
 *
 * Provides admin-side document upload functionality.
 * Accessible via Sales > Document Upload menu or as a popup from other admin pages.
 *
 * Routes:
 *   GET  adminhtml/documentupload/index          — Render admin upload page
 *   GET  adminhtml/documentupload/popup           — Render upload form for popup (no chrome)
 *   POST adminhtml/documentupload/upload          — AJAX upload endpoint
 *   GET  adminhtml/documentupload/documentTypes   — AJAX get document types
 *   GET  adminhtml/documentupload/accounts        — AJAX get accounts for a carrier
 *
 * @category   Community
 * @package    XFE_DocumentUpload
 */
class XFE_DocumentUpload_Adminhtml_DocumentuploadController extends Mage_Adminhtml_Controller_Action
{
	/**
	 * ACL check
	 *
	 * @return bool
	 */
	protected function _isAllowed()
	{
		return true;
	}

	/**
	 * Render the admin upload page
	 */
	public function indexAction()
	{
		$this->loadLayout();
		$this->_setActiveMenu('sales/xfe_documentupload');
		$this->_title(Mage::helper('xfe_documentupload')->__('Document Upload'));
		$this->renderLayout();
	}

	/**
	 * Render upload form for popup/lightbox (no admin chrome)
	 *
	 * Used by the popup JS widget to load the form asynchronously
	 * into a modal dialog.
	 */
	public function popupAction()
	{
		$this->loadLayout('xfe_documentupload_popup');
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
	 *   - account_index (int, optional, default=0)
	 */
	public function uploadAction()
	{
		$this->getResponse()->setHeader('Content-Type', 'application/json');

		if (!$this->getRequest()->isPost()) {
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
				'success' => false,
				'errors'  => array(Mage::helper('xfe_documentupload')->__('Invalid request method.')),
			)));
			return;
		}

		$helper = Mage::helper('xfe_documentupload');

		$carrierCode      = $this->getRequest()->getPost('carrier_code', '');
		$parcelNumber     = trim($this->getRequest()->getPost('parcel_number', ''));
		$documentType     = $this->getRequest()->getPost('document_type', '');
		$parcelNumberList = trim($this->getRequest()->getPost('parcel_number_list', ''));
		$accountIndex     = (int)$this->getRequest()->getPost('account_index', 0);

		// Validate required fields
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

		// Get uploaded file
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

		// Get carrier adapter
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

		// Validate file
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

		// Upload to carrier API
		$result = $adapter->upload(array(
			'parcel_number'      => $parcelNumber,
			'document_type'      => $documentType,
			'file_path'          => $file['tmp_name'],
			'filename'           => $file['name'],
			'parcel_number_list' => $parcelNumberList,
			'account_index'      => $accountIndex,
		));

		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
	}

	/**
	 * AJAX endpoint: get document types for a carrier
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

	/**
	 * AJAX endpoint: get accounts list for a carrier
	 *
	 * Returns available accounts with their title and index.
	 * Used by the popup to populate the account selector.
	 */
	public function accountsAction()
	{
		$this->getResponse()->setHeader('Content-Type', 'application/json');

		$carrierCode = $this->getRequest()->getParam('carrier_code', '');
		if (empty($carrierCode)) {
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
				'accounts' => array(),
			)));
			return;
		}

		$adapter = Mage::helper('xfe_documentupload')->getCarrierAdapter($carrierCode);
		$accounts = array();
		if ($adapter) {
			$rawAccounts = $adapter->getAccounts();
			foreach ($rawAccounts as $idx => $acc) {
				$accounts[] = array(
					'index' => $idx,
					'title' => isset($acc['title']) && $acc['title'] !== ''
						? $acc['title']
						: ($idx + 1) . '. ' . (isset($acc['account_number']) ? $acc['account_number'] : 'N/A'),
				);
			}
		}

		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
			'accounts' => $accounts,
		)));
	}
}
