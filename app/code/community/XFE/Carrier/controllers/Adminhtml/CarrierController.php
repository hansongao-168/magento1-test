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
        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('xfe_carrier/carrier');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    $helper->__('The carrier does not exist.')
                );
                return $this->_redirect('*/*/');
            }
        }

        Mage::register('xfe_carrier_data', $model);

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
            $model = Mage::getModel('xfe_carrier/carrier');

            if ($id) {
                $model->load($id);
                if (!$model->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('xfe_carrier')->__('The carrier does not exist.')
                    );
                    return $this->_redirect('*/*/');
                }
            }

            $model->addData($data);
            Mage::helper('xfe_carrier')->validateCarrierModules($model, $data);
            $model->save();

            $carrierId = (int)$model->getId();
            if ($carrierId) {
                if (array_key_exists('accounts_data', $data)) {
                    XFE_Carrier_Model_Service_Registry::account()->saveBatch(
                        $carrierId, $data['accounts_data']
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
                return $this->_redirect('*/*/edit', array('id' => $model->getId()));
            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            if ($id) {
                return $this->_redirect('*/*/edit', array('id' => $id));
            }
            return $this->_redirect('*/*/new');
        }

        return $this->_redirect('*/*/');
    }

    public function deleteAction()
    {
        $id = $this->getRequest()->getParam('id');

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
            unset($data['inline_rules_data']);  // handled separately below

            $account->addData($data);
            $account->save();
            $accountId = (int)$account->getId();

            // ---------- Inline rule(s) materialisation --------------------
            // 1.0.7+: the inline editor on the Account Edit page is a
            // dynamic list. JS submits a JSON array in `inline_rules_data`
            // describing every rule the admin wants bound to this account.
            // We diff it against what already exists, then insert / update /
            // delete so the carrier's rule pool stays clean.
            $this->_materialiseInlineRules($carrierId, $accountId, $this->getRequest()->getPost('inline_rules_data'));

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
    // Rule sub-actions
    // ====================================================================

    public function editRuleAction()
    {
        $ruleId    = (int) $this->getRequest()->getParam('rule_id');
        $carrierId = (int) $this->getRequest()->getParam('carrier_id');
        $helper    = Mage::helper('xfe_carrier');

        $rule = Mage::getModel('xfe_carrier/carrier_rule');
        if ($ruleId) {
            $rule->load($ruleId);
            if (!$rule->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    $helper->__('This rule does not exist.')
                );
                return $this->_redirect('*/carrier/');
            }
            $carrierId = $rule->getCarrierId();
        } else {
            if (!$carrierId) {
                Mage::getSingleton('adminhtml/session')->addError(
                    $helper->__('Missing carrier ID.')
                );
                return $this->_redirect('*/carrier/');
            }
            $rule->setCarrierId($carrierId);
        }

        Mage::register('xfe_carrier_rule_data', $rule);

        $this->_initAction()
            ->_addBreadcrumb(
                $ruleId ? $helper->__('Edit Rule') : $helper->__('New Rule'),
                $ruleId ? $helper->__('Edit Rule') : $helper->__('New Rule')
            )
            ->renderLayout();
    }

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

        $ruleId    = (int) $this->getRequest()->getParam('rule_id');
        $carrierId = (int) $this->getRequest()->getParam('carrier_id');

        try {
            $rule = Mage::getModel('xfe_carrier/carrier_rule');
            if ($ruleId) {
                $rule->load($ruleId);
                if (!$rule->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        $helper->__('This rule does not exist.')
                    );
                    return $this->_redirect('*/carrier/');
                }
                $carrierId = $rule->getCarrierId();
            } else {
                if (!$carrierId) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        $helper->__('Missing carrier ID.')
                    );
                    return $this->_redirect('*/carrier/');
                }
            }

            unset($data['form_key']);
            $rule->addData($data);
            $rule->save();

            Mage::getSingleton('adminhtml/session')->addSuccess(
                $helper->__('Rule saved.')
            );
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        if ($carrierId) {
            return $this->_redirect('*/carrier/edit', array('id' => $carrierId));
        }
        return $this->_redirect('*/carrier/');
    }

    public function deleteRuleAction()
    {
        $ruleId    = (int) $this->getRequest()->getParam('rule_id');
        $helper    = Mage::helper('xfe_carrier');
        $carrierId = 0;

        if (!$ruleId) {
            Mage::getSingleton('adminhtml/session')->addError($helper->__('Invalid parameter.'));
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
    // AJAX resolver
    // ====================================================================

    /**
     * AJAX: given a shipment context, resolve the right account + logo.
     *
     * POST params (all optional except carrier_id):
     *   carrier_id           int
     *   country_code         string
     *   city                 string
     *   zip_code             string
     *   package_count        int|float
     *   package_weight       float
     *   length, width, height, volume, order_amount, customer_group
     *   use_fallback         '0' | '1'  (default 1)
     *
     * Returns JSON:
     *   { account_id, account_rule_id, logo_id, logo_rule_id, used_fallback }
     */
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
     * Sync the inline rule editor's payload with the carrier's rule pool.
     *
     * Payload is a JSON array (string from $_POST) of rows shaped like:
     *   { rule_id:int, name:string, is_active:int, is_cancel_on_failure:int,
     *     sort_order:int }
     *
     * Behaviour:
     *   - Rows whose `name` is empty (and which have no rule_id) are
     *     dropped on the floor: the admin opened the form, clicked "Add",
     *     walked away.
     *   - Rows whose `name` is empty but DO have a rule_id: the existing
     *     rule is deleted (admin clicked the trash icon).
     *   - Existing rules for this carrier+account that are NOT present in
     *     the payload are deleted (deletion-by-diff).
     *   - Anything else is inserted or updated.
     *
     * All errors (malformed JSON, etc.) are swallowed silently and the
     * caller continues - rule editing is best-effort next to account save.
     *
     * @param int    $carrierId
     * @param int    $accountId
     * @param mixed  $payload
     * @return void
     */
    protected function _materialiseInlineRules($carrierId, $accountId, $payload)
    {
        $carrierId = (int)$carrierId;
        $accountId = (int)$accountId;
        if (!$carrierId || !$accountId) {
            return;
        }
        $rules = $this->_decodeInlineRulesPayload($payload);
        if (!is_array($rules)) {
            return;
        }

        $write     = Mage::getSingleton('core/resource')->getConnection('core_write');
        $ruleTable = Mage::getSingleton('core/resource')->getTableName('xfe_carrier/carrier_rule');

        $existingIds = $write->fetchCol(
            $write->select()->from($ruleTable, 'rule_id')
                ->where('carrier_id = ?', $carrierId)
                ->where('account_id = ?', $accountId)
        );

        $keptIds = array();
        foreach ($rules as $row) {
            $name = isset($row['name']) ? trim((string)$row['name']) : '';
            $ruleId = isset($row['rule_id']) ? (int)$row['rule_id'] : 0;

            if ($name === '') {
                // Empty name on a brand-new row: skip.
                // Empty name on an existing row: drop the rule (trashed).
                if ($ruleId > 0 && in_array($ruleId, $existingIds, true)) {
                    $this->_deleteRuleCascade($ruleId);
                }
                continue;
            }

            $data = array(
                'carrier_id'           => $carrierId,
                'account_id'           => $accountId,
                'module_code'          => 'account',
                'name'                 => $name,
                'status'               => isset($row['is_active'])            ? (int)$row['is_active']            : 1,
                'is_cancel_on_failure' => isset($row['is_cancel_on_failure']) ? (int)$row['is_cancel_on_failure'] : 0,
                'sort_order'           => isset($row['sort_order'])           ? (int)$row['sort_order']           : 0,
                'updated_at'           => Varien_Date::now(),
            );

            if ($ruleId > 0 && in_array($ruleId, $existingIds, true)) {
                $write->update($ruleTable, $data, array('rule_id = ?' => $ruleId));
                $keptIds[] = $ruleId;
            } else {
                $data['created_at'] = Varien_Date::now();
                $write->insert($ruleTable, $data);
                $keptIds[] = (int)$write->lastInsertId($ruleTable);
            }
        }

        // Diff: anything existing that's NOT in the submitted list gets
        // removed (admin clicked the trash icon in the UI).
        $removed = array_diff($existingIds, $keptIds);
        foreach ($removed as $delId) {
            $this->_deleteRuleCascade((int)$delId);
        }
    }

    /**
     * Decode the JSON payload submitted by the inline rule editor.
     * Tolerates: null, empty string, JSON string, already-decoded array.
     *
     * @param mixed $payload
     * @return array|null
     */
    protected function _decodeInlineRulesPayload($payload)
    {
        if (is_array($payload)) {
            return $payload;
        }
        if (is_string($payload) && $payload !== '') {
            $decoded = Mage::helper('core')->jsonDecode($payload);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    /**
     * Delete a rule and every condition group / leaf condition attached
     * to it. Mirrors what RuleService::deleteById does, but lives here so
     * the inline-editor code path does not need to reach into the rule
     * service.
     *
     * @param int $ruleId
     * @return void
     */
    protected function _deleteRuleCascade($ruleId)
    {
        $ruleId = (int)$ruleId;
        if (!$ruleId) {
            return;
        }
        $write      = Mage::getSingleton('core/resource')->getConnection('core_write');
        $ruleTable  = Mage::getSingleton('core/resource')->getTableName('xfe_carrier/carrier_rule');
        $groupTable = Mage::getSingleton('core/resource')->getTableName('xfe_carrier/rule_condition_group');
        $condTable  = Mage::getSingleton('core/resource')->getTableName('xfe_carrier/rule_condition');

        $write->delete($condTable, array(
            'group_id IN (?)' => $write->select()
                ->from($groupTable, 'group_id')
                ->where('rule_id = ?', $ruleId),
        ));
        $write->delete($groupTable, array('rule_id = ?' => $ruleId));
        $write->delete($ruleTable,  array('rule_id = ?' => $ruleId));
    }

    /**
     * Cleanup all child entities (logo / account / rule) before carrier delete.
     */
    protected function _purgeCarrierChildren($carrierId)
    {
        XFE_Carrier_Model_Service_Registry::logo()->deleteAllForCarrier($carrierId);
        XFE_Carrier_Model_Service_Registry::account()->deleteAllForCarrier($carrierId);
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
