<?php
/**
 * Rule Edit Container
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Block_Adminhtml_Rule_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId   = 'id';
        $this->_blockGroup = 'xfeshippingrule';
        $this->_controller = 'adminhtml_rule';

        parent::__construct();

        $model = Mage::registry('current_rule');
        if ($model && $model->getId()) {
            $this->_updateButton('save', 'label', Mage::helper('xfeshippingrule')->__('Save Rule'));
            $this->_updateButton('delete', 'label', Mage::helper('xfeshippingrule')->__('Delete Rule'));
        } else {
            $this->_updateButton('save', 'label', Mage::helper('xfeshippingrule')->__('Create Rule'));
            $this->_removeButton('delete');
        }

        // Use custom template with Tabs support
        $this->setTemplate('xfe_shippingrule/rule/edit/container.phtml');
    }

    protected function _prepareLayout()
    {
        // Add Tabs block as child (rendered via container.phtml template)
        $this->setChild(
            'tabs',
            $this->getLayout()->createBlock('xfeshippingrule/adminhtml_rule_edit_tabs', 'tabs')
        );

        return parent::_prepareLayout();
    }

    public function getHeaderText()
    {
        $model = Mage::registry('current_rule');
        if ($model && $model->getId()) {
            return Mage::helper('xfeshippingrule')->__('Edit Rule #%s', $model->getId());
        }
        return Mage::helper('xfeshippingrule')->__('New Rule');
    }

    public function getFormActionUrl()
    {
        return $this->getUrl('*/*/save');
    }
}
