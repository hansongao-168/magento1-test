<?php

/**
 * Block Adminhtml Carrier Account Edit Form
 *
 * Inline rule editor (multi-rule since 1.0.7): the carrier-account no
 * longer has a `rule_id` FK; instead each rule carries an `account_id`
 * FK so one account can be bound to many candidate rules. The inline
 * editor renders a dynamic list (add / remove blocks via JS) and seeds
 * itself with whatever rules are already attached to the account.
 *
 * Each rule block carries the same fields as the dedicated Rule Edit
 * page so admins can configure name / status / cancel-on-failure /
 * sort_order without leaving the account page. module_code is locked
 * to 'account' (resolved from carrier_modules.xml).
 *
 * The visible inputs are name-less; only the hidden `inline_rules_data`
 * field actually submits. On form submit the JS serialises every block
 * into that field as a JSON array; the controller parses it and runs
 * the diff (insert / update / delete) via RuleService.
 *
 * No horizontal coupling: this block does not import the Rule model
 * directly. Reads go through the Rule collection, writes go through
 * the controller's saveAccountAction.
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Account_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /** Prefix used for all rule-related fields rendered by this form. */
    const RULE_FIELD_PREFIX = 'rule__';

    protected function _prepareForm()
    {
        $helper  = Mage::helper('xfe_carrier');
        $account = Mage::registry('xfe_carrier_account_data');


        // ---------- Module code (locked to "account" from carrier_modules) -
        // The inline rule editor on the Account page is only ever used to
        // create/update rules that target the account-management module, so
        // module_code is not user-editable here. We resolve its label from
        // carrier_modules.xml at render time so renaming "账号管理" in XML
        // is picked up automatically; the submitted value is still the code
        // ("account") that the Resolver expects.
        $inlineModuleCode  = 'account';
        $inlineModuleLabel = $helper->__('账号管理');
        foreach ($helper->getCarrierModules() as $_group) {
            if (isset($_group['modules'][$inlineModuleCode])) {
                $resolved = $_group['modules'][$inlineModuleCode]['label'];
                if ($resolved !== '') {
                    $inlineModuleLabel = $resolved;
                }
                break;
            }
        }

        // ---------- Form -----------------------------------------------
        $form = new Varien_Data_Form(array(
            'id'     => 'edit_form',
            'action' => $this->getUrl('*/*/saveAccount'),
            'method' => 'post',
        ));

        $form->setUseContainer(true);

        $fieldset = $form->addFieldset('base_fieldset', array(
            'legend' => $helper->__('账号信息'),
        ));

        if ($account && $account->getId()) {
            $fieldset->addField('account_id', 'hidden', array(
                'name'  => 'account_id',
            ));
        }

        $fieldset->addField('carrier_id', 'hidden', array(
            'name'  => 'carrier_id',
        ));

        $fieldset->addField('account_code', 'text', array(
            'name'     => 'account_code',
            'label'    => $helper->__('账号编号'),
            'title'    => $helper->__('账号编号'),
            'required' => true,
        ));

        $fieldset->addField('account_name', 'text', array(
            'name'     => 'account_name',
            'label'    => $helper->__('账号名称'),
            'title'    => $helper->__('账号名称'),
            'required' => true,
        ));

        $fieldset->addField('company', 'text', array(
            'name'  => 'company',
            'label' => $helper->__('物流公司'),
            'title' => $helper->__('物流公司'),
        ));

        $fieldset->addField('api_key', 'text', array(
            'name'  => 'api_key',
            'label' => $helper->__('API Key'),
            'title' => $helper->__('API Key'),
        ));

        $fieldset->addField('api_secret', 'text', array(
            'name'  => 'api_secret',
            'label' => $helper->__('API Secret'),
            'title' => $helper->__('API Secret'),
        ));

        $fieldset->addField('api_endpoint', 'text', array(
            'name'  => 'api_endpoint',
            'label' => $helper->__('API Endpoint'),
            'title' => $helper->__('API Endpoint'),
        ));

        $fieldset->addField('is_active', 'select', array(
            'name'   => 'is_active',
            'label'  => $helper->__('状态'),
            'title'  => $helper->__('状态'),
            'values' => Mage::getSingleton('xfe_carrier/source_status')->toOptionArray(),
        ));

        $fieldset->addField('sort_order', 'text', array(
            'name'  => 'sort_order',
            'label' => $helper->__('排序'),
            'title' => $helper->__('排序'),
            'class' => 'validate-number',
            'note'  => $helper->__('越小越靠前'),
        ));

        // ---------- Inline rule editor ----------------------------------
        $useRuleValue = $linkedRule ? 1 : 0;
        $ruleFieldset = $form->addFieldset('rule_fieldset', array(
            'legend' => $helper->__('规则设置（内联）'),
            'note'   => $helper->__('可添加多条规则，每条都会成为该账号的候选匹配规则；多维条件请在「规则管理」Tab 中编辑。所建规则同样会出现在「规则管理」标签页中。'),
        ));

        // Hidden mirror: keep the original rule__module_code contract so
        // _materialiseInlineRule() can stay backward-compatible.
        $ruleFieldset->addField(self::RULE_FIELD_PREFIX . 'module_code', 'hidden', array(
            'name'  => self::RULE_FIELD_PREFIX . 'module_code',
            'value' => $inlineModuleCode,
        ));
        $ruleFieldset->addField(
            self::RULE_FIELD_PREFIX . 'module_code_display',
            'label',
            array(
                'name'   => self::RULE_FIELD_PREFIX . 'module_code_display',
                'label'  => $helper->__('适用模块'),
                'title'  => $helper->__('适用模块'),
                'value'  => $inlineModuleLabel,
                'bold'   => true,
                'after_element_html' => '<br /><small style="color:#888;">'
                    . $helper->__('该模块在账号页面固定为「账号管理」，与 carrier_modules.xml 中的定义一致；不可修改。')
                    . '</small>',
            )
        );

        // ---------- Dynamic rule editor (multi-rule) ---------------------
        // We replace the single inline block with a JavaScript-driven list
        // that lets the admin add / remove rules. Only the hidden
        // `inline_rules_data` field actually submits - the visible inputs
        // are name-less and are serialised into JSON by the on-submit hook.
        $existingRules = $this->_loadInlineRulesForAccount($account);
        $ruleFieldset->addField('inline_rules_data', 'hidden', array(
            'name'  => 'inline_rules_data',
            'value' => Mage::helper('core')->jsonEncode($existingRules),
        ));

        $statusOptions = Mage::getSingleton('xfe_carrier/source_status')->toOptionArray();
        $statusOptionsJson = Mage::helper('core')->jsonEncode($statusOptions);

        // Status options for the per-block "规则状态" select. Built as raw
        // <option> HTML so JS can stamp them into each block.
        $statusOptionsHtml = '';
        foreach ($statusOptions as $opt) {
            $statusOptionsHtml .= '<option value="' . (int)$opt['value'] . '">'
                . htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') . '</option>';
        }

        $cancelOptions = array(
            array('value' => 0, 'label' => $helper->__('否')),
            array('value' => 1, 'label' => $helper->__('是')),
        );
        $cancelOptionsHtml = '';
        foreach ($cancelOptions as $opt) {
            $cancelOptionsHtml .= '<option value="' . (int)$opt['value'] . '">'
                . htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') . '</option>';
        }

        // UI: container + add button + JS bootstrap.
        $uiHtml = '<div id="xfe-inline-rules-list"></div>'
            . '<button type="button" id="xfe-inline-rules-add" class="scalable add" style="margin-top:8px;">'
            . '<span><span><span>+ ' . $helper->__('添加规则') . '</span></span></span>'
            . '</button>'
            // JS template - cloned by JS when admin clicks "添加规则".
            . '<div id="xfe-inline-rule-template" style="display:none;">'
            .   '<div class="xfe-inline-rule-block entry-edit" style="margin-bottom:8px;border:1px solid #ddd;padding:8px;">'
            .     '<div class="entry-edit-head" style="background:#f6f6f6;padding:4px 8px;margin:-8px -8px 8px;">'
            .       '<strong>#<span class="xfe-rule-index">1</span></strong>'
            .       '<button type="button" class="xfe-inline-rule-remove scalable delete" style="float:right;">'
            .         '<span><span><span>' . $helper->__('删除') . '</span></span></span>'
            .       '</button>'
            .     '</div>'
            .     '<table cellspacing="0" cellpadding="4" width="100%">'
            .       '<tr><td class="label"><label>' . $helper->__('规则名称') . '</label></td>'
            .         '<td><input type="text" class="xfe-rule-name input-text" style="width:90%;" /></td></tr>'
            .       '<tr><td class="label"><label>' . $helper->__('规则状态') . '</label></td>'
            .         '<td><select class="xfe-rule-status">' . $statusOptionsHtml . '</select></td></tr>'
            .       '<tr><td class="label"><label>' . $helper->__('失败时取消') . '</label></td>'
            .         '<td><select class="xfe-rule-cancel">' . $cancelOptionsHtml . '</select></td></tr>'
            .       '<tr><td class="label"><label>' . $helper->__('规则优先级') . '</label></td>'
            .         '<td><input type="text" class="xfe-rule-sort input-text validate-number" style="width:60px;" />'
            .           ' <small style="color:#888;">' . $helper->__('越小越先匹配') . '</small></td></tr>'
            .       '<input type="hidden" class="xfe-rule-id" value="" />'
            .     '</table>'
            .   '</div>'
            . '</div>';

        $ruleFieldset->addField('inline_rules_ui', 'note', array(
            'label' => $helper->__('规则列表'),
            'text'  => $uiHtml,
        ));

        // JS bootstrap (vanilla; no jQuery dependency).
        $js = <<<'JSEOF'
<script type="text/javascript">
(function () {
    function $ (id) { return document.getElementById(id); }
    function el (cls, root) { return (root || document).getElementsByClassName(cls)[0]; }
    function els (cls, root) { return Array.prototype.slice.call((root || document).getElementsByClassName(cls)); }

    var list     = $('xfe-inline-rules-list');
    var tplWrap  = $('xfe-inline-rule-template');
    var addBtn   = $('xfe-inline-rules-add');
    var hidden   = $('inline_rules_data');
    var form     = $('edit_form');

    if (!list || !tplWrap || !addBtn || !hidden || !form) { return; }

    var nextId = 1;

    function buildBlock (data) {
        data = data || {};
        var src  = tplWrap.getElementsByClassName('xfe-inline-rule-block')[0];
        var node = src.cloneNode(true);
        node.style.display = '';
        node.setAttribute('data-rule-index', nextId);
        node.getElementsByClassName('xfe-rule-index')[0].textContent = String(nextId);
        el('xfe-rule-id',     node).value     = data.rule_id     || '';
        el('xfe-rule-name',   node).value     = data.name        || '';
        el('xfe-rule-status', node).value     = (data.is_active !== undefined ? data.is_active : 1);
        el('xfe-rule-cancel', node).value     = (data.is_cancel_on_failure !== undefined ? data.is_cancel_on_failure : 0);
        el('xfe-rule-sort',   node).value     = (data.sort_order !== undefined ? data.sort_order : '');
        list.appendChild(node);
        nextId++;
        wireRemove(node);
    }

    function wireRemove (node) {
        var btn = el('xfe-rule-remove', node) || node.getElementsByClassName('xfe-inline-rule-remove')[0];
        if (!btn) { return; }
        btn.addEventListener('click', function () {
            node.parentNode.removeChild(node);
            // Renumber visible indices so the UI stays clean.
            els('xfe-inline-rule-block', list).forEach(function (b, i) {
                b.getElementsByClassName('xfe-rule-index')[0].textContent = String(i + 1);
            });
        });
    }

    function collect () {
        var data = [];
        els('xfe-inline-rule-block', list).forEach(function (b) {
            var id = el('xfe-rule-id',     b).value;
            var n  = el('xfe-rule-name',   b).value;
            // Skip empty blocks - admin opened the form, clicked Add,
            // never filled anything in. Don't pollute the rule pool.
            if (!id && n.trim() === '') { return; }
            data.push({
                rule_id:               id ? parseInt(id, 10) : 0,
                name:                  n,
                is_active:             parseInt(el('xfe-rule-status', b).value, 10),
                is_cancel_on_failure:  parseInt(el('xfe-rule-cancel', b).value, 10),
                sort_order:            parseInt(el('xfe-rule-sort',   b).value || 0, 10),
            });
        });
        hidden.value = JSON.stringify(data);
    }

    // Initial population: read whatever PHP seeded into the hidden field.
    try {
        var seed = JSON.parse(hidden.value || '[]');
        if (Object.prototype.toString.call(seed) === '[object Array]') {
            seed.forEach(buildBlock);
        }
    } catch (e) { /* malformed seed - start empty */ }

    addBtn.addEventListener('click', function () { buildBlock({}); });

    // Serialize before any submit (varienForm does not interfere with
    // this hidden field because it has no validation rules).
    form.addEventListener('submit', collect);
    if (window.varienForm && varienForm && varienForm.submitHandler) {
        // No-op; placeholder if a future submit validator needs the data.
    }
})();
</script>
JSEOF;

        $ruleFieldset->addField('inline_rules_js', 'note', array(
            'text' => $js,
        ));

        // ---------- Pre-fill values from account only --------------------
        // Rule rows are seeded into the hidden `inline_rules_data` JSON
        // above; JS uses that to paint the per-rule blocks. We still let
        // account columns pre-fill the form so edit form behaves correctly.
        if ($account) {
            $form->setValues($account->getData());
        }

        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Load the rules that already exist for this account, ordered by
     * sort_order ASC. Returned in the JSON shape the JS editor expects.
     *
     * Each entry:
     *   rule_id, name, is_active, is_cancel_on_failure, sort_order
     *
     * @param XFE_Carrier_Model_Carrier_Account|null $account
     * @return array
     */
    protected function _loadInlineRulesForAccount($account)
    {
        if (!$account || !$account->getId()) {
            return array();
        }
        $rules = Mage::getModel('xfe_carrier/carrier_rule')->getCollection()
            ->addFieldToFilter('account_id', (int)$account->getId())
            ->setOrder('sort_order', 'ASC');
        $out = array();
        foreach ($rules as $rule) {
            $out[] = array(
                'rule_id'              => (int)$rule->getId(),
                'name'                 => (string)$rule->getName(),
                'is_active'            => (int)$rule->getStatus(),
                'is_cancel_on_failure' => (int)$rule->getIsCancelOnFailure(),
                'sort_order'           => (int)$rule->getSortOrder(),
            );
        }
        return $out;
    }
}