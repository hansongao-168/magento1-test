<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Logo_Rules_Grid
    extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('carrier_logo_rule_grid');
        $this->setDefaultSort('sort_order');
        $this->setDefaultDir('ASC');
        $this->setUseAjax(false);
        $this->setSaveParametersInSession(false);
    }

    protected function _prepareCollection()
    {
        $logo = Mage::registry('xfe_carrier_logo_data');
        if ($logo && $logo->getId()) {
            $collection = Mage::getModel('xfe_carrier/carrier_rule')->getCollection()
                ->addFieldToFilter('carrier_id', (int)$logo->getCarrierId())
                ->addFieldToFilter('module_code', 'logo')
                ->setOrder('sort_order', 'ASC');
        } else {
            $collection = new Varien_Data_Collection();
        }
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $helper = Mage::helper('xfe_carrier');

        $this->addColumn('name', array(
            'header' => $helper->__('Rule Name'),
            'index'  => 'name',
        ));

        $this->addColumn('description', array(
            'header' => $helper->__('Description'),
            'index'  => 'description',
        ));

        $this->addColumn('status', array(
            'header'   => $helper->__('Status'),
            'index'    => 'status',
            'type'     => 'options',
            'width'    => '80px',
            'options'  => Mage::getSingleton('xfe_carrier/source_status')->toArray(),
            'renderer' => 'xfe_carrier/adminhtml_carrier_edit_tab_rules_grid_renderer_status',
        ));

        $this->addColumn('sort_order', array(
            'header' => $helper->__('Sort'),
            'index'  => 'sort_order',
            'type'   => 'number',
            'width'  => '60px',
        ));

        $this->addColumn('action', array(
            'header'  => $helper->__('Action'),
            'width'   => '140px',
            'type'    => 'action',
            'getter'  => 'getId',
            'actions' => array(
                array(
                    'caption' => $helper->__('Edit'),
                    'url'     => array(
                        'base'   => '*/carrier/editRule',
                        'params' => array(
                            'carrier_id' => $this->_getCarrierId(),
                            'logo_id'    => $this->_getLogoId(),
                        ),
                    ),
                    'field'   => 'rule_id',
                ),
                array(
                    'caption' => $helper->__('Delete'),
                    'url'     => array('base' => '*/carrier/deleteRule', 'params' => array()),
                    'field'   => 'rule_id',
                    'confirm' => $helper->__('Are you sure you want to delete this rule?'),
                ),
            ),
            'filter'   => false,
            'sortable' => false,
        ));

        return parent::_prepareColumns();
    }

    protected function _getCarrierId()
    {
        $logo = Mage::registry('xfe_carrier_logo_data');
        return $logo && $logo->getId() ? (int)$logo->getCarrierId() : 0;
    }

    protected function _getLogoId()
    {
        $logo = Mage::registry('xfe_carrier_logo_data');
        return $logo && $logo->getId() ? (int)$logo->getId() : 0;
    }

    public function getEmptyText()
    {
        return Mage::helper('xfe_carrier')->__('No rules bound. This logo will be used as default.');
    }

    public function getRowUrl($row)
    {
        return false;
    }

    protected function _prepareMassaction()
    {
        return $this;
    }

    protected function _toHtml()
    {
        $helper    = Mage::helper('xfe_carrier');
        $carrierId = $this->_getCarrierId();
        $addUrl    = $this->getUrl('*/carrier/editRule',
            array('carrier_id' => $carrierId, 'logo_id' => $this->_getLogoId()));

        $html  = '<div id="logo-rules-list-wrapper">';
        $html .= '<p class="form-buttons" style="margin:0 0 8px 0;">';
        $html .= '<button type="button" class="scalable add" onclick="setLocation(\'' . $addUrl . '\')">';
        $html .= '<span><span><span>' . $helper->__('+ Add Rule') . '</span></span></span>';
        $html .= '</button>';
        $html .= '</p>';
        $html .= parent::_toHtml();
        $html .= '</div>';
        return $html;
    }
}
