<?php
/**
 * Type Grid Container
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Block_Adminhtml_Type extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_type';
        $this->_blockGroup = 'xfeshippingrule';
        $this->_headerText = Mage::helper('xfeshippingrule')->__('Shipping Rule Types');
        $this->_addButtonLabel = Mage::helper('xfeshippingrule')->__('Add Type');
        parent::__construct();
    }
}
