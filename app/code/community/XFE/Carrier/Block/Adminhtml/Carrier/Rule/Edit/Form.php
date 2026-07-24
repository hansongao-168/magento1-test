<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Rule_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $helper = Mage::helper('xfe_carrier');
        $rule   = Mage::registry('xfe_carrier_rule_data');

        $form = new Varien_Data_Form(array(
            'id'     => 'edit_form',
            'action' => $this->getUrl('*/*/saveRule'),
            'method' => 'post',
        ));

        $form->setUseContainer(true);

        $fieldset = $form->addFieldset('base_fieldset', array(
            'legend' => $helper->__('规则信息'),
        ));

        if ($rule && $rule->getId()) {
            $fieldset->addField('rule_id', 'hidden', array(
                'name' => 'rule_id',
            ));
        }

        $fieldset->addField('carrier_id', 'hidden', array(
            'name' => 'carrier_id',
        ));

        $fieldset->addField('name', 'text', array(
            'name'     => 'name',
            'label'    => $helper->__('规则名称'),
            'title'    => $helper->__('规则名称'),
            'required' => true,
        ));

        // Module code dropdown from carrier_modules.xml
        $moduleOptions = $helper->getCarrierModuleSelectOptions();
        $moduleSelectOptions = array(array('value' => '', 'label' => $helper->__('-- 请选择 --')));
        foreach ($moduleOptions as $code => $label) {
            $moduleSelectOptions[] = array('value' => $code, 'label' => $label);
        }
        $fieldset->addField('module_code', 'select', array(
            'name'   => 'module_code',
            'label'  => $helper->__('所属模块'),
            'title'  => $helper->__('所属模块'),
            'values' => $moduleSelectOptions,
        ));

        $fieldset->addField('is_active', 'select', array(
            'name'   => 'is_active',
            'label'  => $helper->__('状态'),
            'title'  => $helper->__('状态'),
            'values' => Mage::getSingleton('xfe_carrier/source_status')->toOptionArray(),
        ));

        $fieldset->addField('is_cancel_on_failure', 'select', array(
            'name'   => 'is_cancel_on_failure',
            'label'  => $helper->__('失败取消'),
            'title'  => $helper->__('失败取消'),
            'note'   => $helper->__('匹配失败时取消该规则'),
            'values' => array(
                array('value' => 0, 'label' => $helper->__('否')),
                array('value' => 1, 'label' => $helper->__('是')),
            ),
        ));

        $fieldset->addField('sort_order', 'text', array(
            'name'  => 'sort_order',
            'label' => $helper->__('排序'),
            'title' => $helper->__('排序'),
            'class' => 'validate-number',
            'note'  => $helper->__('越小越靠前'),
        ));

        // Condition builder (Ajax-loaded via conditions block)
        $fieldset->addField('conditions_data', 'hidden', array(
            'name' => 'conditions_data',
        ));

        if ($rule) {
            $form->setValues($rule->getData());
        }

        $this->setForm($form);

        return parent::_prepareForm();
    }
}
