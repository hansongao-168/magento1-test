<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * Prepare form - form tag and content rendered by container.phtml + tabs.
     * Tab content divs are moved into edit_form by varienTabs JS.
     */
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form(array(
            'id'      => 'edit_form',
            'action'  => $this->getUrl('*/*/save', array('id' => $this->getRequest()->getParam('id'))),
            'method'  => 'post',
            'enctype' => 'multipart/form-data',
        ));

        $form->setUseContainer(false);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Prevent duplicate content rendering.
     * All form content is handled by tabs + container.phtml.
     */
    protected function _toHtml()
    {
        return '';
    }
}
