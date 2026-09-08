<?php

/**
 * Carrier Admin Controller
 *
 * Orchestrator for the carrier edit-page form. The controller is the
 * sole place that:
 *   - persists the carrier entity
 *   - delegates logo / account / rule persistence to dedicated services
 *
 * Service call graph (unidirectional):
 *
 *   Controller
 *      +-- Carrier model            (basic entity CRUD)
 *      +-- LogoService              (upload, delete)
 *      +-- AccountService           (batch upsert + delete)
 *      +-- RuleService              (rules + nested condition tree)
 *
 * No service references another service and none imports the Carrier model.
 *
 * All user-facing strings use ASCII placeholders to keep the source
 * encoding-safe; the Helper translates them via __() at render time.
 */
class XFE_Carrier_Adminhtml_CarrierController extends Mage_Adminhtml_Controller_Action
{
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('admin/system/xfe_carrier');
    }

    protected function _initAction()
    {
        $helper = Mage::helper('xfe_carrier');
        $this->loadLayout()
            ->_setActiveMenu('system/xfe_carrier')
            ->_addBreadcrumb(
                $helper->__('Carrier Management'),
                $helper->__('Carrier Management')
            );
        return $this;
    }

    public function indexAction()
    {
        $this->_initAction()
            ->renderLayout();
    }

    public function gridAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('xfe_carrier/adminhtml_carrier_grid')->toHtml()
        );
    }

    /**
     * Bulk-import landing page. Renders the upload form + a link to the
     * CSV template. The form posts to importPostAction().
     */
    public function importAction()
    {
        $this->_initAction()
            ->_title($this->__('批量导入承运商'))
            ->_addBreadcrumb(
                Mage::helper('xfe_carrier')->__('批量导入承运商'),
                Mage::helper('xfe_carrier')->__('批量导入承运商')
            )
            ->renderLayout();
    }

    /**
     * Handle the upload: validate form_key, hand the file to the Importer
     * service, store the Result in the registry for the result page.
     */
    public function importPostAction()
    {
        $helper = Mage::helper('xfe_carrier');
        if (!$this->_validateFormKey()) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Invalid form key, please reload the page.')
            );
            return $this->_redirect('*/*/import');
        }

        $result = XFE_Carrier_Model_Service_Registry::importer()->importUpload(
            isset($_FILES['carrier_csv']) ? $_FILES['carrier_csv'] : array()
        );

        Mage::register('xfe_carrier_import_result', $result);

        // Human-readable flash messages.
        $session = Mage::getSingleton('adminhtml/session');
        if ($result->created > 0) {
            $session->addSuccess(
                $helper->__('%d carrier(s) created.', $result->created)
            );
        }
        if ($result->updated > 0) {
            $session->addSuccess(
                $helper->__('%d carrier(s) updated.', $result->updated)
            );
        }
        if ($result->skipped > 0) {
            $session->addWarning(
                $helper->__('%d row(s) skipped (see below).', $result->skipped)
            );
        }
        if ($result->created + $result->updated + $result->skipped === 0
            && !$result->hasErrors()
        ) {
            $session->addNotice($helper->__('Uploaded CSV contained no data rows.'));
        }
        return $this->_redirect('*/*/importResult');
    }

    /**
     * Result page: shows the counters + a per-row error table from the
     * last importPostAction(). If the registry is empty, redirect back
     * to the import page.
     */
    public function importResultAction()
    {
        /** @var XFE_Carrier_Model_Service_Importer_Result|null $result */
        $result = Mage::registry('xfe_carrier_import_result');
        if (!$result) {
            return $this->_redirect('*/*/import');
        }
        $this->_initAction()
            ->_title($this->__('批量导入结果'))
            ->_addBreadcrumb(
                Mage::helper('xfe_carrier')->__('批量导入结果'),
                Mage::helper('xfe_carrier')->__('批量导入结果')
            )
            ->renderLayout();
    }

    /**
     * Stream a starter CSV template so users know what columns are
     * accepted. Triggered by the "下载模板" link on the import page.
     */
    public function downloadTemplateAction()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xfe_carrier_import_');
        if (!XFE_Carrier_Model_Service_Registry::importer()->writeTemplate($tmpFile)) {
            @unlink($tmpFile);
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('xfe_carrier')->__('Failed to build the CSV template.')
            );
            return $this->_redirect('*/*/import');
        }

        $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Content-Type', 'text/csv; charset=utf-8', true)
            ->setHeader(
                'Content-Disposition',
                'attachment; filename="carrier_import_template.csv"',
                true
            )
            ->setHeader('Content-Length', (string)filesize($tmpFile), true)
            ->setBody(file_get_contents($tmpFile));
        @unlink($tmpFile);
        return $this;
    }

    public function newAction()
    {
        $this->_forward('edit');
    }

    public function editAction()
    {
        $helper = Mage::helper('xfe_carrier');
        $id     = $this->getRequest()->getParam('id');
        $storeId = (int)$this->getRequest()->getParam('store', Mage_Core_Model_App::ADMIN_STORE_ID);
        $model  = Mage::getModel('xfe_carrier/carrier');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    $helper->__('The carrier does not exist.')
                );
                return $this->_redirect('*/*/');
            }
            // Pin to the requested store view so the General tab shows that scope's translations.
            $model->setStoreId($storeId);
        }

        Mage::register('xfe_carrier_data', $model);
        Mage::register('xfe_carrier_store', $storeId);

        $this->_initAction()
            ->_addBreadcrumb(
                $id ? $helper->__('Edit Carrier') : $helper->__('New Carrier'),
                $id ? $helper->__('Edit Carrier') : $helper->__('New Carrier')
            )
            ->renderLayout();
    }

    public function saveAction()
    {
        $data = $this->getRequest()->getPost();
        $id   = $this->getRequest()->getParam('id');

        if (!$data) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('xfe_carrier')->__('Cannot save: no data received.')
            );
            return $this->_redirect('*/*/');
        }

        try {
            $model  = Mage::getModel('xfe_carrier/carrier');

            if ($id) {
                $model->load($id);
                if (!$model->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('xfe_carrier')->__('The carrier does not exist.')
                    );
                    return $this->_redirect('*/*/');
                }
            }

            // Per-store translations (name / note) live in
            // xfe_carrier_translation. The form posts a hidden `store`
            // param identifying the active scope; in any non-admin scope
            // we route name / note into store_translations instead of the
            // base row. When a caller explicitly posts the parallel
            // store_translations payload (multi-store batch save), that
            // wins and is merged on top.
            $storeScope = (int)$this->getRequest()->getParam(
                'store', Mage_Core_Model_App::ADMIN_STORE_ID
            );

            $storeTranslations = array();
            if (isset($data['store_translations']) && is_array($data['store_translations'])) {
                $storeTranslations = $data['store_translations'];
                unset($data['store_translations']);
            }

            // Pull the visible name / note out of the base row when we are
            // editing a non-admin store view, so addData() does not stomp
            // on the admin-scope values; the resource model will write the
            // current scope's name / note into xfe_carrier_translation.
            if ($storeScope !== Mage_Core_Model_App::ADMIN_STORE_ID
                && $id
                && array_key_exists('name', $data)
            ) {
                $storeTranslations['name'][$storeScope] = $data['name'];
                unset($data['name']);
            }
            if ($storeScope !== Mage_Core_Model_App::ADMIN_STORE_ID
                && $id
                && array_key_exists('note', $data)
            ) {
                $storeTranslations['note'][$storeScope] = $data['note'];
                unset($data['note']);
            }

            // The tabbed edit page moves every tab body into the single
            // edit_form via varienTabs. Grid search / pagination inputs from
            // the account / ftp / rule tabs (class "no-changes") then collide
            // with the real form fields and get POSTed as arrays (e.g. name
            // appears twice). A scalar column fed an array would be coerced to
            // the literal string "Array" by _prepareDataForTable(). JS already
            // neutralizes those inputs; this is a belt-and-braces guard so a
            // scalar form value can never arrive as an array.
            $scalarFields = array('name', 'code', 'note', 'status', 'sort_order', 'shipping_company_id');
            foreach ($scalarFields as $f) {
                if (isset($data[$f]) && is_array($data[$f])) {
                    // If a scalar column somehow arrives as an array (e.g. the
                    // tabbed form posts multiple same-name inputs), keep the
                    // last value so the model is never handed an array that
                    // would be coerced to "Array".
                    $data[$f] = end($data[$f]);
                }
            }

            $model->addData($data);
            Mage::helper('xfe_carrier')->validateCarrierModules($model, $data);
            if (!empty($storeTranslations)) {
                $model->setData('store_translations', $storeTranslations);
            }
            $model->save();

            $carrierId = (int)$model->getId();
            if ($carrierId) {
                // 1.0.15+ 自定义属性 strict apply
                if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
                    $applier = new XFE_Carrier_Model_Service_Carrier_CustomAttributeApplier();
                    $applier->applyFromPost($carrierId, $data);
                }
                if (array_key_exists('accounts_data', $data)) {
                    XFE_Carrier_Model_Service_Registry::account()->saveBatch(
                        $carrierId, $data['accounts_data']
                    );
                }
                if (array_key_exists('ftp_accounts_data', $data)) {
                    XFE_Carrier_Model_Service_Registry::ftpAccount()->saveBatch(
                        $carrierId, $data['ftp_accounts_data']
                    );
                }
                if (array_key_exists('rules_data', $data)) {
                    XFE_Carrier_Model_Service_Registry::rule()->saveBatch(
                        $carrierId, $data['rules_data']
                    );
                }
            }

            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('xfe_carrier')->__('Carrier saved.')
            );

            if ($this->getRequest()->getParam('back')) {
                return $this->_redirect('*/*/edit', array(
                    'id'    => $model->getId(),
                    'store' => (int)$this->getRequest()->getParam('store', Mage_Core_Model_App::ADMIN_STORE_ID),
                ));
            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            if ($id) {
                return $this->_redirect('*/*/edit', array(
                    'id'    => $id,
                    'store' => (int)$this->getRequest()->getParam('store', Mage_Core_Model_App::ADMIN_STORE_ID),
                ));
            }
            return $this->_redirect('*/*/new');
        }

        return $this->_redirect('*/*/');
    }

    public function deleteAction()
    {
        $id     = $this->getRequest()->getParam('id');
        $storeId = (int)$this->getRequest()->getParam('store', Mage_Core_Model_App::ADMIN_STORE_ID);

        if ($id) {
            try {
                $model = Mage::getModel('xfe_carrier/carrier')->load($id);
                if (!$model->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('xfe_carrier')->__('The carrier does not exist.')
                    );
                    return $this->_redirect('*/*/');
                }

                $this->_purgeCarrierChildren($id);
                $model->delete();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('xfe_carrier')->__('Carrier deleted.')
                );
            } catch (Exception $e) {
                Mage::logException($e);
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        return $this->_redirect('*/*/');
    }

    // ====================================================================
    // Account sub-actions
    // ====================================================================

    public function editAccountAction()
    {
        $accountId = (int) $this->getRequest()->getParam('account_id');
        $carrierId = (int) $this->getRequest()->getParam('carrier_id');
        $helper    = Mage::helper('xfe_carrier');

        $account = Mage::getModel('xfe_carrier/carrier_account');
        if ($accountId) {
            $account->load($accountId);
            if (!$account->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    $helper->__('This account does not exist.')
                );
                return $this->_redirect('*/carrier/');
            }
            $carrierId = $account->getCarrierId();
        } else {
            if (!$carrierId) {
                Mage::getSingleton('adminhtml/session')->addError(
                    $helper->__('Missing carrier ID.')
                );
                return $this->_redirect('*/carrier/');
            }
            $account->setCarrierId($carrierId);
        }

        Mage::register('xfe_carrier_account_data', $account);

        $this->_initAction()
            ->_addBreadcrumb(
                $accountId ? $helper->__('Edit Account') : $helper->__('New Account'),
                $accountId ? $helper->__('Edit Account') : $helper->__('New Account')
            )
            ->renderLayout();
    }

    public function saveAccountAction()
    {
        $data   = $this->getRequest()->getPost();
        $helper = Mage::helper('xfe_carrier');

        if (!$data) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Cannot save: no data received.')
            );
            return $this->_redirect('*/carrier/');
        }

        $accountId = (int) $this->getRequest()->getParam('account_id');
        $carrierId = (int) $this->getRequest()->getParam('carrier_id');

        try {
            $account = Mage::getModel('xfe_carrier/carrier_account');
            if ($accountId) {
                $account->load($accountId);
                if (!$account->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        $helper->__('This account does not exist.')
                    );
                    return $this->_redirect('*/carrier/');
                }
                $carrierId = $account->getCarrierId();
            } else {
                if (!$carrierId) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        $helper->__('Missing carrier ID.')
                    );
                    return $this->_redirect('*/carrier/');
                }
            }

            unset($data['form_key']);

            $account->addData($data);
            $account->save();
            $accountId = (int)$account->getId();

            // 自定义字段(1.0.14+):键值对列表,以 JSON 整体保存。
            // Service 内做 diff + 事件广播,不在 Controller 写 json_encode。
            if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
                $applier = new XFE_Carrier_Model_Service_Account_CustomAttributeApplier();
                $applier->applyFromPost($accountId, $data);
            }

            // Note: since 1.0.8 rules are managed from a dedicated
            // edit page (see Carrier/Rule/Edit/Form.php) - the Account
            // Edit page no longer hosts an inline rule editor. Rules
            // created from the Account page carry account_id and are
            // listed in the rules grid on the Account page.

            Mage::getSingleton('adminhtml/session')->addSuccess(
                $helper->__('Account saved.')
            );
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            if ($this->getRequest()->getParam('carrier_id')) {
                return $this->_redirect('*/*/editAccount', array('carrier_id' => $carrierId));
            }
            return $this->_redirect('*/carrier/');
        }

        return $this->_redirect('*/carrier/edit', array('id' => $carrierId));
    }

    public function deleteAccountAction()
    {
        $accountId = (int) $this->getRequest()->getParam('account_id');
        $helper    = Mage::helper('xfe_carrier');
        $carrierId = 0;

        if (!$accountId) {
            Mage::getSingleton('adminhtml/session')->addError($helper->__('Invalid parameter.'));
            return $this->_redirect('*/carrier/');
        }

        try {
            $account = Mage::getModel('xfe_carrier/carrier_account')->load($accountId);
            if ($account->getId()) {
                $carrierId = $account->getCarrierId();
                $account->delete();
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    $helper->__('Account deleted.')
                );
            } else {
                Mage::getSingleton('adminhtml/session')->addError(
                    $helper->__('This account does not exist.')
                );
            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        if ($carrierId) {
            return $this->_redirect('*/carrier/edit', array('id' => $carrierId));
        }
        return $this->_redirect('*/carrier/');
    }

    // ====================================================================
    // FTP账号 sub-actions (1.0.10+)
    // ====================================================================

    /**
     * Edit / new FTP账号. URL: carrier/editFtpAccount
     *
     * Query params:
     *   ftp_account_id  int (optional - 编辑模式)
     *   carrier_id      int (optional - 新建模式时必传)
     */
    public function editFtpAccountAction()
    {
        $ftpAccountId = (int)$this->getRequest()->getParam('ftp_account_id');
        $carrierId    = (int)$this->getRequest()->getParam('carrier_id');
        $helper       = Mage::helper('xfe_carrier');

        $ftp = Mage::getModel('xfe_carrier/carrier_ftp_account');
        if ($ftpAccountId) {
            $ftp->load($ftpAccountId);
            if (!$ftp->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    $helper->__('该 FTP账号不存在。')
                );
                return $this->_redirect('*/carrier/');
            }
            $carrierId = $ftp->getCarrierId();
        } else {
            if (!$carrierId) {
                Mage::getSingleton('adminhtml/session')->addError(
                    $helper->__('缺少承运商 ID。')
                );
                return $this->_redirect('*/carrier/');
            }
            $ftp->setCarrierId($carrierId);
        }

        Mage::register('xfe_carrier_ftp_account_data', $ftp);

        $this->_initAction()
            ->_addBreadcrumb(
                $ftpAccountId ? $helper->__('编辑 FTP账号') : $helper->__('新增 FTP账号'),
                $ftpAccountId ? $helper->__('编辑 FTP账号') : $helper->__('新增 FTP账号')
            )
            ->renderLayout();
    }

    /**
     * 保存 FTP账号. URL: carrier/saveFtpAccount
     */
    public function saveFtpAccountAction()
    {
        $data         = $this->getRequest()->getPost();
        $helper       = Mage::helper('xfe_carrier');

        if (!$data) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('无法保存:未接收到数据。')
            );
            return $this->_redirect('*/carrier/');
        }

        $ftpAccountId = (int)$this->getRequest()->getParam('ftp_account_id');
        $carrierId    = (int)$this->getRequest()->getParam('carrier_id');

        try {
            $ftp = Mage::getModel('xfe_carrier/carrier_ftp_account');
            if ($ftpAccountId) {
                $ftp->load($ftpAccountId);
                if (!$ftp->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        $helper->__('该 FTP账号不存在。')
                    );
                    return $this->_redirect('*/carrier/');
                }
                $carrierId = $ftp->getCarrierId();
            } else {
                if (!$carrierId) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        $helper->__('缺少承运商 ID。')
                    );
                    return $this->_redirect('*/carrier/');
                }
            }

            unset($data['form_key']);

            $ftp->addData($data);
            $ftp->save();
            $ftpAccountId = (int)$ftp->getId();

            // 自定义字段(1.0.14+):键值对列表,以 JSON 整体保存。
            if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
                $applier = new XFE_Carrier_Model_Service_FtpAccount_CustomAttributeApplier();
                $applier->applyFromPost($ftpAccountId, $data);
            }

            Mage::getSingleton('adminhtml/session')->addSuccess(
                $helper->__('FTP账号已保存。')
            );
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            if ($this->getRequest()->getParam('ftp_account_id')) {
                return $this->_redirect('*/*/editFtpAccount', array(
                    'ftp_account_id' => $ftpAccountId,
                    'carrier_id'     => $carrierId,
                ));
            }
            return $this->_redirect('*/*/editFtpAccount', array('carrier_id' => $carrierId));
        }

        return $this->_redirect('*/carrier/edit', array('id' => $carrierId));
    }

    /**
     * 删除 FTP账号. URL: carrier/deleteFtpAccount
     */
    public function deleteFtpAccountAction()
    {
        $ftpAccountId = (int)$this->getRequest()->getParam('ftp_account_id');
        $helper       = Mage::helper('xfe_carrier');
        $carrierId    = 0;

        if (!$ftpAccountId) {
            Mage::getSingleton('adminhtml/session')->addError($helper->__('参数无效。'));
            return $this->_redirect('*/carrier/');
        }

        try {
            $ftp = Mage::getModel('xfe_carrier/carrier_ftp_account')->load($ftpAccountId);
            if ($ftp->getId()) {
                $carrierId = (int)$ftp->getCarrierId();
                $ftp->delete();
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    $helper->__('FTP账号已删除。')
                );
            } else {
                Mage::getSingleton('adminhtml/session')->addError(
                    $helper->__('该 FTP账号不存在。')
                );
            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        if ($carrierId) {
            return $this->_redirect('*/carrier/edit', array('id' => $carrierId));
        }
        return $this->_redirect('*/carrier/');
    }

    // ====================================================================
    // Logo CRUD
    // ====================================================================

    /**
     * Render the "Add Logo" form for a carrier.
     */
    public function addLogoAction()
    {
        $carrierId = (int) $this->getRequest()->getParam('id');
        $helper    = Mage::helper('xfe_carrier');

        $carrier = Mage::getModel('xfe_carrier/carrier');
        if ($carrierId) {
            $carrier->load($carrierId);
            if (!$carrier->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    $helper->__('The carrier does not exist.')
                );
                return $this->_redirect('*/carrier/');
            }
        } else {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Missing carrier ID.')
            );
            return $this->_redirect('*/carrier/');
        }

        Mage::register('xfe_carrier_data', $carrier);

        $this->_initAction()
            ->_addBreadcrumb(
                $helper->__('Add Logo'),
                $helper->__('Add Logo')
            )
            ->renderLayout();
    }

    /**
     * Render the "Edit Logo" form for an existing logo.
     */
    public function editLogoAction()
    {
        $logoId   = (int) $this->getRequest()->getParam('logo_id');
        $helper   = Mage::helper('xfe_carrier');

        if (!$logoId) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Invalid logo ID.')
            );
            return $this->_redirect('*/carrier/');
        }

        $logo = Mage::getModel('xfe_carrier/carrier_logo')->load($logoId);
        if (!$logo->getId()) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('The logo does not exist.')
            );
            return $this->_redirect('*/carrier/');
        }

        $carrier = Mage::getModel('xfe_carrier/carrier')->load($logo->getCarrierId());
        if ($carrier && $carrier->getId()) {
            Mage::register('xfe_carrier_data', $carrier);
        }

        Mage::register('xfe_carrier_logo_data', $logo);

        $this->_initAction()
            ->_addBreadcrumb(
                $helper->__('Edit Logo'),
                $helper->__('Edit Logo')
            )
            ->renderLayout();
    }

    /**
     * Handle logo save (both new upload and edit).
     *
     * POST params:
     *   carrier_id   int (required)
     *   logo_id      int (optional - for edit mode)
     *   logo_label   string
     *   logo_type    string (main/mobile/alt)
     *   logo         file (optional for edit - replace existing)
     */
    public function saveLogoAction()
    {
        $carrierId = (int) $this->getRequest()->getParam('carrier_id');
        $logoId    = (int) $this->getRequest()->getParam('logo_id');
        $helper    = Mage::helper('xfe_carrier');

        if (!$carrierId) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Missing carrier ID.')
            );
            return $this->_redirect('*/carrier/');
        }

        $carrier = Mage::getModel('xfe_carrier/carrier')->load($carrierId);
        if (!$carrier->getId()) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('The carrier does not exist.')
            );
            return $this->_redirect('*/carrier/');
        }

        try {
            $label = $this->getRequest()->getParam('logo_label');
            $type  = $this->getRequest()->getParam('logo_type');

            if ($logoId) {
                // ===== Edit mode: update existing logo =====
                $logo = Mage::getModel('xfe_carrier/carrier_logo')->load($logoId);
                if (!$logo->getId() || (int)$logo->getCarrierId() !== $carrierId) {
                    Mage::throwException($helper->__('The logo does not exist.'));
                }

                $logo->setLabel($label);
                $logo->setLogoType($type);

                $fileData = isset($_FILES['logo']) ? $_FILES['logo'] : null;
                if ($fileData && isset($fileData['tmp_name']) && !empty($fileData['tmp_name'])) {
                    // Replace file: delete old one, upload new
                    XFE_Carrier_Model_Service_Registry::logo()->deleteById($carrierId, $logoId);
                    $result = XFE_Carrier_Model_Service_Registry::logo()->upload(
                        $carrierId, $fileData, $label, $type
                    );
                    if (!$result->isSuccess()) {
                        Mage::throwException($result->getMessage());
                    }
                    $logoId = (int) $result->getLogoId();
                } else {
                    $logo->save();
                }

                // 1.0.15+ 自定义属性 strict apply
                $post = $this->getRequest()->getPost();
                if ($logoId && isset($post['custom_fields']) && is_array($post['custom_fields'])) {
                    $applier = new XFE_Carrier_Model_Service_Logo_CustomAttributeApplier();
                    $applier->applyFromPost($logoId, $post);
                }

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    $helper->__('Logo saved.')
                );
            } else {
                // ===== New mode: upload new logo =====
                $fileData = isset($_FILES['logo']) ? $_FILES['logo'] : null;
                if (!$fileData || !isset($fileData['tmp_name']) || empty($fileData['tmp_name'])) {
                    Mage::throwException($helper->__('Please select a file to upload.'));
                }

                $result = XFE_Carrier_Model_Service_Registry::logo()->upload(
                    $carrierId, $fileData, $label, $type
                );

                if ($result->isSuccess()) {
                    // 1.0.15+ 自定义属性 strict apply
                    $post = $this->getRequest()->getPost();
                    $newLogoId = (int) $result->getLogoId();
                    if ($newLogoId && isset($post['custom_fields']) && is_array($post['custom_fields'])) {
                        $applier = new XFE_Carrier_Model_Service_Logo_CustomAttributeApplier();
                        $applier->applyFromPost($newLogoId, $post);
                    }

                    Mage::getSingleton('adminhtml/session')->addSuccess(
                        $helper->__('Logo uploaded.')
                    );
                } else {
                    Mage::throwException($result->getMessage());
                }
            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        return $this->_redirect('*/carrier/edit', array('id' => $carrierId));
    }

    /**
     * AJAX: delete a single logo. Returns JSON { success, message }.
     */
    public function deleteLogoAction()
    {
        $helper    = Mage::helper('xfe_carrier');
        $carrierId = (int) $this->getRequest()->getParam('carrier_id');
        $logoId    = (int) $this->getRequest()->getParam('logo_id');

        $this->getResponse()->setHeader('Content-Type', 'application/json');

        if (!$carrierId || !$logoId) {
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
                'success' => false,
                'message' => $helper->__('Invalid parameter.'),
            )));
            return;
        }

        try {
            $deleted = XFE_Carrier_Model_Service_Registry::logo()->deleteById($carrierId, $logoId);
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
                'success' => $deleted,
                'message' => $deleted
                    ? $helper->__('Logo deleted.')
                    : $helper->__('Logo could not be deleted.'),
            )));
        } catch (Exception $e) {
            Mage::logException($e);
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
                'success' => false,
                'message' => $e->getMessage(),
            )));
        }
    }
    // ====================================================================
    // Rule CRUD (standalone rule edit page)
    // ====================================================================

    /**
     * Render the Rule Edit page (General tab + Conditions tab).
     * Supports both new and edit modes.
     *
     * Query params:
     *   carrier_id   int (required)
     *   account_id   int (optional - bind to account)
     *   rule_id      int (optional - edit existing)
     */
    public function editRuleAction()
    {
        $carrierId = (int) $this->getRequest()->getParam('carrier_id');
        $accountId = (int) $this->getRequest()->getParam('account_id');
        $ruleId    = (int) $this->getRequest()->getParam('rule_id');
        $helper    = Mage::helper('xfe_carrier');

        if (!$carrierId) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Missing carrier ID.')
            );
            return $this->_redirect('*/carrier/');
        }

        $carrier = Mage::getModel('xfe_carrier/carrier')->load($carrierId);
        if (!$carrier->getId()) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('The carrier does not exist.')
            );
            return $this->_redirect('*/carrier/');
        }

        $rule = Mage::getModel('xfe_carrier/carrier_rule');
        if ($ruleId) {
            $rule->load($ruleId);
            if (!$rule->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    $helper->__('The rule does not exist.')
                );
                return $this->_redirect('*/carrier/edit', array('id' => $carrierId));
            }
        } else {
            $rule->setCarrierId($carrierId);
            if ($accountId) {
                $rule->setAccountId($accountId);
            }

        }

        Mage::register('xfe_carrier_rule_data', $rule);

        $this->_initAction()
            ->_addBreadcrumb(
                $ruleId ? $helper->__('Edit Rule') : $helper->__('New Rule'),
                $ruleId ? $helper->__('Edit Rule') : $helper->__('New Rule')
            )
            ->renderLayout();
    }

    /**
     * Save a rule (new or existing), including condition groups.
     *
     * POST params (from the rule edit form):
     *   carrier_id    int
     *   account_id    int (optional)
     *   rule_id       int (optional - edit existing)
     *   name          string
     *   description   string
     *   module_code   string (logo/account)
     *   status        int (0/1)
     *   is_cancel_on_failure int (0/1)
     *   sort_order    int
     *   groups_data   string (JSON from condition builder)
     */
    public function saveRuleAction()
    {
        $data   = $this->getRequest()->getPost();
        $helper = Mage::helper('xfe_carrier');

        if (!$data) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Cannot save: no data received.')
            );
            return $this->_redirect('*/carrier/');
        }

        $carrierId = (int) $this->getRequest()->getParam('carrier_id');
        $ruleId    = (int) $this->getRequest()->getParam('rule_id');

        if (!$carrierId) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Missing carrier ID.')
            );
            return $this->_redirect('*/carrier/');
        }

        try {
            $rule = Mage::getModel('xfe_carrier/carrier_rule');
            if ($ruleId) {
                $rule->load($ruleId);
                if (!$rule->getId()) {
                    Mage::throwException($helper->__('The rule does not exist.'));
                }
            } else {
                $rule->setCarrierId($carrierId);
            }

            $rule->addData($data);

            // Set groups_data for the _afterSave condition tree handler
            $groupsData = $this->getRequest()->getParam('groups_data');
            if ($groupsData !== null) {
                $rule->setGroupsData($groupsData);
            }

            $rule->save();

            Mage::getSingleton('adminhtml/session')->addSuccess(
                $helper->__('Rule saved.')
            );

            if ($this->getRequest()->getParam('back')) {
                return $this->_redirect('*/carrier/editRule', array(
                    'rule_id'    => $rule->getId(),
                    'carrier_id' => $carrierId,
                ));
            }

            $accountId = $rule->getAccountId();
            if ($carrierId && $accountId) {
                return $this->_redirect('*/carrier/editAccount', array(
                    'carrier_id' => $carrierId,
                    'account_id' => $accountId,
                ));
            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        return $this->_redirect('*/carrier/edit', array('id' => $carrierId));
    }

    /**
     * Delete a single rule (with its condition tree).
     *
     * Query params:
     *   rule_id    int (required)
     */
    public function deleteRuleAction()
    {
        $ruleId   = (int) $this->getRequest()->getParam('rule_id');
        $helper   = Mage::helper('xfe_carrier');
        $carrierId = 0;

        if (!$ruleId) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Invalid parameter.')
            );
            return $this->_redirect('*/carrier/');
        }

        try {
            $rule = Mage::getModel('xfe_carrier/carrier_rule')->load($ruleId);
            if ($rule->getId()) {
                $carrierId = (int)$rule->getCarrierId();
                XFE_Carrier_Model_Service_Registry::rule()->deleteById($ruleId);
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    $helper->__('Rule deleted.')
                );
            } else {
                Mage::getSingleton('adminhtml/session')->addError(
                    $helper->__('This rule does not exist.')
                );
            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        if ($carrierId) {
            return $this->_redirect('*/carrier/edit', array('id' => $carrierId));
        }
        return $this->_redirect('*/carrier/');
    }
    // ====================================================================
    // Rule bulk import / export (规则批量导入与导出)
    // ====================================================================

    /**
     * 规则批量导入落地页：渲染上传表单 + 模板下载链接。
     */
    public function ruleImportAction()
    {
        $this->_initAction()
            ->_title($this->__('批量导入承运商规则'))
            ->_addBreadcrumb(
                Mage::helper('xfe_carrier')->__('批量导入承运商规则'),
                Mage::helper('xfe_carrier')->__('批量导入承运商规则')
            )
            ->renderLayout();
    }

    /**
     * 处理规则上传：校验 form_key，交给 Rule_Importer 服务，结果存入
     * registry 供结果页渲染。
     */
    public function ruleImportPostAction()
    {
        $helper = Mage::helper('xfe_carrier');
        if (!$this->_validateFormKey()) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Invalid form key, please reload the page.')
            );
            return $this->_redirect('*/*/ruleImport');
        }

        $result = XFE_Carrier_Model_Service_Registry::ruleImporter()->importUpload(
            isset($_FILES['rule_csv']) ? $_FILES['rule_csv'] : array()
        );

        Mage::register('xfe_carrier_rule_import_result', $result);

        $session = Mage::getSingleton('adminhtml/session');
        if ($result->created > 0) {
            $session->addSuccess(
                $helper->__('%d rule(s) created.', $result->created)
            );
        }
        if ($result->updated > 0) {
            $session->addSuccess(
                $helper->__('%d rule(s) updated.', $result->updated)
            );
        }
        if ($result->skipped > 0) {
            $session->addWarning(
                $helper->__('%d row(s) skipped (see below).', $result->skipped)
            );
        }
        if ($result->created + $result->updated + $result->skipped === 0
            && !$result->hasErrors()
        ) {
            $session->addNotice($helper->__('Uploaded CSV contained no data rows.'));
        }
        return $this->_redirect('*/*/ruleImportResult');
    }

    /**
     * 规则导入结果页：展示计数器与逐行错误表。
     */
    public function ruleImportResultAction()
    {
        $result = Mage::registry('xfe_carrier_rule_import_result');
        if (!$result) {
            return $this->_redirect('*/*/ruleImport');
        }
        $this->_initAction()
            ->_title($this->__('批量导入承运商规则结果'))
            ->_addBreadcrumb(
                Mage::helper('xfe_carrier')->__('批量导入承运商规则结果'),
                Mage::helper('xfe_carrier')->__('批量导入承运商规则结果')
            )
            ->renderLayout();
    }

    /**
     * 流式输出规则导入 CSV 模板。
     */
    public function ruleDownloadTemplateAction()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xfe_carrier_rule_import_');
        if (!XFE_Carrier_Model_Service_Registry::ruleImporter()->writeTemplate($tmpFile)) {
            @unlink($tmpFile);
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('xfe_carrier')->__('Failed to build the rule CSV template.')
            );
            return $this->_redirect('*/*/ruleImport');
        }

        $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Content-Type', 'text/csv; charset=utf-8', true)
            ->setHeader(
                'Content-Disposition',
                'attachment; filename="carrier_rule_import_template.csv"',
                true
            )
            ->setHeader('Content-Length', (string)filesize($tmpFile), true)
            ->setBody(file_get_contents($tmpFile));
        @unlink($tmpFile);
        return $this;
    }

    /**
     * 导出全部承运商规则为 CSV 附件。
     */
    public function ruleExportAction()
    {
        $csv = XFE_Carrier_Model_Service_Registry::ruleExporter()->exportAll();

        $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Content-Type', 'text/csv; charset=utf-8', true)
            ->setHeader(
                'Content-Disposition',
                'attachment; filename="carrier_rules_export_' . date('Ymd_His') . '.csv"',
                true
            )
            ->setHeader('Content-Length', (string)strlen($csv), true)
            ->setBody($csv);
        return $this;
    }

    // ====================================================================
    // Account bulk import / export (账号批量导入与导出)
    // ====================================================================

    /**
     * 账号批量导入落地页：渲染上传表单 + 模板下载链接。
     */
    public function accountImportAction()
    {
        $this->_initAction()
            ->_title($this->__('批量导入承运商账号'))
            ->_addBreadcrumb(
                Mage::helper('xfe_carrier')->__('批量导入承运商账号'),
                Mage::helper('xfe_carrier')->__('批量导入承运商账号')
            )
            ->renderLayout();
    }

    /**
     * 处理账号上传：校验 form_key，交给 Account_Importer 服务，结果存入
     * registry 供结果页渲染。
     */
    public function accountImportPostAction()
    {
        $helper = Mage::helper('xfe_carrier');
        if (!$this->_validateFormKey()) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Invalid form key, please reload the page.')
            );
            return $this->_redirect('*/*/accountImport');
        }

        $result = XFE_Carrier_Model_Service_Registry::accountImporter()->importUpload(
            isset($_FILES['account_csv']) ? $_FILES['account_csv'] : array()
        );

        Mage::register('xfe_carrier_account_import_result', $result);

        $session = Mage::getSingleton('adminhtml/session');
        if ($result->created > 0) {
            $session->addSuccess(
                $helper->__('%d account(s) created.', $result->created)
            );
        }
        if ($result->updated > 0) {
            $session->addSuccess(
                $helper->__('%d account(s) updated.', $result->updated)
            );
        }
        if ($result->skipped > 0) {
            $session->addWarning(
                $helper->__('%d row(s) skipped (see below).', $result->skipped)
            );
        }
        if ($result->created + $result->updated + $result->skipped === 0
            && !$result->hasErrors()
        ) {
            $session->addNotice($helper->__('Uploaded CSV contained no data rows.'));
        }
        return $this->_redirect('*/*/accountImportResult');
    }

    /**
     * 账号导入结果页：展示计数器与逐行错误表。
     */
    public function accountImportResultAction()
    {
        $result = Mage::registry('xfe_carrier_account_import_result');
        if (!$result) {
            return $this->_redirect('*/*/accountImport');
        }
        $this->_initAction()
            ->_title($this->__('批量导入承运商账号结果'))
            ->_addBreadcrumb(
                Mage::helper('xfe_carrier')->__('批量导入承运商账号结果'),
                Mage::helper('xfe_carrier')->__('批量导入承运商账号结果')
            )
            ->renderLayout();
    }

    /**
     * 流式输出账号导入 CSV 模板。
     */
    public function accountDownloadTemplateAction()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xfe_carrier_account_import_');
        if (!XFE_Carrier_Model_Service_Registry::accountImporter()->writeTemplate($tmpFile)) {
            @unlink($tmpFile);
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('xfe_carrier')->__('Failed to build the account CSV template.')
            );
            return $this->_redirect('*/*/accountImport');
        }

        $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Content-Type', 'text/csv; charset=utf-8', true)
            ->setHeader(
                'Content-Disposition',
                'attachment; filename="carrier_account_import_template.csv"',
                true
            )
            ->setHeader('Content-Length', (string)filesize($tmpFile), true)
            ->setBody(file_get_contents($tmpFile));
        @unlink($tmpFile);
        return $this;
    }

    /**
     * 导出全部承运商账号为 CSV 附件。
     */
    public function accountExportAction()
    {
        $csv = XFE_Carrier_Model_Service_Registry::accountExporter()->exportAll();

        $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Content-Type', 'text/csv; charset=utf-8', true)
            ->setHeader(
                'Content-Disposition',
                'attachment; filename="carrier_accounts_export_' . date('Ymd_His') . '.csv"',
                true
            )
            ->setHeader('Content-Length', (string)strlen($csv), true)
            ->setBody($csv);
        return $this;
    }

    // ====================================================================
    // FTP账号 bulk import / export (FTP账号批量导入与导出)
    // ====================================================================

    /**
     * FTP账号批量导入落地页：渲染上传表单 + 模板下载链接。
     */
    public function ftpAccountImportAction()
    {
        $this->_initAction()
            ->_title($this->__('批量导入承运商 FTP账号'))
            ->_addBreadcrumb(
                Mage::helper('xfe_carrier')->__('批量导入承运商 FTP账号'),
                Mage::helper('xfe_carrier')->__('批量导入承运商 FTP账号')
            )
            ->renderLayout();
    }

    /**
     * 处理 FTP账号上传：校验 form_key，交给 FtpAccount_Importer 服务，
     * 结果存入 registry 供结果页渲染。
     */
    public function ftpAccountImportPostAction()
    {
        $helper = Mage::helper('xfe_carrier');
        if (!$this->_validateFormKey()) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Invalid form key, please reload the page.')
            );
            return $this->_redirect('*/*/ftpAccountImport');
        }

        $result = XFE_Carrier_Model_Service_Registry::ftpAccountImporter()->importUpload(
            isset($_FILES['ftp_account_csv']) ? $_FILES['ftp_account_csv'] : array()
        );

        Mage::register('xfe_carrier_ftp_account_import_result', $result);

        $session = Mage::getSingleton('adminhtml/session');
        if ($result->created > 0) {
            $session->addSuccess(
                $helper->__('%d FTP account(s) created.', $result->created)
            );
        }
        if ($result->updated > 0) {
            $session->addSuccess(
                $helper->__('%d FTP account(s) updated.', $result->updated)
            );
        }
        if ($result->skipped > 0) {
            $session->addWarning(
                $helper->__('%d row(s) skipped (see below).', $result->skipped)
            );
        }
        if ($result->created + $result->updated + $result->skipped === 0
            && !$result->hasErrors()
        ) {
            $session->addNotice($helper->__('Uploaded CSV contained no data rows.'));
        }
        return $this->_redirect('*/*/ftpAccountImportResult');
    }

    /**
     * FTP账号导入结果页：展示计数器与逐行错误表。
     */
    public function ftpAccountImportResultAction()
    {
        $result = Mage::registry('xfe_carrier_ftp_account_import_result');
        if (!$result) {
            return $this->_redirect('*/*/ftpAccountImport');
        }
        $this->_initAction()
            ->_title($this->__('批量导入承运商 FTP账号结果'))
            ->_addBreadcrumb(
                Mage::helper('xfe_carrier')->__('批量导入承运商 FTP账号结果'),
                Mage::helper('xfe_carrier')->__('批量导入承运商 FTP账号结果')
            )
            ->renderLayout();
    }

    /**
     * 流式输出 FTP账号导入 CSV 模板。
     */
    public function ftpAccountDownloadTemplateAction()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xfe_carrier_ftp_import_');
        if (!XFE_Carrier_Model_Service_Registry::ftpAccountImporter()->writeTemplate($tmpFile)) {
            @unlink($tmpFile);
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('xfe_carrier')->__('Failed to build the FTP account CSV template.')
            );
            return $this->_redirect('*/*/ftpAccountImport');
        }

        $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Content-Type', 'text/csv; charset=utf-8', true)
            ->setHeader(
                'Content-Disposition',
                'attachment; filename="carrier_ftp_account_import_template.csv"',
                true
            )
            ->setHeader('Content-Length', (string)filesize($tmpFile), true)
            ->setBody(file_get_contents($tmpFile));
        @unlink($tmpFile);
        return $this;
    }

    /**
     * 导出全部承运商 FTP账号为 CSV 附件。
     */
    public function ftpAccountExportAction()
    {
        $csv = XFE_Carrier_Model_Service_Registry::ftpAccountExporter()->exportAll();

        $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Content-Type', 'text/csv; charset=utf-8', true)
            ->setHeader(
                'Content-Disposition',
                'attachment; filename="carrier_ftp_accounts_export_' . date('Ymd_His') . '.csv"',
                true
            )
            ->setHeader('Content-Length', (string)strlen($csv), true)
            ->setBody($csv);
        return $this;
    }

public function resolveAction()
    {
        $helper = Mage::helper('xfe_carrier');
        $request = $this->getRequest();
        $carrierId = (int)$request->getParam('carrier_id');

        if (!$carrierId) {
            return $this->_jsonResponse(array(
                'error' => true,
                'message' => $helper->__('Missing carrier_id.'),
            ));
        }

        $allowedKeys = array(
            'country_code', 'city', 'zip_code', 'customer_group',
            'package_count', 'package_weight',
            'length', 'width', 'height', 'volume', 'order_amount',
        );
        $ctx = array();
        foreach ($allowedKeys as $k) {
            $v = $request->getParam($k);
            if ($v !== null && $v !== '') {
                $ctx[$k] = $v;
            }
        }

        $useFallback = $request->getParam('use_fallback', '1') !== '0';

        try {
            $matchCtx  = XFE_Carrier_Model_Service_Rule_MatchContext::create($ctx);
            $result    = XFE_Carrier_Model_Service_Registry::ruleResolver()
                ->resolve($carrierId, $matchCtx, $useFallback);
            $payload   = array(
                'error'  => false,
                'result' => $result->toArray(),
            );

            if ($result->getAccountId()) {
                $acc = Mage::getModel('xfe_carrier/carrier_account')->load($result->getAccountId());
                if ($acc->getId()) {
                    $payload['account'] = array(
                        'account_id'   => (int)$acc->getId(),
                        'account_name' => $acc->getAccountName(),
                        'account_no'   => $acc->getAccountNo(),
                        'endpoint_url' => $acc->getEndpointUrl(),
                    );
                }
            }
            if ($result->getLogoId()) {
                $logo = Mage::getModel('xfe_carrier/carrier_logo')->load($result->getLogoId());
                if ($logo->getId()) {
                    $payload['logo'] = array(
                        'logo_id'   => (int)$logo->getId(),
                        'logo_type' => $logo->getLogoType(),
                        'path'      => $logo->getPath(),
                        'url'       => Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . $logo->getPath(),
                    );
                }
            }
            return $this->_jsonResponse($payload);
        } catch (Exception $e) {
            Mage::logException($e);
            return $this->_jsonResponse(array(
                'error'   => true,
                'message' => $e->getMessage(),
            ));
        }
    }

    // ====================================================================
    // Helpers
    // ====================================================================

    /**
     * Cleanup all child entities (logo / account / ftp_account / rule) before carrier delete.
     */
    protected function _purgeCarrierChildren($carrierId)
    {
        XFE_Carrier_Model_Service_Registry::logo()->deleteAllForCarrier($carrierId);
        XFE_Carrier_Model_Service_Registry::account()->deleteAllForCarrier($carrierId);
        XFE_Carrier_Model_Service_Registry::ftpAccount()->deleteAllForCarrier($carrierId);
        XFE_Carrier_Model_Service_Registry::rule()->deleteAllForCarrier($carrierId);
    }

    /**
     * Emit a JSON response and stop.
     */
    protected function _jsonResponse($payload)
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($payload));
    }
}
