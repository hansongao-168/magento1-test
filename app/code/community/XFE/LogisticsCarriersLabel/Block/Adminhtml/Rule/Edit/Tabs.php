<?php
/**
 * Rule Edit Tabs
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_Block_Adminhtml_Rule_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('xcarriers_label_rule_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('xcarrierslabel')->__('Rule Information'));
    }

    protected function _beforeToHtml()
    {
        $this->addTab('general', array(
            'label'   => Mage::helper('xcarrierslabel')->__('General Information'),
            'title'   => Mage::helper('xcarrierslabel')->__('General Information'),
            'content' => $this->getLayout()->createBlock(
                'xcarrierslabel/adminhtml_rule_edit_tab_general'
            )->toHtml(),
            'active'  => true,
        ));

        $this->addTab('conditions', array(
            'label'   => Mage::helper('xcarrierslabel')->__('Conditions'),
            'title'   => Mage::helper('xcarrierslabel')->__('Conditions'),
            'content' => $this->getLayout()->createBlock(
                'xcarrierslabel/adminhtml_rule_edit_tab_conditions'
            )->toHtml(),
        ));

        return parent::_beforeToHtml();
    }
}
