<?php
/**
 * Rule Edit Tabs
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Block_Adminhtml_Rule_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('shipping_rule_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('xfeshippingrule')->__('Rule Information'));
    }

    protected function _beforeToHtml()
    {
        $this->addTab('general', array(
            'label'   => Mage::helper('xfeshippingrule')->__('General Information'),
            'title'   => Mage::helper('xfeshippingrule')->__('General Information'),
            'content' => $this->getLayout()->createBlock(
                'xfeshippingrule/adminhtml_rule_edit_tab_general'
            )->toHtml(),
            'active'  => true,
        ));

        $this->addTab('conditions', array(
            'label'   => Mage::helper('xfeshippingrule')->__('Conditions'),
            'title'   => Mage::helper('xfeshippingrule')->__('Conditions'),
            'content' => $this->getLayout()->createBlock(
                'xfeshippingrule/adminhtml_rule_edit_tab_conditions'
            )->toHtml(),
        ));

        return parent::_beforeToHtml();
    }
}
