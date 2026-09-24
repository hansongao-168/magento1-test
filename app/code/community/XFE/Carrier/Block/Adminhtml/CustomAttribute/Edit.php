<?php

/**
 * 自定义属性编辑 Container
 *
 * 关联文档: docs/architecture/carrier-global-custom-field-defs.md
 */
class XFE_Carrier_Block_Adminhtml_CustomAttribute_Edit
    extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_customAttribute';
        $this->_blockGroup = 'xfe_carrier';
        $this->_mode = 'edit';

        $helper = Mage::helper('xfe_carrier');
        $model  = Mage::registry('xfe_carrier_custom_attribute_data');
        $this->_headerText = $model && $model->getId()
            ? $helper->__('编辑自定义属性: %s', $model->getData('field_key'))
            : $helper->__('新增自定义属性');

        parent::__construct();
        $this->_updateButton('save', 'label', $helper->__('保存'));
        $this->_updateButton('delete', 'label', $helper->__('停用'));
    }
}
