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

            $model->addData($data);
            Mage::helper('xfe_carrier')->validateCarrierModules($model, $data);
            if (!empty($storeTranslations)) {
                $model->setData('store_translations', $storeTranslations);
            }
            $model->save();

            $carrierId = (int)$model->getId();
            if ($carrierId) {
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
                } else {
                    $logo->save();
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
