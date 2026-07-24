<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Logo_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId   = 'logo_id';
        $this->_blockGroup = 'xfe_carrier';
        $this->_controller = 'adminhtml_carrier_logo';
        $this->_mode       = 'edit';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('xfe_carrier')->__('上传 Logo'));
        $this->_updateButton('back', 'onclick', 'setLocation(\'' . $this->getBackUrl() . '\')');
    }

    /**
     * @return string
     */
    public function getHeaderText()
    {
        $carrier = Mage::registry('xfe_carrier_data');
        return Mage::helper('xfe_carrier')->__('为 "%s" 添加 Logo', $carrier ? $carrier->getName() : '');
    }

    /**
     * @return string
     */
    public function getBackUrl()
    {
        $carrier = Mage::registry('xfe_carrier_data');
        if ($carrier && $carrier->getId()) {
            return $this->getUrl('*/carrier/edit', array('id' => $carrier->getId()));
        }
        return $this->getUrl('*/carrier/');
    }

    /**
     * @return string
     */
    public function getSaveUrl()
    {
        $carrier = Mage::registry('xfe_carrier_data');
        return $this->getUrl('*/*/saveLogo', array('id' => $carrier ? $carrier->getId() : 0));
    }
}
