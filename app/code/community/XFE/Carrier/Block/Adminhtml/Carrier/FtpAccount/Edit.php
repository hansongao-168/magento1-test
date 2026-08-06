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
        $this->_controller = 'adminhtml_carrier_ftp_account';
        $this->_mode       = 'edit';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('xfe_carrier')->__('保存 FTP账号'));
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