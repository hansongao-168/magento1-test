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

        $this->_updateButton('save', 'label', Mage::helper('xfe_carrier')->__('Save Logo'));
        $this->_updateButton('back', 'onclick', 'setLocation(\'' . $this->getBackUrl() . '\')');
    }

    public function getHeaderText()
    {
        $logo = Mage::registry('xfe_carrier_logo_data');
        if ($logo && $logo->getId()) {
            return Mage::helper('xfe_carrier')->__('Edit Logo');
        }
        $carrier = Mage::registry('xfe_carrier_data');
        return Mage::helper('xfe_carrier')->__('Add Logo for "%s"', $carrier ? $carrier->getName() : '');
    }

    public function getBackUrl()
    {
        $carrier = Mage::registry('xfe_carrier_data');
        if ($carrier && $carrier->getId()) {
            return $this->getUrl('*/carrier/edit', array('id' => $carrier->getId()));
        }
        return $this->getUrl('*/carrier/');
    }

    public function getSaveUrl()
    {
        $logo    = Mage::registry('xfe_carrier_logo_data');
        $carrier = Mage::registry('xfe_carrier_data');
        $params  = array();
        if ($logo && $logo->getId()) {
            $params['logo_id'] = $logo->getId();
        }
        if ($carrier && $carrier->getId()) {
            $params['carrier_id'] = $carrier->getId();
        }
        return $this->getUrl('*/*/saveLogo', $params);
    }
}