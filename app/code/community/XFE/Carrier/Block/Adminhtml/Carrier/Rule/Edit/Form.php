<?php

/**
 * Carrier Rule Edit Form (pure form container)
 *
 * This is the outer <form> container for the Tab system, mirroring
 * XFE_ShippingRule_Block_Adminhtml_Rule_Edit_Form:
 *   - The form tag itself (id `edit_form`, POST to saveRule)
 *   - The persistent hidden field `groups_data_hidden` (used by the
 *     JS condition-builder to persist its JSON state across form
 *     submits)
 *
 * All visible fields live in the Tab blocks (see Tab/General.php and
 * Tab/Conditions.php). The varienTabs JS moves each tab's content
 * into this <form>.
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Rule_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $helper = Mage::helper('xfe_carrier');
        $rule   = Mage::registry('xfe_carrier_rule_data');

        $form = new Varien_Data_Form(array(
            'id'      => 'edit_form',
            'action'  => $this->getUrl('*/*/saveRule'),
            'method'  => 'post',
            'enctype' => 'multipart/form-data',
        ));
        $form->setUseContainer(true);
        $this->setForm($form);

        // Hidden field for conditions data (JSON) - placed in the outer
        // form so it's always submitted. The JS condition-builder
        // updates this field on submit and reads it on init.
        $form->addField('groups_data_hidden', 'hidden', array(
            'name' => 'groups_data',
            'id'   => 'groups_data_hidden',
        ));

        if ($rule && $rule->getId()) {
            $existingJson = $rule->getConditionsDataJson();
            if ($existingJson) {
                $form->getElement('groups_data_hidden')->setValue($existingJson);
            }
        }

        return parent::_prepareForm();
    }
}