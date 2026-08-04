<?php

class XFE_LabelPrint_Block_Adminhtml_Print_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('xfe_labelprint_grid');
        $this->setDefaultSort('id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    /**
     * Prepare collection.
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('xfe_labelprint/print')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Prepare grid columns.
     */
    protected function _prepareColumns()
    {
        $helper = Mage::helper('xfe_labelprint');

        $this->addColumn('id', array(
            'header' => $helper->__('ID'),
            'index'  => 'id',
            'type'   => 'number',
            'width'  => '70px',
        ));

        $this->addColumn('order_id', array(
            'header' => $helper->__('Order #'),
            'index'  => 'order_id',
            'type'   => 'number',
            'width'  => '110px',
        ));

        $this->addColumn('tracking_number_id', array(
            'header' => $helper->__('Tracking #'),
            'index'  => 'tracking_number_id',
            'type'   => 'number',
            'width'  => '110px',
        ));

        $this->addColumn('path_file', array(
            'header'  => $helper->__('Current File'),
            'index'   => 'path_file',
            'width'   => '240px',
            'renderer' => 'xfe_labelprint/adminhtml_print_grid_renderer_file',
        ));

        $this->addColumn('old_path_file', array(
            'header'  => $helper->__('Previous File'),
            'index'   => 'old_path_file',
            'width'   => '240px',
            'renderer' => 'xfe_labelprint/adminhtml_print_grid_renderer_file',
            'column_css_class' => 'xfe-labelprint-old-file',
        ));

        $this->addColumn('additional_data', array(
            'header' => $helper->__('Additional Data'),
            'index'  => 'additional_data',
            'width'  => '260px',
            'renderer' => 'xfe_labelprint/adminhtml_print_grid_renderer_additional',
        ));

        $this->addColumn('created_at', array(
            'header' => $helper->__('Logged At'),
            'index'  => 'created_at',
            'type'   => 'datetime',
            'width'  => '160px',
        ));

        $this->addColumn('updated_at', array(
            'header' => $helper->__('Updated At'),
            'index'  => 'updated_at',
            'type'   => 'datetime',
            'width'  => '160px',
        ));

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('id');
        $this->getMassactionBlock()->setFormFieldName('ids');

        $this->getMassactionBlock()->addItem('delete', array(
            'label'   => Mage::helper('xfe_labelprint')->__('Delete'),
            'url'     => $this->getUrl('*/*/massDelete'),
            'confirm' => Mage::helper('xfe_labelprint')->__('Delete the selected rows? Files on disk are kept.'),
        ));

        return $this;
    }

    /**
     * @param XFE_LabelPrint_Model_Print $row
     * @return string|false
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/view', array('id' => $row->getId()));
    }

    /**
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }

}