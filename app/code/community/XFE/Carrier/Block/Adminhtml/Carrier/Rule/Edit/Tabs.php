<?php

/**
 * Carrier Rule Edit Tabs
 *
 * Mirrors XFE_ShippingRule_Block_Adminhtml_Rule_Edit_Tabs so the Carrier
 * Rule Edit page renders the same way as ShippingRule's edit page:
 *   - General Information tab (rule identity)
 *   - Conditions tab (conditions builder)
 *
 * The tabs JS moves each tab's content into the outer <form> (dest
 * element id `edit_form`), which is the same form created by the
 * Edit/Form.php block.
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Rule_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('carrier_rule_edit_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('xfe_carrier')->__('规则信息'));
        $this->setTemplate('widget/tabs.phtml');
    }

    protected function _beforeToHtml()
    {
        $helper = Mage::helper('xfe_carrier');

        $this->addTab('general', array(
            'label'   => $helper->__('规则信息'),
            'title'   => $helper->__('规则信息'),
            'content' => $this->getLayout()->createBlock(
                'xfe_carrier/adminhtml_carrier_rule_edit_tab_general'
            )->toHtml(),
            'active'  => true,
        ));

        $this->addTab('conditions', array(
            'label'   => $helper->__('适合条件 (Conditions)'),
            'title'   => $helper->__('适合条件 (Conditions)'),
            'content' => $this->getLayout()->createBlock(
                'xfe_carrier/adminhtml_carrier_rule_edit_tab_conditions'
            )->toHtml(),
        ));

        return parent::_beforeToHtml();
    }
}