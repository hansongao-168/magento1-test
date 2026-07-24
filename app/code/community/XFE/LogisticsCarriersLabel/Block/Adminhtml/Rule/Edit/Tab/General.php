<?php
/**
 * Rule General Tab
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_Block_Adminhtml_Rule_Edit_Tab_General extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    protected function _prepareForm()
    {
        $model = Mage::registry('current_rule');
        $helper = Mage::helper('xcarrierslabel');

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

        $fieldset->addField('country_code', 'text', array(
            'label'    => $helper->__('Country Code'),
            'name'     => 'country_code',
            'required' => true,
            'class'    => 'required-entry',
            'note'     => $helper->__('e.g. DE, AT, BE, FR'),
        ));

        $fieldset->addField('country_name', 'text', array(
            'label'    => $helper->__('Country Name'),
            'name'     => 'country_name',
            'required' => true,
            'class'    => 'required-entry',
            'note'     => $helper->__('e.g. Allemagne, Autriche, Belgique'),
        ));

        $fieldset->addField('partner_name', 'text', array(
            'label'    => $helper->__('Partner Name'),
            'name'     => 'partner_name',
            'required' => true,
            'class'    => 'required-entry',
            'note'     => $helper->__('e.g. DPD, Speedy, Postnord, SEUR, BRT'),
        ));

        $fieldset->addField('shipping_company_id', 'text', array(
            'label'    => $helper->__('Shipping Company ID'),
            'name'     => 'shipping_company_id',
            'required' => true,
            'class'    => 'required-entry validate-number',
            'note'     => $helper->__('Line ID'),
        ));

        $fieldset->addField('display_title', 'text', array(
            'label'    => $helper->__('Display Title'),
            'name'     => 'display_title',
            'note'     => $helper->__('e.g. Chrono Classic'),
        ));

        $fieldset->addField('tracking_url_template', 'textarea', array(
            'label' => $helper->__('Tracking URL Template'),
            'name'  => 'tracking_url_template',
            'style' => 'height:60px;',
            'note'  => $helper->__('Use {tracking_number} and {zip} as placeholders. e.g. https://my.dpd.de/myParcel.aspx?parcelno={tracking_number}&zip={zip}'),
        ));

        $fieldset->addField('background_color', 'text', array(
            'label' => $helper->__('Background Color'),
            'name'  => 'background_color',
            'class' => 'input-text',
            'note'  => $helper->__('Hex color code, e.g. #FF5733'),
            'after_element_html' => '<input type="color" name="bg_color_picker" id="bg_color_picker" value="' . $model->getBackgroundColor() . '" onchange="document.getElementById(\'background_color\').value=this.value" style="width:40px;height:30px;padding:0;border:none;cursor:pointer;vertical-align:middle;margin-left:5px;" />',
        ));

        $fieldset->addField('text_color', 'text', array(
            'label' => $helper->__('Text Color'),
            'name'  => 'text_color',
            'class' => 'input-text',
            'note'  => $helper->__('Hex color code, e.g. #FFFFFF'),
            'after_element_html' => '<input type="color" name="text_color_picker" id="text_color_picker" value="' . $model->getTextColor() . '" onchange="document.getElementById(\'text_color\').value=this.value" style="width:40px;height:30px;padding:0;border:none;cursor:pointer;vertical-align:middle;margin-left:5px;" />',
        ));

        $fieldset->addField('status', 'select', array(
            'label'    => $helper->__('Status'),
            'name'     => 'status',
            'required' => true,
            'class'    => 'required-entry',
            'options'  => $helper->getStatusOptions(),
        ));

        // Hidden field for conditions data (JSON)
        $form->addField('groups_data_hidden', 'hidden', array(
            'name'  => 'groups_data',
            'id'    => 'groups_data_hidden',
        ));

        if ($model) {
            // Decode conditions and pass to JS
            $conditionsData = $model->getConditionsData();
            if (!empty($conditionsData)) {
                $form->getElement('groups_data_hidden')
                    ->setValue(Mage::helper('core')->jsonEncode($conditionsData));
            }
        }

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
        return Mage::helper('xcarrierslabel')->__('General Information');
    }

    /**
     * @return string
     */
    public function getTabTitle()
    {
        return Mage::helper('xcarrierslabel')->__('General Information');
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
