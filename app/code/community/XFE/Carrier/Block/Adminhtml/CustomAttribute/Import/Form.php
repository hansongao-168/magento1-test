<?php

/**
 * 自定义属性批量导入 - 表单 Block
 */
class XFE_Carrier_Block_Adminhtml_CustomAttribute_Import_Form
    extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form(array(
            'id'      => 'import_form',
            'action'  => $this->getUrl('*/carrier_customAttribute/importPost'),
            'method'  => 'post',
            'enctype' => 'multipart/form-data',
        ));
        $form->setUseContainer(false);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}
