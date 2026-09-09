<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Account_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId   = 'account_id';
        $this->_blockGroup = 'xfe_carrier';
        // _controller 必须等于 Controller 文件名第二段('CarrierController' → 'carrier'),
        // 否则 getDeleteUrl() 拼出的 URL 是不存在的 'adminhtml_carrier/delete'。
        $this->_controller = 'carrier';
        $this->_mode       = 'edit';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('xfe_carrier')->__('保存账号'));
    }

    /**
     * 显式挂载 form 子块。
     *
     * **关键**:必须**跳过** parent::_prepareLayout()。
     * 父类 Form_Container::_prepareLayout() 会用 _blockGroup/_controller/_mode
     * 拼出 "xfe_carrier/adminhtml_carrier_edit_form" 去 createBlock(),我们的
     * 实际类名是 ..._Account_Edit_Form,拼出的 alias 找不到,createBlock()
     * 返回 null,setChild('form', null) 会把这里挂上去的 form 块覆盖成 null,
     * 模板 getChildHtml('form') 渲染空,页面就空白了。
     *
     * 正确做法:直接调祖父类 Container::_prepareLayout() 处理按钮,
     * 然后**自己**挂 form 块。参考 XFE_Carrier_Block_Adminhtml_Carrier_Account_Import
     * 的同款模式。
     *
     * @return Mage_Core_Block_Abstract
     */
    protected function _prepareLayout()
    {
        Mage_Adminhtml_Block_Widget_Container::_prepareLayout();
        $this->setChild(
            'form',
            $this->getLayout()->createBlock(
                'xfe_carrier/adminhtml_carrier_account_edit_form'
            )
        );
        return $this;
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
