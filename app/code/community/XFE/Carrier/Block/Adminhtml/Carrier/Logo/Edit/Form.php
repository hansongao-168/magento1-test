<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Logo_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $helper = Mage::helper('xfe_carrier');
        $logo   = Mage::registry('xfe_carrier_logo_data');
        $carrier = Mage::registry('xfe_carrier_data');
        $isEdit = $logo && $logo->getId();

        // ---------- Form -----------------------------------------------
        $form = new Varien_Data_Form(array(
            'id'      => 'edit_form',
            'action'  => $this->getUrl('*/*/saveLogo'),
            'method'  => 'post',
            'enctype' => 'multipart/form-data',
        ));

        $form->setUseContainer(true);

        // ---------- Fieldset: Logo Info --------------------------------
        $fieldset = $form->addFieldset('logo_fieldset', array(
            'legend' => $helper->__('Logo Info'),
        ));

        if ($isEdit) {
            $fieldset->addField('logo_id', 'hidden', array(
                'name' => 'logo_id',
            ));
        }

        $fieldset->addField('carrier_id', 'hidden', array(
            'name' => 'carrier_id',
        ));

        $fieldset->addField('logo_label', 'text', array(
            'name'     => 'logo_label',
            'label'    => $helper->__('Logo Name'),
            'title'    => $helper->__('Logo Name'),
            'required' => true,
        ));

        $fieldset->addField('logo_type', 'select', array(
            'name'   => 'logo_type',
            'label'  => $helper->__('Logo Type'),
            'title'  => $helper->__('Logo Type'),
            'values' => array(
                array('value' => 'main',   'label' => $helper->__('Main Logo')),
                array('value' => 'mobile', 'label' => $helper->__('Mobile Logo')),
                array('value' => 'alt',    'label' => $helper->__('Alternate Logo')),
            ),
        ));

        $fieldset->addField('logo', 'file', array(
            'name'     => 'logo',
            'label'    => $isEdit ? $helper->__('Replace File') : $helper->__('Select File'),
            'title'    => $isEdit ? $helper->__('Replace File') : $helper->__('Select File'),
            'required' => !$isEdit,
            'note'     => $isEdit ? $helper->__('Leave empty to keep the current file.') : '',
        ));

        if ($isEdit && $logo->getPath()) {
            $mediaUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
            $imgUrl   = $mediaUrl . $logo->getPath();
            $fieldset->addField('preview_html', 'note', array(
                'label' => $helper->__('Current'),
                'text'  => '<img src="' . $this->escapeUrl($imgUrl) . '" alt="' . $this->escapeHtml($logo->getLabel() ?: 'Logo') . '" style="max-width:200px; max-height:80px;" />',
            ));
        }

        if ($logo) {
            $form->setValues($logo->getData());
        }

        // Override logo_label field value (the model stores it as 'label')
        if ($logo && $logo->getLabel()) {
            $form->getElement('logo_label')->setValue($logo->getLabel());
        }
        if ($logo && $logo->getLogoType()) {
            $form->getElement('logo_type')->setValue($logo->getLogoType());
        }

                // Force carrier_id from registered carrier data (not on logo model)
        $carrier = Mage::registry('xfe_carrier_data');
        if ($carrier && $carrier->getId()) {
            $form->getElement('carrier_id')->setValue($carrier->getId());
        }
$form->addField('form_key', 'hidden', array(
            'name'  => 'form_key',
            'value' => Mage::getSingleton('core/session')->getFormKey(),
        ));

        $this->setForm($form);

        return parent::_prepareForm();
    }

    protected function _toHtml()
    {
        $html   = parent::_toHtml();
        $helper = Mage::helper('xfe_carrier');
        $logo   = Mage::registry('xfe_carrier_logo_data');

        $html .= '<div class="entry-edit xfe-carrier-logo-rules-section" style="margin-top:20px;">';
        $html .= '<div class="entry-edit-head">';
        $html .= '<h4 class="icon-head head-edit-form fieldset-legend">'
              . $helper->__('Rule Settings') . '</h4>';
        $html .= '</div>';
        $html .= '<div class="fieldset">';
        $html .= '<p class="note" style="margin:0 0 10px 0;">'
              . $helper->__('Rules bound to this logo act as matching rules. If no rule is bound, this logo is treated as the default logo.')
              . '</p>';
        $html .= $this->_renderLogoRulesSection($logo);
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    protected function _renderLogoRulesSection($logo)
    {
        $helper = Mage::helper('xfe_carrier');

        if (!$logo || !$logo->getId()) {
            return '<p style="color:#999;font-style:italic;padding:6px 0;">'
                . $helper->__('Please save the logo first, then bind rules to it.')
                . '</p>';
        }

        return $this->getLayout()->createBlock(
            'xfe_carrier/adminhtml_carrier_edit_tab_logo_rules_grid'
        )->toHtml();
    }
}