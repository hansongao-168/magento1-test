<?php
/**
 * Type Edit Container
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Block_Adminhtml_Type_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId   = 'id';
        $this->_blockGroup = 'xfeshippingrule';
        $this->_controller = 'adminhtml_type';

        parent::__construct();

        $model = Mage::registry('current_type');
        if ($model && $model->getId()) {
            $this->_updateButton('save', 'label', Mage::helper('xfeshippingrule')->__('Save Type'));
            $this->_updateButton('delete', 'label', Mage::helper('xfeshippingrule')->__('Delete Type'));
        } else {
            $this->_updateButton('save', 'label', Mage::helper('xfeshippingrule')->__('Create Type'));
            $this->_removeButton('delete');
        }
    }

    public function getHeaderText()
    {
        $model = Mage::registry('current_type');
        if ($model && $model->getId()) {
            return Mage::helper('xfeshippingrule')->__('Edit Type #%s', $model->getId());
        }
        return Mage::helper('xfeshippingrule')->__('New Type');
    }

    public function getFormActionUrl()
    {
        return $this->getUrl('*/*/save');
    }
}
