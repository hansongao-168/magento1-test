<?php
/**
 * Rule Grid Block
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Block_Adminhtml_Rule_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('shippingRuleGrid');
        $this->setDefaultSort('rule_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('xfeshippingrule/rule_collection')
            ->joinTypeData();

        // Add condition count via subquery
        $collection->getSelect()->joinLeft(
            array('cg' => $collection->getTable('xfeshippingrule/condition_group')),
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
        $helper = Mage::helper('xfeshippingrule');

        $this->addColumn('rule_id', array(
            'header' => $helper->__('ID'),
            'index'  => 'rule_id',
            'width'  => '50px',
        ));

        $this->addColumn('type_description', array(
            'header' => $helper->__('Type'),
            'index'  => 'type_description',
            'width'  => '150px',
        ));

        $this->addColumn('billing_type', array(
            'header'  => $helper->__('Billing Type'),
            'index'   => 'billing_type',
            'type'    => 'options',
            'options' => $helper->getBillingTypeOptions(),
            'width'   => '120px',
        ));

        $this->addColumn('shipping_fee', array(
            'header'   => $helper->__('Shipping Fee'),
            'index'    => 'shipping_fee',
            'type'     => 'currency',
            'currency' => 'base_currency_code',
            'width'    => '100px',
        ));

        $this->addColumn('package_range', array(
            'header'         => $helper->__('Package Range'),
            'index'          => 'rule_id',
            'filter'         => false,
            'sortable'       => false,
            'width'          => '100px',
            'frame_callback' => array($this, 'decoratePackageRange'),
        ));

        $this->addColumn('description', array(
            'header' => $helper->__('Description'),
            'index'  => 'description',
            'type'   => 'text',
            'width'  => '200px',
            'nl2br'  => true,
            'truncate' => 80,
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

    /**
     * Decorate package range column
     */
    public function decoratePackageRange($value, $row, $column, $isExport)
    {
        $min = (int)$row->getPackageMin();
        $max = $row->getPackageMax();
        if ($max === null) {
            return $min . '+';
        }
        return $min . ' ~ ' . (int)$max;
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
