<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Account_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId   = 'account_id';
        $this->_blockGroup = 'xfe_carrier';
        $this->_controller = 'adminhtml_carrier_account';
        $this->_mode       = 'edit';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('xfe_carrier')->__('保存账号'));
    }

    /**
     * @return string
     */
    public function getHeaderText()
    {
        $account = Mage::registry('xfe_carrier_account_data');
        if ($account && $account->getId()) {
            return Mage::helper('xfe_carrier')->__('编辑账号');
        }
        return Mage::helper('xfe_carrier')->__('新增账号');
    }

    public function getBackUrl()
    {
        $account = Mage::registry('xfe_carrier_account_data');
        $carrierId = $account ? $account->getCarrierId() : (int)$this->getRequest()->getParam('carrier_id');
        if ($carrierId) {
            return $this->getUrl('*/carrier/edit', array('id' => $carrierId));
        }
        return $this->getUrl('*/carrier/');
    }

    public function getSaveUrl()
    {
        $account = Mage::registry('xfe_carrier_account_data');
        $params = array();
        if ($account && $account->getId()) {
            $params['account_id'] = $account->getId();
        }
        $carrierId = $account ? $account->getCarrierId() : (int)$this->getRequest()->getParam('carrier_id');
        if ($carrierId) {
            $params['carrier_id'] = $carrierId;
        }
        return $this->getUrl('*/*/saveAccount', $params);
    }
}
