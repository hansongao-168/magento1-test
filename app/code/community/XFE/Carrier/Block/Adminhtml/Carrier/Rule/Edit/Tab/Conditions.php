<?php

/**
 * Carrier Rule Edit - Conditions Tab
 *
 * Hosts the conditions builder - same contract as
 * XFE_ShippingRule_Block_Adminhtml_Rule_Edit_Tab_Conditions:
 *   - fieldset: conditions_fieldset (legend: "适合条件 (Conditions)")
 *   - element:  conditions_builder (rendered by
 *              XFE_Carrier_Block_Adminhtml_Carrier_Rule_Edit_Renderer_Conditions)
 *   - hidden:   attr_options_json / op_options_json / numeric_op_json /
 *              string_op_json - consumed by the JS in
 *              skin/xfe_shippingrule/js/condition-builder.js (module-agnostic)
 *
 * The JS is registered globally in the layout XML
 * (`adminhtml_carrier_editrule` adds the JS to <head>).
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Rule_Edit_Tab_Conditions
    extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    protected function _prepareForm()
    {
        $helper = Mage::helper('xfe_carrier');

        $form = new Varien_Data_Form();
        $this->setForm($form);

        $fieldset = $form->addFieldset('conditions_fieldset', array(
            'legend' => $helper->__('适合条件 (Conditions)'),
        ));

        $fieldset->addType(
            'conditions_builder',
            'XFE_Carrier_Block_Adminhtml_Carrier_Rule_Edit_Renderer_Conditions'
        );

        $fieldset->addField('conditions_builder', 'conditions_builder', array(
            'label' => '',
            'name'  => 'conditions_builder',
        ));

        $attrOptions     = $helper->getConditionAttributeOptions();
        $operatorOptions = $helper->getOperatorOptions();
        $numericOps      = $helper->getNumericOperators();
        $stringOps       = $helper->getStringOperators();

        $fieldset->addField('attr_options_json', 'hidden', array(
            'name'  => 'attr_options_json',
            'value' => Mage::helper('core')->jsonEncode($attrOptions),
        ));
        $fieldset->addField('op_options_json', 'hidden', array(
            'name'  => 'op_options_json',
            'value' => Mage::helper('core')->jsonEncode($operatorOptions),
        ));
        $fieldset->addField('numeric_op_json', 'hidden', array(
            'name'  => 'numeric_op_json',
            'value' => Mage::helper('core')->jsonEncode($numericOps),
        ));
        $fieldset->addField('string_op_json', 'hidden', array(
            'name'  => 'string_op_json',
            'value' => Mage::helper('core')->jsonEncode($stringOps),
        ));

        // 属性 → 类型元数据，供共享 JS 决定下拉项集合与值输入框禁用。
        // 1.0.11+ 新增。
        $fieldset->addField('attribute_meta_json', 'hidden', array(
            'name'  => 'attribute_meta_json',
            'value' => Mage::helper('core')->jsonEncode($helper->getAttributeTypeMap()),
        ));

        return parent::_prepareForm();
    }

    public function getTabLabel()
    {
        return Mage::helper('xfe_carrier')->__('适合条件 (Conditions)');
    }

    public function getTabTitle()
    {
        return Mage::helper('xfe_carrier')->__('适合条件 (Conditions)');
    }

    public function canShowTab()
    {
        return true;
    }

    public function isHidden()
    {
        return false;
    }
}