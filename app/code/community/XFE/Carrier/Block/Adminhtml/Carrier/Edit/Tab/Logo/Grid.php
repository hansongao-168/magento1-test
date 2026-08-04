<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Logo_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('carrier_logo_grid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setUseAjax(false);
        $this->setSaveParametersInSession(false);
    }

    protected function _prepareCollection()
    {
        $model = Mage::registry('xfe_carrier_data');
        if ($model && $model->getId()) {
            $collection = Mage::getModel('xfe_carrier/carrier_logo')->getCollection()
                ->addFieldToFilter('carrier_id', $model->getId());
        } else {
            $collection = new Varien_Data_Collection();
        }
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $helper = Mage::helper('xfe_carrier');

        $this->addColumn('preview', array(
            'header'   => $helper->__('Preview'),
            'renderer' => 'xfe_carrier/adminhtml_carrier_edit_tab_logo_grid_renderer_preview',
            'width'    => '120px',
            'filter'   => false,
            'sortable' => false,
        ));

        $this->addColumn('label', array(
            'header' => $helper->__('Name'),
            'index'  => 'label',
        ));

        $this->addColumn('logo_type', array(
            'header'  => $helper->__('Type'),
            'index'   => 'logo_type',
            'type'    => 'options',
            'options' => array(
                'main'   => $helper->__('Main Logo'),
                'mobile' => $helper->__('Mobile Logo'),
                'alt'    => $helper->__('Alternate Logo'),
            ),
            'width' => '120px',
        ));

        $this->addColumn('is_default', array(
            'header'   => $helper->__('Default'),
            'index'    => 'rule_id',
            'width'    => '80px',
            'renderer' => 'xfe_carrier/adminhtml_carrier_edit_tab_logo_grid_renderer_default',
            'filter'   => false,
            'sortable' => false,
        ));

        $this->addColumn('dimensions', array(
            'header'   => $helper->__('Dimensions'),
            'renderer' => 'xfe_carrier/adminhtml_carrier_edit_tab_logo_grid_renderer_dimensions',
            'width'    => '120px',
            'filter'   => false,
            'sortable' => false,
        ));

        $this->addColumn('action', array(
            'header'    => $helper->__('Action'),
            'width'     => '140px',
            'type'      => 'action',
            'getter'    => 'getId',
            'actions'   => array(
                array(
                    'caption' => $helper->__('Edit'),
                    'url'     => array('base' => '*/carrier/editLogo', 'params' => array()),
                    'field'   => 'logo_id',
                ),
                array(
                    'caption' => $helper->__('Delete'),
                    'onclick' => 'deleteLogo({{id}}); return false;',
                ),
            ),
            'filter'    => false,
            'sortable'  => false,
        ));

        return parent::_prepareColumns();
    }

    public function getEmptyText()
    {
        return Mage::helper('xfe_carrier')->__('No logos yet. Click the button above to add one.');
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
        $helper = Mage::helper('xfe_carrier');
        $model = Mage::registry('xfe_carrier_data');
        $addUrl = $model ? $this->getUrl('adminhtml/carrier/addLogo', array('id' => $model->getId())) : '#';

        $html = '<div id="logo-list-wrapper">';
        $html .= '<div class="content-header" style="padding:0 0 10px 0;border:none;">';
        $html .= '<button type="button" class="scalable add" onclick="setLocation(\'' . $addUrl . '\')">';
        $html .= '<span><span><span>' . $helper->__('+ Add Logo') . '</span></span></span>';
        $html .= '</button>';
        $html .= '</div>';
        $html .= parent::_toHtml();
        $html .= '</div>';
        return $html;
    }
}