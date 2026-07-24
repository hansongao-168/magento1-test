<?php
/**
 * Rule Grid Container
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Block_Adminhtml_Rule extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_rule';
        $this->_blockGroup = 'xfeshippingrule';
        $this->_headerText = Mage::helper('xfeshippingrule')->__('Shipping Rules');
        $this->_addButtonLabel = Mage::helper('xfeshippingrule')->__('Add Rule');
        parent::__construct();
    }
}
