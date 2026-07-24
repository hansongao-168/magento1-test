<?php
/**
 * Rule Grid Block
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_Block_Adminhtml_Rule_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('logisticsCarriersLabelRuleGrid');
        $this->setDefaultSort('rule_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('xcarrierslabel/rule_collection');

        // Add condition count via subquery
        $collection->getSelect()->joinLeft(
            array('cg' => $collection->getTable('xcarrierslabel/condition_group')),
            'main_table.rule_id = cg.rule_id',
            array()
        );
        $collection->getSelect()->columns(
            array('condition_count' => new Zend_Db_Expr('COUNT(DISTINCT cg.group_id)'))
        );
        $collection->getSelect()->group('main_table.rule_id');

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $helper = Mage::helper('xcarrierslabel');

        $this->addColumn('rule_id', array(
            'header' => $helper->__('ID'),
            'index'  => 'rule_id',
            'width'  => '50px',
        ));

        $this->addColumn('country_code', array(
            'header' => $helper->__('Country Code'),
            'index'  => 'country_code',
            'width'  => '100px',
        ));

        $this->addColumn('country_name', array(
            'header' => $helper->__('Country Name'),
            'index'  => 'country_name',
            'width'  => '150px',
        ));

        $this->addColumn('partner_name', array(
            'header' => $helper->__('Partner'),
            'index'  => 'partner_name',
            'width'  => '100px',
        ));

        $this->addColumn('shipping_company_id', array(
            'header' => $helper->__('Shipping Company ID'),
            'index'  => 'shipping_company_id',
            'width'  => '100px',
        ));

        $this->addColumn('background_color', array(
            'header' => $helper->__('Background Color'),
            'index'  => 'background_color',
            'width'  => '120px',
            'filter' => false,
            'sortable' => false,
            'renderer' => new XFE_LogisticsCarriersLabel_Block_Adminhtml_Rule_Grid_Renderer_Color(),
        ));

        $this->addColumn('text_color', array(
            'header' => $helper->__('Text Color'),
            'index'  => 'text_color',
            'width'  => '100px',
            'filter' => false,
            'sortable' => false,
            'renderer' => new XFE_LogisticsCarriersLabel_Block_Adminhtml_Rule_Grid_Renderer_Color(),
        ));

        $this->addColumn('display_title', array(
            'header' => $helper->__('Display Title'),
            'index'  => 'display_title',
            'width'  => '150px',
        ));

        $this->addColumn('condition_count', array(
            'header' => $helper->__('Conditions'),
            'index'  => 'condition_count',
            'width'  => '80px',
            'filter' => false,
        ));

        $this->addColumn('status', array(
            'header'  => $helper->__('Status'),
            'index'   => 'status',
            'type'    => 'options',
            'options' => $helper->getStatusOptions(),
            'width'   => '80px',
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
        $helper = Mage::helper('xcarrierslabel');
        $this->setMassactionIdField('rule_id');
        $this->getMassactionBlock()->setFormFieldName('rule');

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
