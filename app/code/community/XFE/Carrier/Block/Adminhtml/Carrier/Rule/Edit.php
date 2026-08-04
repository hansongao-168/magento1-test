<?php

/**
 * Carrier Rule Edit Container
 *
 * Mirrors XFE_ShippingRule_Block_Adminhtml_Rule_Edit:
 *   - Adds tabs (General + Conditions) as a child block
 *   - Uses a custom container.phtml that calls getChildHtml('tabs')
 *     and getFormHtml()
 *   - Localises the back button label to Chinese
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Rule_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId   = 'rule_id';
        $this->_blockGroup = 'xfe_carrier';
        $this->_controller = 'adminhtml_carrier_rule';
        $this->_mode       = 'edit';

        parent::__construct();

        $helper = Mage::helper('xfe_carrier');
        $this->_updateButton('save', 'label', $helper->__('保存规则'));
        $this->_updateButton('back', 'label', $helper->__('返回'));
        // 重置按钮去掉,避免误清空条件组
        $this->_removeButton('reset');

        // Custom template with Tabs support
        $this->setTemplate('xfe_carrier/rule/edit/container.phtml');
    }

    /**
     * Add the tabs block as a child so the container template can
     * render it via getChildHtml('tabs').
     *
     * @return Mage_Core_Block_Abstract
     */
    protected function _prepareLayout()
    {
        $this->setChild(
            'tabs',
            $this->getLayout()->createBlock(
                'xfe_carrier/adminhtml_carrier_rule_edit_tabs',
                'carrier_rule_edit_tabs'
            )
        );

        return parent::_prepareLayout();
    }

    /**
     * @return string
     */
    public function getHeaderText()
    {
        $rule = Mage::registry('xfe_carrier_rule_data');
        if ($rule && $rule->getId()) {
            return Mage::helper('xfe_carrier')->__('编辑规则 #%s', $rule->getId());
        }
        return Mage::helper('xfe_carrier')->__('新增规则');
    }

    /**
     * Back URL respects the rule's scope:
     *   - account-scope: back to the Account Edit page
     *   - carrier-scope: back to the Carrier Edit page
     *
     * @return string
     */
    public function getBackUrl()
    {
        $rule = Mage::registry('xfe_carrier_rule_data');
        $carrierId = $rule ? $rule->getCarrierId() : (int)$this->getRequest()->getParam('carrier_id');
        $accountId = $rule ? (int)$rule->getAccountId() : (int)$this->getRequest()->getParam('account_id');
        $logoId    = (int)$this->getRequest()->getParam('logo_id');

        if ($carrierId && $accountId) {
            return $this->getUrl('*/carrier/editAccount', array(
                'carrier_id' => $carrierId,
                'account_id' => $accountId,
            ));
        }

        if ($carrierId) {
            return $this->getUrl('*/carrier/edit', array('id' => $carrierId));
        }

        return $this->getUrl('*/carrier/');
    }

    public function getSaveUrl()
    {
        $rule = Mage::registry('xfe_carrier_rule_data');
        $params = array();
        if ($rule && $rule->getId()) {
            $params['rule_id'] = $rule->getId();
        }
        $carrierId = $rule ? $rule->getCarrierId() : (int)$this->getRequest()->getParam('carrier_id');
        $logoId    = (int)$this->getRequest()->getParam('logo_id');
        if ($carrierId) {
            $params['carrier_id'] = $carrierId;
        }
        if ($logoId) {
            $params['logo_id'] = $logoId;
        }
        return $this->getUrl('*/*/saveRule', $params);
    }
}