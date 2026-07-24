<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Logo_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $helper = Mage::helper('xfe_carrier');

        $form = new Varien_Data_Form(array(
            'id'      => 'edit_form',
            'action'  => $this->getUrl('*/*/saveLogo', array(
                'id' => Mage::registry('xfe_carrier_data') ? Mage::registry('xfe_carrier_data')->getId() : 0
            )),
            'method'  => 'post',
            'enctype' => 'multipart/form-data',
        ));

        $form->setUseContainer(true);

        $fieldset = $form->addFieldset('logo_fieldset', array(
            'legend' => $helper->__('Logo 信息'),
        ));

        $fieldset->addField('logo_label', 'text', array(
            'name'     => 'logo_label',
            'label'    => $helper->__('Logo 名称'),
            'title'    => $helper->__('Logo 名称'),
            'required' => true,
        ));

        $fieldset->addField('logo_type', 'select', array(
            'name'   => 'logo_type',
            'label'  => $helper->__('Logo type'),
            'title'  => $helper->__('Logo type'),
            'values' => array(
                array('value' => 'main',  'label' => $helper->__('Main Logo')),
                array('value' => 'mobile', 'label' => $helper->__('Mobile Logo')),
                array('value' => 'alt',   'label' => $helper->__('Alternate Logo')),
            ),
        ));

        // Build rule options from rules of the current carrier that target logos.
        $carrier = Mage::registry('xfe_carrier_data');
        $ruleOptions = array(array('value' => '', 'label' => $helper->__('-- none --')));
        if ($carrier && $carrier->getId()) {
            $rules = Mage::getModel('xfe_carrier/carrier_rule')->getCollection()
                ->addFieldToFilter('carrier_id', $carrier->getId())
                ->addFieldToFilter('module_code', 'logo')
                ->addFieldToFilter('status', 1)
                ->setOrder('sort_order', 'ASC');
            foreach ($rules as $r) {
                $ruleOptions[] = array(
                    'value' => (int)$r->getId(),
                    'label' => $r->getName() ? $r->getName() : ('Rule #' . $r->getId()),
                );
            }
        }

        $fieldset->addField('rule_id', 'select', array(
            'name'   => 'rule_id',
            'label'  => $helper->__('Bound rule'),
            'title'  => $helper->__('Bound rule'),
            'values' => $ruleOptions,
            'note'   => $helper->__('Bind this logo to a rule; the rule decides when to display it.'),
        ));

        $fieldset->addField('logo', 'file', array(
            'name'     => 'logo',
            'label'    => $helper->__('选择文件'),
            'title'    => $helper->__('选择文件'),
            'required' => true,
        ));

        $form->addField('form_key', 'hidden', array(
            'name'  => 'form_key',
            'value' => Mage::getSingleton('core/session')->getFormKey(),
        ));

        $this->setForm($form);

        return parent::_prepareForm();
    }
}
