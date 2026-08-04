<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Logo_Grid_Renderer_Default
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        $ruleId = $row->getData('rule_id');
        if (!$ruleId) {
            return '<span class="grid-severity-notice"><span>' . Mage::helper('xfe_carrier')->__('Default') . '</span></span>';
        }
        return '<span class="grid-severity-minor"><span>' . Mage::helper('xfe_carrier')->__('Rule-bound') . '</span></span>';
    }
}