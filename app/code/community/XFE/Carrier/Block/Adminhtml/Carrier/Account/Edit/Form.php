<?php

/**
 * Block Adminhtml Carrier Account Edit Form
 *
 * Layout (matches the carrier-level Rules tab UX):
 *   - 账号信息 fieldset: account identity fields (Varien form)
 *   - 规则设置 section : full-width entry-edit block rendered after the
 *     form, hosting the list of rules bound to this account + a
 *     [+ 添加规则] button that links out to the dedicated Carrier Rule
 *     Edit page (which hosts the conditions builder - same UX as
 *     XFE_ShippingRule Conditions tab).
 *
 * The rules section is rendered OUTSIDE the Varien form so the Magento
 * grid widget gets the full content width instead of being squeezed
 * into the value column of a <note> element.
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Account_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $helper  = Mage::helper('xfe_carrier');
        $account = Mage::registry('xfe_carrier_account_data');

        // ---------- Form -----------------------------------------------
        $form = new Varien_Data_Form(array(
            'id'     => 'edit_form',
            'action' => $this->getUrl('*/*/saveAccount'),
            'method' => 'post',
        ));

        $form->setUseContainer(true);

        // ---------- Fieldset: 账号信息 ---------------------------------
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

        if ($account) {
            $form->setValues($account->getData());
        }

        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Render the account form (parent::_toHtml()), then append a
     * full-width 规则设置 section hosting the rules grid.
     *
     * Using an entry-edit block (instead of a <note> element inside the
     * form) keeps the grid at the full content width and matches the
     * look-and-feel of the carrier-level Rules tab.
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
              . $helper->__('绑定到本账号的规则将作为该账号的优选匹配规则；条件请在规则编辑页（点击列表中的 [编辑] 链接）中维护。')
              . '</p>';
        $html .= $this->_renderAccountRulesSection($account);
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render the rules grid + the [+ 添加规则] button, or the
     * "please save the account first" hint when the account hasn't
     * been saved yet.
     *
     * Uses the dedicated
     * XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Account_Rules_Grid
     * block so the rules list inherits the standard admin grid styling
     * (column widths, zebra striping, severity badges via
     * grid_severity_notice|critical, action links) - no custom inline CSS.
     *
     * @param XFE_Carrier_Model_Carrier_Account|null $account
     * @return string
     */
    protected function _renderAccountRulesSection($account)
    {
        $helper = Mage::helper('xfe_carrier');

        if (!$account || !$account->getId()) {
            return '<p style="color:#999;font-style:italic;padding:6px 0;">'
                . $helper->__('请先保存账号，然后再为它绑定规则。')
                . '</p>';
        }

        // Registry already carries the account at this point
        // (editAccountAction populates xfe_carrier_account_data); the
        // grid picks up account_id from there for both the filter and
        // the action URLs.
        return $this->getLayout()->createBlock(
            'xfe_carrier/adminhtml_carrier_edit_tab_account_rules_grid'
        )->toHtml();
    }
}