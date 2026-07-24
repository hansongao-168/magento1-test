<?php
/**
 * Rule Conditions Tab
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Block_Adminhtml_Rule_Edit_Tab_Conditions extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    protected function _prepareForm()
    {
        $helper = Mage::helper('xfeshippingrule');

        $form = new Varien_Data_Form();
        $this->setForm($form);

        $fieldset = $form->addFieldset('conditions_fieldset', array(
            'legend' => $helper->__('Condition Groups'),
        ));

        $fieldset->addType('conditions_builder', 'XFE_ShippingRule_Block_Adminhtml_Rule_Edit_Renderer_Conditions');

        $fieldset->addField('conditions_builder', 'conditions_builder', array(
            'label' => '',
            'name'  => 'conditions_builder',
        ));

        // Add condition attribute options for JS
        $attrOptions = $helper->getConditionAttributeOptions();
        $operatorOptions = $helper->getOperatorOptions();
        $numericOperators = $helper->getNumericOperators();
        $stringOperators = $helper->getStringOperators();

        $fieldset->addField('attr_options_json', 'hidden', array(
            'name'   => 'attr_options_json',
            'value'  => Mage::helper('core')->jsonEncode($attrOptions),
        ));

        $fieldset->addField('op_options_json', 'hidden', array(
            'name'  => 'op_options_json',
            'value' => Mage::helper('core')->jsonEncode($operatorOptions),
        ));

        $fieldset->addField('numeric_op_json', 'hidden', array(
            'name'  => 'numeric_op_json',
            'value' => Mage::helper('core')->jsonEncode($numericOperators),
        ));

        $fieldset->addField('string_op_json', 'hidden', array(
            'name'  => 'string_op_json',
            'value' => Mage::helper('core')->jsonEncode($stringOperators),
        ));

        return parent::_prepareForm();
    }

    /**
     * @return string
     */
    public function getTabLabel()
    {
        return Mage::helper('xfeshippingrule')->__('Conditions');
    }

    /**
     * @return string
     */
    public function getTabTitle()
    {
        return Mage::helper('xfeshippingrule')->__('Conditions');
    }

    /**
     * @return bool
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isHidden()
    {
        return false;
    }
}
