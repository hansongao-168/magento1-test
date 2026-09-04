<?php

/**
 * Carrier FTP账号编辑 - Form Container
 *
 * 平行于 XFE_Carrier_Block_Adminhtml_Carrier_Account_Edit,
 * Save 指向 carrier/saveFtpAccount Action,
 * Back 指向 carrier/edit。
 */
class XFE_Carrier_Block_Adminhtml_Carrier_FtpAccount_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId   = 'ftp_account_id';
        $this->_blockGroup = 'xfe_carrier';
        // _controller 必须等于 Controller 文件名第二段('CarrierController' → 'carrier'),
        // 否则 getDeleteUrl() 拼出的 URL 是错的。
        $this->_controller = 'carrier';
        $this->_mode       = 'edit';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('xfe_carrier')->__('保存 FTP账号'));
    }

    /**
     * 显式挂载 form 子块,见 XFE_Carrier_Block_Adminhtml_Carrier_Account_Edit 的同款注释。
     *
     * @return Mage_Core_Block_Abstract
     */
    protected function _prepareLayout()
    {
        Mage_Adminhtml_Block_Widget_Container::_prepareLayout();
        $this->setChild(
            'form',
            $this->getLayout()->createBlock(
                'xfe_carrier/adminhtml_carrier_ftpaccount_edit_form'
            )
        );
        return $this;
    }

    public function getHeaderText()
    {
        $ftp = Mage::registry('xfe_carrier_ftp_account_data');
        if ($ftp && $ftp->getId()) {
            return Mage::helper('xfe_carrier')->__('编辑 FTP账号');
        }
        return Mage::helper('xfe_carrier')->__('新增 FTP账号');
    }

    public function getBackUrl()
    {
        $ftp       = Mage::registry('xfe_carrier_ftp_account_data');
        $carrierId = $ftp ? $ftp->getCarrierId() : (int)$this->getRequest()->getParam('carrier_id');
        if ($carrierId) {
            return $this->getUrl('*/carrier/edit', array('id' => $carrierId));
        }
        return $this->getUrl('*/carrier/');
    }

    public function getSaveUrl()
    {
        $ftp    = Mage::registry('xfe_carrier_ftp_account_data');
        $params = array();
        if ($ftp && $ftp->getId()) {
            $params['ftp_account_id'] = $ftp->getId();
        }
        $carrierId = $ftp ? $ftp->getCarrierId() : (int)$this->getRequest()->getParam('carrier_id');
        if ($carrierId) {
            $params['carrier_id'] = $carrierId;
        }
        return $this->getUrl('*/*/saveFtpAccount', $params);
    }
}