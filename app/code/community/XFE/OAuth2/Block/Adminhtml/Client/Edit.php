<?php
/**
 * Admin Client Edit Container
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Block_Adminhtml_Client_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'xfeoauth2';
        $this->_controller = 'adminhtml_client';
        $this->_mode = 'edit';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('xfeoauth2')->__('Save Client'));
        $this->_updateButton('delete', 'label', Mage::helper('xfeoauth2')->__('Delete Client'));
    }

    /**
     * @return string
     */
    public function getHeaderText()
    {
        $model = Mage::registry('xfeoauth2_client');
        if ($model && $model->getId()) {
            return Mage::helper('xfeoauth2')->__('Edit Client: %s', $model->getName());
        }
        return Mage::helper('xfeoauth2')->__('New Client');
    }
}
