<?php

/**
 * 承运商账号编辑 - Form
 *
 * 字段名严格对齐 XFE_Carrier_Model_Carrier_Account 的真实列(由 1.0.1
 * install 起的 schema 固化),与 FTP 账号 Edit/Form 同款"键值对编辑器"
 * 共享同一份 phtml + JS 资源(模板:
 *   xfe_carrier/carrier/account/custom_fields.phtml)。
 *
 * 表单分段:
 *   1. 账号信息    (固定列)
 *   2. 自定义字段  (EAV-like 键值对,持久化到 custom_fields_json)
 *   3. 规则设置    (与原 Edit/Tab/Account 风格保持一致,挂载独立 Grid)
 *
 * 关联文档: docs/architecture/carrier-account-custom-fields.md
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Account_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * 用于 phtml 区分"主账号"还是"FTP 账号"的容器类型;
     * FTP Form 继承 / 复用相同模板时改写。
     */
    protected $_containerEntityType = 'account';

    protected function _prepareForm()
    {
        $helper  = Mage::helper('xfe_carrier');
        $account = Mage::registry('xfe_carrier_account_data');

        $form = new Varien_Data_Form(array(
            'id'     => 'edit_form',
            'action' => $this->getUrl('*/*/saveAccount'),
            'method' => 'post',
        ));
        $form->setUseContainer(true);

        // ============================================================
        // 1) 账号信息(固定列,与 Model 真实字段对齐)
        // ============================================================
        $fieldset = $form->addFieldset('base_fieldset', array(
            'legend' => $helper->__('账号信息'),
        ));

        if ($account && $account->getId()) {
            $fieldset->addField('account_id', 'hidden', array(
                'name' => 'account_id',
            ));
        }
        $fieldset->addField('carrier_id', 'hidden', array(
            'name' => 'carrier_id',
        ));

        $fieldset->addField('account_name', 'text', array(
            'name'     => 'account_name',
            'label'    => $helper->__('账号名称'),
            'title'    => $helper->__('账号名称'),
            'required' => true,
        ));

        $fieldset->addField('account_no', 'text', array(
            'name'  => 'account_no',
            'label' => $helper->__('账号编号'),
            'title' => $helper->__('账号编号'),
        ));

        $fieldset->addField('username', 'text', array(
            'name'  => 'username',
            'label' => $helper->__('用户名'),
            'title' => $helper->__('用户名'),
        ));

        $fieldset->addField('password', 'text', array(
            'name'  => 'password',
            'label' => $helper->__('密码'),
            'title' => $helper->__('密码'),
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

        $fieldset->addField('endpoint_url', 'text', array(
            'name'  => 'endpoint_url',
            'label' => $helper->__('API 端点 URL'),
            'title' => $helper->__('API 端点 URL'),
        ));

        $fieldset->addField('status', 'select', array(
            'name'   => 'status',
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

        $fieldset->addField('note', 'textarea', array(
            'name'  => 'note',
            'label' => $helper->__('备注'),
            'title' => $helper->__('备注'),
        ));

        if ($account) {
            $form->setValues($account->getData());
        }

        // ============================================================
        // 2) 自定义字段(键值对编辑器)
        // ============================================================
        $this->_addCustomFieldsFieldset($form, $account);

        $this->setForm($form);
        return parent::_prepareForm();
    }

    /**
     * 注入「自定义字段」fieldset。
     *
     * 1.0.15 严格模式: 从 xfe_carrier_custom_attribute 拉已登记属性,
     * 渲染 strict_editor.phtml(下拉 + 必填校验 + 旧数据兼容)。
     * 兼容模式(全局表为空): 退化为旧版键值对编辑器(自由 key)。
     *
     * @param Varien_Data_Form                     $form
     * @param XFE_Carrier_Model_Carrier_Account|null $account
     * @return void
     */
    protected function _addCustomFieldsFieldset(Varien_Data_Form $form, $account)
    {
        $helper   = Mage::helper('xfe_carrier');
        $entityType = $this->_containerEntityType;
        $defs     = XFE_Carrier_Model_Service_Registry::customAttributeService()
            ->getActiveDefs($entityType);
        $rawJson  = $account ? (string) $account->getCustomFieldsJson() : '';
        $hasDefs  = $defs->count() > 0;

        $note = $hasDefs
            ? $helper->__(
                '1.0.15 严格模式:仅显示在"自定义属性"菜单中已登记的字段。标有 * 的为必填,留空将无法保存。'
            )
            : $helper->__(
                '尚未在"自定义属性"菜单登记任何字段。先去登记后再回来填写。'
            );

        $fieldset = $form->addFieldset('custom_fields_fieldset', array(
            'legend' => $helper->__('自定义字段'),
            'note'   => $note,
        ));

        $fieldset->addField('custom_fields', 'note', array(
            'label' => $helper->__('键值对列表'),
            'text'  => $this->_renderCustomFieldsEditor($rawJson, $defs),
        ));
    }

    /**
     * 渲染键值对编辑器 phtml。
     *
     * 1.0.15 严格模式: 用 strict_editor.phtml(下拉 + 必填)。
     * 兼容模式(全局表为空): 退化为旧版 free-form 编辑器(允许自由 key)。
     *
     * @param string $rawJson
     * @param XFE_Carrier_Domain_CustomAttributeCollection $defs
     * @return string
     */
    protected function _renderCustomFieldsEditor(
        $rawJson,
        XFE_Carrier_Domain_CustomAttributeCollection $defs
    ) {
        if ($defs->count() === 0) {
            // 兼容模式: 用旧的 free-form 模板
            return $this->getLayout()->createBlock('core/template')
                ->setTemplate('xfe_carrier/carrier/account/custom_fields.phtml')
                ->setData('raw_json', $rawJson)
                ->setData('entity_type', $this->_containerEntityType)
                ->toHtml();
        }
        return $this->getLayout()->createBlock('core/template')
            ->setTemplate('xfe_carrier/custom_attribute/strict_editor.phtml')
            ->setData('raw_json', $rawJson)
            ->setData('entity_type', $this->_containerEntityType)
            ->setData('defs', $defs)
            ->toHtml();
    }

    /**
     * 规则设置区块(原有逻辑保留)。
     *
     * @return string
     */
    protected function _toHtml()
    {
        $html    = parent::_toHtml();
        $helper  = Mage::helper('xfe_carrier');
        $account = Mage::registry('xfe_carrier_account_data');

        $html .= '<div class="entry-edit xfe-carrier-account-rules-section" style="margin-top:20px;">';
        $html .= '<div class="entry-edit-head">';
        $html .= '<h4 class="icon-head head-edit-form fieldset-legend">'
              . $helper->__('规则设置') . '</h4>';
        $html .= '</div>';
        $html .= '<div class="fieldset">';
        $html .= '<p class="note" style="margin:0 0 10px 0;">'
              . $helper->__('绑定到本账号的规则将作为该账号的优选匹配规则;条件请在规则编辑页(点击列表中的 [编辑] 链接)中维护。')
              . '</p>';
        $html .= $this->_renderAccountRulesSection($account);
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * @param XFE_Carrier_Model_Carrier_Account|null $account
     * @return string
     */
    protected function _renderAccountRulesSection($account)
    {
        $helper = Mage::helper('xfe_carrier');

        if (!$account || !$account->getId()) {
            return '<p style="color:#999;font-style:italic;padding:6px 0;">'
                . $helper->__('请先保存账号,然后再为它绑定规则。')
                . '</p>';
        }

        return $this->getLayout()->createBlock(
            'xfe_carrier/adminhtml_carrier_edit_tab_account_rules_grid'
        )->toHtml();
    }
}
