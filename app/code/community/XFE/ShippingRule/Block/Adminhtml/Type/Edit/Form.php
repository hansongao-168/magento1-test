<?php
/**
 * Type Edit Form
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Block_Adminhtml_Type_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $model = Mage::registry('current_type');
        $helper = Mage::helper('xfeshippingrule');

        $form = new Varien_Data_Form(array(
            'id'     => 'edit_form',
            'action' => $this->getUrl('*/*/save', array('id' => $this->getRequest()->getParam('id'))),
            'method' => 'post',
        ));
        $form->setUseContainer(true);
        $this->setForm($form);

        $fieldset = $form->addFieldset('type_fieldset', array(
            'legend' => $helper->__('Type Information'),
        ));

        $fieldset->addField('calculation_type', 'select', array(
            'label'    => $helper->__('Calculation Type'),
            'name'     => 'calculation_type',
            'required' => true,
            'class'    => 'required-entry',
            'options'  => array(
                'fixed'   => $helper->__('Fixed'),
                'percent' => $helper->__('Percentage'),
                'weight'  => $helper->__('By Weight'),
            ),
        ));

        $fieldset->addField('nature', 'select', array(
            'label'    => $helper->__('Nature'),
            'name'     => 'nature',
            'required' => true,
            'class'    => 'required-entry',
            'options'  => array(
                'normal'  => $helper->__('Normal'),
                'promo'   => $helper->__('Promo'),
                'special' => $helper->__('Special'),
            ),
        ));

        $fieldset->addField('description', 'text', array(
            'label' => $helper->__('Description'),
            'name'  => 'description',
            'required' => true,
            'class'    => 'required-entry',
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
}
