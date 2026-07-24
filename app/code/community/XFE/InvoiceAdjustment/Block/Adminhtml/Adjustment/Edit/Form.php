<?php
/**
 * Adjustment Edit Form
 *
 * @category   Community
 * @package    XFE_InvoiceAdjustment
 */
class XFE_InvoiceAdjustment_Block_Adminhtml_Adjustment_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * Prepare form
     *
     * @return XFE_InvoiceAdjustment_Block_Adminhtml_Adjustment_Edit_Form
     */
    protected function _prepareForm()
    {
        $model  = Mage::registry('current_adjustment');
        $helper = Mage::helper('invoiceadjustment');

        $form = new Varien_Data_Form(array(
            'id'     => 'edit_form',
            'action' => $this->getUrl('*/*/save', array('id' => $this->getRequest()->getParam('id'))),
            'method' => 'post',
        ));

        $form->setUseContainer(true);
        $this->setForm($form);

        $fieldset = $form->addFieldset('general_fieldset', array(
            'legend' => $helper->__('General Information'),
        ));

        if ($model && $model->getId()) {
            $fieldset->addField('adjustment_id', 'label', array(
                'label' => $helper->__('ID'),
                'name'  => 'adjustment_id',
                'value' => $model->getId(),
            ));
        }

        $fieldset->addField('adjustment_name', 'text', array(
            'label'    => $helper->__('Adjustment Name'),
            'name'     => 'adjustment_name',
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

        $fieldset->addField('reason', 'textarea', array(
            'label' => $helper->__('Reason'),
            'name'  => 'reason',
            'style' => 'height:100px;',
        ));

        // Summary fields (read-only)
        if ($model && $model->getId()) {
            $fieldset = $form->addFieldset('summary_fieldset', array(
                'legend' => $helper->__('Amount Summary (auto-calculated from orders)'),
            ));

            $fieldset->addField('adjusted_ht', 'label', array(
                'label' => $helper->__('Adjusted HT'),
                'name'  => 'adjusted_ht',
                'value' => $model->getAdjustedHt(),
            ));

            $fieldset->addField('adjusted_tva', 'label', array(
                'label' => $helper->__('Adjusted TVA'),
                'name'  => 'adjusted_tva',
                'value' => $model->getAdjustedTva(),
            ));

            $fieldset->addField('adjusted_ttc', 'label', array(
                'label' => $helper->__('Adjusted TTC'),
                'name'  => 'adjusted_ttc',
                'value' => $model->getAdjustedTtc(),
            ));

            $fieldset->addField('customer_pay', 'label', array(
                'label' => $helper->__('Customer Pay'),
                'name'  => 'customer_pay',
                'value' => $model->getCustomerPay(),
            ));

            $fieldset->addField('customer_refund', 'label', array(
                'label' => $helper->__('Customer Refund'),
                'name'  => 'customer_refund',
                'value' => $model->getCustomerRefund(),
            ));
        }

        if ($model) {
            $form->setValues($model->getData());
        }

        return parent::_prepareForm();
    }
}
