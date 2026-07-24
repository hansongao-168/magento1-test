<?php
/**
 * Admin Client Grid Container
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Block_Adminhtml_Client extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'xfeoauth2';
        $this->_controller = 'adminhtml_client';
        $this->_headerText = Mage::helper('xfeoauth2')->__('OAuth 2.0 Clients');
        $this->_addButtonLabel = Mage::helper('xfeoauth2')->__('Add New Client');

        parent::__construct();
    }
}
