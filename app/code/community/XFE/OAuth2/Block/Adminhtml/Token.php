<?php
/**
 * Admin Token Grid Container
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Block_Adminhtml_Token extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'xfeoauth2';
        $this->_controller = 'adminhtml_token';
        $this->_headerText = Mage::helper('xfeoauth2')->__('Access Tokens');
        parent::__construct();
        $this->_removeButton('add');
    }
}
