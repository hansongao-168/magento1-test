<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('xfe_carrier_grid');
        $this->setDefaultSort('sort_order');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    /**
     * Prepare collection
     *
     * @return $this
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('xfe_carrier/carrier')->getCollection();

        // Surface per-store translations (name / note) by left-joining
        // xfe_carrier_translation for the store view selected in the
        // admin Store Switcher. The Collection fallback rule (empty
        // per-store row -> base row) keeps admin-scope defaults visible
        // when no translation exists for the chosen store.
        $store = $this->getRequest()->getParam(
            'store',
            (int)Mage::app()->getDefaultStoreView()->getId()
        );
        if ($store !== null && $store !== '') {
            $collection->addStoreFilter((int)$store);
        }

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Prepare grid columns
     *
     * @return $this
     */
    protected function _prepareColumns()
    {
        $this->addColumn('entity_id', array(
            'header' => Mage::helper('xfe_carrier')->__('ID'),
            'index'  => 'entity_id',
            'type'   => 'number',
            'width'  => '50px',
        ));

        $this->addColumn('name', array(
            'header' => Mage::helper('xfe_carrier')->__('名称'),
            // 'store_name' is populated by XFE_Carrier_Model_Resource_Carrier_Collection
            // when addStoreFilter() joins xfe_carrier_translation; empty values
            // fall back to the base column in Collection::_afterLoad().
            'index'  => 'store_name',
        ));

        $this->addColumn('code', array(
            'header' => Mage::helper('xfe_carrier')->__('标识代码'),
            'index'  => 'code',
        ));

        $this->addColumn('shipping_company_id', array(
            'header' => Mage::helper('xfe_carrier')->__('线路公司ID'),
            'index'  => 'shipping_company_id',
            'type'   => 'number',
            'width'  => '100px',
        ));

        $this->addColumn('status', array(
            'header'  => Mage::helper('xfe_carrier')->__('状态'),
            'index'   => 'status',
            'type'    => 'options',
            'width'   => '80px',
            'options' => Mage::getSingleton('xfe_carrier/source_status')->toArray(),
        ));

        $this->addColumn('sort_order', array(
            'header' => Mage::helper('xfe_carrier')->__('排序'),
            'index'  => 'sort_order',
            'type'   => 'number',
            'width'  => '60px',
        ));

        $this->addColumn('created_at', array(
            'header' => Mage::helper('xfe_carrier')->__('创建时间'),
            'index'  => 'created_at',
            'type'   => 'datetime',
            'width'  => '160px',
        ));

        $this->addColumn('updated_at', array(
            'header' => Mage::helper('xfe_carrier')->__('更新时间'),
            'index'  => 'updated_at',
            'type'   => 'datetime',
            'width'  => '160px',
        ));

        return parent::_prepareColumns();
    }

    /**
     * Prepare mass action
     *
     * @return $this
     */
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('entity_id');
        $this->getMassactionBlock()->setFormFieldName('carrier_ids');

        $this->getMassactionBlock()->addItem('delete', array(
            'label'   => Mage::helper('xfe_carrier')->__('删除'),
            'url'     => $this->getUrl('*/*/massDelete'),
            'confirm' => Mage::helper('xfe_carrier')->__('确定要删除选中的承运商吗？'),
        ));

        $this->getMassactionBlock()->addItem('status', array(
            'label'      => Mage::helper('xfe_carrier')->__('修改状态'),
            'url'        => $this->getUrl('*/*/massStatus'),
            'additional' => array(
                'status' => array(
                    'name'   => 'status',
                    'type'   => 'select',
                    'class'  => 'required-entry',
                    'label'  => Mage::helper('xfe_carrier')->__('状态'),
                    'values' => Mage::getSingleton('xfe_carrier/source_status')->toOptionArray(),
                ),
            ),
        ));

        return $this;
    }

    /**
     * Get row URL for edit action
     *
     * @param XFE_Carrier_Model_Carrier $row
     * @return string|false
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', array('id' => $row->getId()));
    }

    /**
     * Get grid URL for Ajax reload
     *
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }
}
