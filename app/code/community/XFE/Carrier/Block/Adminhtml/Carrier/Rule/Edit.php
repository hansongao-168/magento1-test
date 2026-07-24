<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Rule_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId   = 'rule_id';
        $this->_blockGroup = 'xfe_carrier';
        $this->_controller = 'adminhtml_carrier_rule';
        $this->_mode       = 'edit';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('xfe_carrier')->__('保存规则'));
    }

    /**
     * @return string
     */
    public function getHeaderText()
    {
        $rule = Mage::registry('xfe_carrier_rule_data');
        if ($rule && $rule->getId()) {
            return Mage::helper('xfe_carrier')->__('编辑规则');
        }
        return Mage::helper('xfe_carrier')->__('新增规则');
    }

    public function getBackUrl()
    {
        $rule = Mage::registry('xfe_carrier_rule_data');
        $carrierId = $rule ? $rule->getCarrierId() : (int)$this->getRequest()->getParam('carrier_id');
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
        if ($carrierId) {
            $params['carrier_id'] = $carrierId;
        }
        return $this->getUrl('*/*/saveRule', $params);
    }
}
