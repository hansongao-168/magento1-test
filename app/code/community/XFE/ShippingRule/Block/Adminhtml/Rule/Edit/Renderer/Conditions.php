<?php
/**
 * Conditions Builder Renderer
 * Renders the HTML container for the condition builder JS
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Block_Adminhtml_Rule_Edit_Renderer_Conditions
    extends Varien_Data_Form_Element_Abstract
{
    public function getElementHtml()
    {
        $html = '<div id="condition-builder-container">';
        $html .= '<div id="condition-groups-container"></div>';
        $html .= '<div class="condition-builder-actions" style="margin-top:10px;">';
        $html .= '<button type="button" class="scalable add" id="add-condition-group-btn" onclick="addConditionGroup()">';
        $html .= '<span>' . Mage::helper('xfeshippingrule')->__('+ Add Condition Group') . '</span>';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '<div class="condition-builder-note" style="margin-top:8px;color:#666;font-style:italic;">';
        $html .= Mage::helper('xfeshippingrule')->__('Groups are combined with OR logic. Within each group, conditions use the selected aggregator (ALL = AND, ANY = OR). Use [+ Add Sub-Group] inside a group to create nested condition groups for complex logic.');
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
}
