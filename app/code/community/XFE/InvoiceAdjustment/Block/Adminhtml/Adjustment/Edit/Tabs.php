<?php
/**
 * Adjustment Edit Tabs
 *
 * @category   Community
 * @package    XFE_InvoiceAdjustment
 */
class XFE_InvoiceAdjustment_Block_Adminhtml_Adjustment_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    /**
     * Init tabs
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('invoice_adjustment_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('invoiceadjustment')->__('Adjustment Information'));
    }

    /**
     * Prepare tabs
     *
     * @return XFE_InvoiceAdjustment_Block_Adminhtml_Adjustment_Edit_Tabs
     */
    protected function _beforeToHtml()
    {
        $this->addTab('general', array(
            'label'   => Mage::helper('invoiceadjustment')->__('General Information'),
            'title'   => Mage::helper('invoiceadjustment')->__('General Information'),
            'content' => $this->getLayout()->createBlock(
                'invoiceadjustment/adminhtml_adjustment_edit_tab_general'
            )->toHtml(),
            'active'  => true,
        ));

        $model = Mage::registry('current_adjustment');
        if ($model && $model->getId()) {
            $this->addTab('orders', array(
                'label'   => Mage::helper('invoiceadjustment')->__('Orders'),
                'title'   => Mage::helper('invoiceadjustment')->__('Orders'),
                'content' => $this->getLayout()->createBlock(
                    'invoiceadjustment/adminhtml_adjustment_edit_tab_orders'
                )->toHtml(),
            ));
        }

        return parent::_beforeToHtml();
    }
}
