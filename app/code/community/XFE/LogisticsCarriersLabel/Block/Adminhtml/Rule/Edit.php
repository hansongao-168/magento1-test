<?php
/**
 * Rule Edit Container
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_Block_Adminhtml_Rule_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId   = 'id';
        $this->_blockGroup = 'xcarrierslabel';
        $this->_controller = 'adminhtml_rule';

        parent::__construct();

        $model = Mage::registry('current_rule');
        if ($model && $model->getId()) {
            $this->_updateButton('save', 'label', Mage::helper('xcarrierslabel')->__('Save Rule'));
            $this->_updateButton('delete', 'label', Mage::helper('xcarrierslabel')->__('Delete Rule'));
        } else {
            $this->_updateButton('save', 'label', Mage::helper('xcarrierslabel')->__('Create Rule'));
            $this->_removeButton('delete');
        }
    }

    public function getHeaderText()
    {
        $model = Mage::registry('current_rule');
        if ($model && $model->getId()) {
            return Mage::helper('xcarrierslabel')->__('Edit Rule #%s', $model->getId());
        }
        return Mage::helper('xcarrierslabel')->__('New Rule');
    }

    public function getFormActionUrl()
    {
        return $this->getUrl('*/*/save');
    }
}
