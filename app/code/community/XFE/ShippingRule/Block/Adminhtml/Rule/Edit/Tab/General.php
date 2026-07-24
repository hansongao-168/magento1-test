<?php
/**
 * Rule General Tab
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Block_Adminhtml_Rule_Edit_Tab_General extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    protected function _prepareForm()
    {
        $model = Mage::registry('current_rule');
        $helper = Mage::helper('xfeshippingrule');

        $form = new Varien_Data_Form();
        $this->setForm($form);

        $fieldset = $form->addFieldset('general_fieldset', array(
            'legend' => $helper->__('General Information'),
        ));

        if ($model && $model->getId()) {
            $fieldset->addField('rule_id', 'label', array(
                'label' => $helper->__('ID'),
                'name'  => 'rule_id',
                'value' => $model->getId(),
            ));
        }

        // Type dropdown - load from type model
        $typeCollection = Mage::getResourceModel('xfeshippingrule/type_collection')
            ->addActiveFilter();
        $typeOptions = $typeCollection->toOptionHash();

        $fieldset->addField('type_id', 'select', array(
            'label'    => $helper->__('Type'),
            'name'     => 'type_id',
            'required' => true,
            'class'    => 'required-entry',
            'options'  => $typeOptions,
        ));

        $fieldset->addField('billing_type', 'select', array(
            'label'    => $helper->__('Billing Type'),
            'name'     => 'billing_type',
            'required' => true,
            'class'    => 'required-entry',
            'options'  => $helper->getBillingTypeOptions(),
        ));

        $fieldset->addField('shipping_fee', 'text', array(
            'label'    => $helper->__('Shipping Fee'),
            'name'     => 'shipping_fee',
            'required' => true,
            'class'    => 'required-entry validate-number',
            'note'     => $helper->__('Base fee. For percentage types, this is the percentage value.'),
        ));

        $fieldset->addField('package_min', 'text', array(
            'label'    => $helper->__('Package Min'),
            'name'     => 'package_min',
            'required' => true,
            'class'    => 'required-entry validate-digits',
            'value'    => 1,
        ));

        $fieldset->addField('package_max', 'text', array(
            'label' => $helper->__('Package Max'),
            'name'  => 'package_max',
            'class' => 'validate-digits',
            'note'  => $helper->__('Leave empty for unlimited.'),
        ));

        $fieldset->addField('stack_mode', 'select', array(
            'label'    => $helper->__('Stack Mode'),
            'name'     => 'stack_mode',
            'required' => true,
            'class'    => 'required-entry',
            'options'  => $helper->getStackModeOptions(),
            'note'     => $helper->__('Stackable rules accumulate fees with other matching rules. Non-stackable rules stop at the first match.'),
        ));

        $fieldset->addField('description', 'textarea', array(
            'label' => $helper->__('Description'),
            'name'  => 'description',
            'style' => 'height:80px;',
        ));

        $fieldset->addField('status', 'select', array(
            'label'    => $helper->__('Status'),
            'name'     => 'status',
            'required' => true,
            'class'    => 'required-entry',
            'options'  => $helper->getStatusOptions(),
        ));

        if ($model) {
            $form->setValues($model->getData());
        }

        return parent::_prepareForm();
    }

    /**
     * @return string
     */
    public function getTabLabel()
    {
        return Mage::helper('xfeshippingrule')->__('General Information');
    }

    /**
     * @return string
     */
    public function getTabTitle()
    {
        return Mage::helper('xfeshippingrule')->__('General Information');
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
