<?php
/**
 * Adjustment Edit Container
 *
 * @category   Community
 * @package    XFE_InvoiceAdjustment
 */
class XFE_InvoiceAdjustment_Block_Adminhtml_Adjustment_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    /**
     * Init container
     */
    public function __construct()
    {
        $this->_objectId   = 'id';
        $this->_blockGroup = 'invoiceadjustment';
        $this->_controller = 'adminhtml_adjustment';

        parent::__construct();

        $model = Mage::registry('current_adjustment');
        if ($model && $model->getId()) {
            $this->_updateButton('save', 'label', Mage::helper('invoiceadjustment')->__('Save Adjustment'));
            $this->_updateButton('delete', 'label', Mage::helper('invoiceadjustment')->__('Delete Adjustment'));

            if (!$model->canEdit()) {
                $this->_removeButton('save');
                $this->_removeButton('delete');
            }
        } else {
            $this->_updateButton('save', 'label', Mage::helper('invoiceadjustment')->__('Create Adjustment'));
            $this->_removeButton('delete');
        }
    }

    /**
     * Get header text
     *
     * @return string
     */
    public function getHeaderText()
    {
        $model = Mage::registry('current_adjustment');
        if ($model && $model->getId()) {
            return Mage::helper('invoiceadjustment')->__(
                'Edit Adjustment #%s',
                $model->getId()
            );
        }
        return Mage::helper('invoiceadjustment')->__('New Adjustment');
    }

    /**
     * Get form action URL
     *
     * @return string
     */
    public function getFormActionUrl()
    {
        return $this->getUrl('*/*/save');
    }
}
