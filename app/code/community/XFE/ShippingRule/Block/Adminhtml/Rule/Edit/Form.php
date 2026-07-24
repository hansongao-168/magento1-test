<?php
/**
 * Rule Edit Form (pure form container)
 *
 * This is the outer <form> container for the Tab system.
 * Only contains the form tag and the persistent hidden field for conditions data.
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Block_Adminhtml_Rule_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $model = Mage::registry('current_rule');

        $form = new Varien_Data_Form(array(
            'id'      => 'edit_form',
            'action'  => $this->getUrl('*/*/save', array('id' => $this->getRequest()->getParam('id'))),
            'method'  => 'post',
            'enctype' => 'multipart/form-data',
        ));
        $form->setUseContainer(true);
        $this->setForm($form);

        // Hidden field for conditions data (JSON) - placed in outer form so it's always submitted
        $form->addField('groups_data_hidden', 'hidden', array(
            'name'  => 'groups_data',
            'id'    => 'groups_data_hidden',
        ));

        if ($model) {
            $conditionsData = $model->getConditionsData();
            if (!empty($conditionsData)) {
                $form->getElement('groups_data_hidden')
                    ->setValue(Mage::helper('core')->jsonEncode($conditionsData));
            }
        }

        return parent::_prepareForm();
    }
}
