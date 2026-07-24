<?php
/**
 * Adjustment Grid Block
 *
 * @category   Community
 * @package    XFE_InvoiceAdjustment
 */
class XFE_InvoiceAdjustment_Block_Adminhtml_Adjustment_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Init grid
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('invoiceAdjustmentGrid');
        $this->setDefaultSort('adjustment_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    /**
     * Prepare collection
     *
     * @return XFE_InvoiceAdjustment_Block_Adminhtml_Adjustment_Grid
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('invoiceadjustment/adjustment_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Prepare columns
     *
     * @return XFE_InvoiceAdjustment_Block_Adminhtml_Adjustment_Grid
     */
    protected function _prepareColumns()
    {
        $helper = Mage::helper('invoiceadjustment');

        $this->addColumn('adjustment_id', array(
            'header' => $helper->__('ID'),
            'index'  => 'adjustment_id',
            'width'  => '50px',
        ));

        $this->addColumn('adjustment_name', array(
            'header' => $helper->__('Adjustment Name'),
            'index'  => 'adjustment_name',
        ));

        $this->addColumn('adjusted_ht', array(
            'header'   => $helper->__('Adjusted HT'),
            'index'    => 'adjusted_ht',
            'type'     => 'currency',
            'currency' => 'base_currency_code',
            'width'    => '120px',
        ));

        $this->addColumn('adjusted_tva', array(
            'header'   => $helper->__('Adjusted TVA'),
            'index'    => 'adjusted_tva',
            'type'     => 'currency',
            'currency' => 'base_currency_code',
            'width'    => '120px',
        ));

        $this->addColumn('adjusted_ttc', array(
            'header'   => $helper->__('Adjusted TTC'),
            'index'    => 'adjusted_ttc',
            'type'     => 'currency',
            'currency' => 'base_currency_code',
            'width'    => '120px',
        ));

        $this->addColumn('customer_pay', array(
            'header'   => $helper->__('Customer Pay'),
            'index'    => 'customer_pay',
            'type'     => 'currency',
            'currency' => 'base_currency_code',
            'width'    => '120px',
        ));

        $this->addColumn('customer_refund', array(
            'header'   => $helper->__('Customer Refund'),
            'index'    => 'customer_refund',
            'type'     => 'currency',
            'currency' => 'base_currency_code',
            'width'    => '120px',
        ));

        $this->addColumn('status', array(
            'header'  => $helper->__('Status'),
            'index'   => 'status',
            'type'    => 'options',
            'options' => $helper->getStatusOptions(),
            'width'   => '100px',
        ));

        $this->addColumn('reason', array(
            'header' => $helper->__('Reason'),
            'index'  => 'reason',
            'type'   => 'text',
            'width'  => '200px',
            'nl2br'  => true,
        ));

        $this->addColumn('created_at', array(
            'header' => $helper->__('Created At'),
            'index'  => 'created_at',
            'type'   => 'datetime',
            'width'  => '150px',
        ));

        $this->addColumn('action', array(
            'header'  => $helper->__('Action'),
            'width'   => '80px',
            'type'    => 'action',
            'getter'  => 'getId',
            'actions' => array(
                array(
                    'caption' => $helper->__('Edit'),
                    'url'     => array('base' => '*/*/edit'),
                    'field'   => 'id',
                ),
            ),
            'filter'   => false,
            'sortable' => false,
            'is_system' => true,
        ));

        $this->addExportType('*/*/exportCsv', $helper->__('CSV'));

        return parent::_prepareColumns();
    }

    /**
     * Get grid URL
     *
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }

    /**
     * Get row URL
     *
     * @param Varien_Object $row
     * @return string
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', array('id' => $row->getId()));
    }

    /**
     * Prepare mass action
     *
     * @return XFE_InvoiceAdjustment_Block_Adminhtml_Adjustment_Grid
     */
    protected function _prepareMassaction()
    {
        $helper = Mage::helper('invoiceadjustment');

        $this->setMassactionIdField('adjustment_id');
        $this->getMassactionBlock()->setFormFieldName('adjustment');

        $this->getMassactionBlock()->addItem('delete', array(
            'label'   => $helper->__('Delete'),
            'url'     => $this->getUrl('*/*/massDelete'),
            'confirm' => $helper->__('Are you sure?'),
        ));

        $this->getMassactionBlock()->addItem('status', array(
            'label'      => $helper->__('Change Status'),
            'url'        => $this->getUrl('*/*/massStatus'),
            'additional' => array(
                'status' => array(
                    'name'   => 'status',
                    'type'   => 'select',
                    'class'  => 'required-entry',
                    'label'  => $helper->__('Status'),
                    'values' => $helper->getStatusOptions(),
                ),
            ),
        ));

        return $this;
    }
}
