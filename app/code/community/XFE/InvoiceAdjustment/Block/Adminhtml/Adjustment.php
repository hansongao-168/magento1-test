<?php
/**
 * Adjustment Grid Container
 *
 * @category   Community
 * @package    XFE_InvoiceAdjustment
 */
class XFE_InvoiceAdjustment_Block_Adminhtml_Adjustment extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * Init container
     */
    public function __construct()
    {
        $this->_controller = 'adminhtml_adjustment';
        $this->_blockGroup = 'invoiceadjustment';
        $this->_headerText = Mage::helper('invoiceadjustment')->__('Invoice Adjustments');
        $this->_addButtonLabel = Mage::helper('invoiceadjustment')->__('Add Adjustment');

        parent::__construct();
    }
}
