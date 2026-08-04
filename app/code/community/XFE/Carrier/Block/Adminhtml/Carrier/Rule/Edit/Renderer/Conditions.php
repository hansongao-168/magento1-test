<?php
/**
 * Conditions Builder Renderer (Carrier module)
 *
 * Renders the HTML container that hosts the condition-builder JS
 * shipped under skin/xfe_shippingrule/js/condition-builder.js.
 *
 * Mirrors XFE_ShippingRule_Block_Adminhtml_Rule_Edit_Renderer_Conditions
 * so the existing JS works against both modules without modification.
 * The JS reads attribute / operator option lists from hidden fields
 * with fixed IDs (`attr_options_json`, `op_options_json`,
 * `numeric_op_json`, `string_op_json`) - those are emitted by the
 * parent Edit form, not by this renderer.
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Rule_Edit_Renderer_Conditions
    extends Varien_Data_Form_Element_Abstract
{
    public function getElementHtml()
    {
        $helper = Mage::helper('xfe_carrier');
        $html  = '<div id="condition-builder-container">';
        $html .= '<div id="condition-groups-container"></div>';
        $html .= '<div class="condition-builder-actions" style="margin-top:10px;">';
        $html .= '<button type="button" class="scalable add" id="add-condition-group-btn" onclick="addConditionGroup()">';
        $html .= '<span>' . $helper->__('+ 添加条件组') . '</span>';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '<div class="condition-builder-note" style="margin-top:8px;color:#666;font-style:italic;">';
        $html .= $helper->__('多个条件组之间用「或（OR）」组合；同一个条件组内的多个条件按聚合器（全部=AND / 任意=OR）组合。在组内使用 [+ 添加子组] 可以嵌套条件组以表达更复杂的逻辑。');
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
}