<?php
/**
 * Type Grid Block
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Block_Adminhtml_Type_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('shippingRuleTypeGrid');
        $this->setDefaultSort('type_id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('xfeshippingrule/type_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $helper = Mage::helper('xfeshippingrule');

        $this->addColumn('type_id', array(
            'header' => $helper->__('ID'),
            'index'  => 'type_id',
            'width'  => '50px',
        ));

        $this->addColumn('calculation_type', array(
            'header'  => $helper->__('Calculation Type'),
            'index'   => 'calculation_type',
            'type'    => 'options',
            'options' => array(
                'fixed'   => $helper->__('Fixed'),
                'percent' => $helper->__('Percentage'),
                'weight'  => $helper->__('By Weight'),
            ),
            'width'   => '150px',
        ));

        $this->addColumn('nature', array(
            'header'  => $helper->__('Nature'),
            'index'   => 'nature',
            'type'    => 'options',
            'options' => array(
                'normal'  => $helper->__('Normal'),
                'promo'   => $helper->__('Promo'),
                'special' => $helper->__('Special'),
            ),
            'width'   => '120px',
        ));

        $this->addColumn('description', array(
            'header' => $helper->__('Description'),
            'index'  => 'description',
        ));

        $this->addColumn('status', array(
            'header'  => $helper->__('Status'),
            'index'   => 'status',
            'type'    => 'options',
            'options' => $helper->getStatusOptions(),
            'width'   => '100px',
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

        return parent::_prepareColumns();
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }

    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', array('id' => $row->getId()));
    }

    protected function _prepareMassaction()
    {
        $helper = Mage::helper('xfeshippingrule');
        $this->setMassactionIdField('type_id');
        $this->getMassactionBlock()->setFormFieldName('type');
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
