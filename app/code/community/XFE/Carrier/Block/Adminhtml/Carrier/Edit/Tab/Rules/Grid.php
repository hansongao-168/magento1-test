<?php

/**
 * Carrier Edit Tab - Rules Grid
 *
 * Carrier-level rules listing (1:N from carrier to rules). Filtered by the
 * currently-edited carrier_id taken from the xfe_carrier_data registry.
 *
 * History note: prior versions of this file carried mojibake (GBK-encoded
 * Chinese re-interpreted as UTF-8) and raw-byte hex escape sequences like
 * '\xe4\xbc\x98\xe5\x85\x88\xe7\xba\xa7' as placeholders. Both forms are
 * forbidden by AGENTS.md §1 and have been rewritten here as native UTF-8.
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Rules_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('carrier_rule_grid');
        $this->setDefaultSort('priority');
        $this->setDefaultDir('DESC');
        $this->setUseAjax(false);
        $this->setSaveParametersInSession(false);
    }

    /**
     * Prepare collection: load rules for current carrier
     *
     * @return $this
     */
    protected function _prepareCollection()
    {
        $model = Mage::registry('xfe_carrier_data');
        if ($model && $model->getId()) {
            $collection = Mage::getModel('xfe_carrier/carrier_rule')->getCollection()
                ->addFieldToFilter('carrier_id', $model->getId())
                ->setOrder('priority', 'DESC')
                ->setOrder('updated_at', 'DESC')
                ->setOrder('sort_order', 'ASC');
        } else {
            $collection = new Varien_Data_Collection();
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
        $helper = Mage::helper('xfe_carrier');

        $this->addColumn('module_code', array(
            'header' => $helper->__('所属模块'),
            'index'  => 'module_code',
            'width'  => '100px',
            'type'   => 'options',
            'options' => $helper->getCarrierModuleSelectOptions(),
        ));

        $this->addColumn('name', array(
            'header' => $helper->__('规则名称'),
            'index'  => 'name',
        ));

        $this->addColumn('description', array(
            'header' => $helper->__('描述'),
            'index'  => 'description',
        ));

        $this->addColumn('status', array(
            'header'   => $helper->__('状态'),
            'index'    => 'status',
            'type'     => 'options',
            'width'    => '80px',
            'options'  => Mage::getSingleton('xfe_carrier/source_status')->toArray(),
            'renderer' => 'xfe_carrier/adminhtml_carrier_edit_tab_rules_grid_renderer_status',
        ));

        $this->addColumn('priority', array(
            'header' => $helper->__('优先级'),
            'index'  => 'priority',
            'type'   => 'number',
            'width'  => '60px',
        ));

        $this->addColumn('sort_order', array(
            'header' => $helper->__('排序'),
            'index'  => 'sort_order',
            'type'   => 'number',
            'width'  => '60px',
        ));

        $this->addColumn('action', array(
            'header'   => $helper->__('操作'),
            'width'    => '140px',
            'type'     => 'action',
            'getter'   => 'getId',
            'actions'  => array(
                array(
                    'caption' => $helper->__('编辑'),
                    'url'     => array('base' => '*/carrier/editRule', 'params' => array('carrier_id' => $this->_getCarrierId())),
                    'field'   => 'rule_id',
                ),
                array(
                    'caption' => $helper->__('删除'),
                    'url'     => array('base' => '*/carrier/deleteRule', 'params' => array()),
                    'field'   => 'rule_id',
                    'confirm' => $helper->__('确定要删除该规则吗？'),
                ),
            ),
            'filter'   => false,
            'sortable' => false,
        ));

        return parent::_prepareColumns();
    }

    /**
     * Get current carrier ID from registry
     *
     * @return int
     */
    protected function _getCarrierId()
    {
        $model = Mage::registry('xfe_carrier_data');
        return $model && $model->getId() ? (int)$model->getId() : 0;
    }

    /**
     * Get empty text when no records found
     *
     * @return string
     */
    public function getEmptyText()
    {
        return Mage::helper('xfe_carrier')->__('暂无规则，请点击上方按钮添加。');
    }

    /**
     * No row URL for grid
     *
     * @param Varien_Object $row
     * @return false
     */
    public function getRowUrl($row)
    {
        return false;
    }

    /**
     * No mass actions for rule grid inside edit tab
     *
     * @return $this
     */
    protected function _prepareMassaction()
    {
        return $this;
    }

    /**
     * Prepend the [+ 添加规则] button above the grid.
     *
     * @return string
     */
    protected function _toHtml()
    {
        $helper = Mage::helper('xfe_carrier');
        $carrierId = $this->_getCarrierId();
        $addUrl = $this->getUrl('*/carrier/editRule', array('carrier_id' => $carrierId));

        $html = '<div id="rules-list-wrapper">';
        $html .= '<p class="form-buttons" style="margin:0 0 8px 0;">';
        $html .= '<button type="button" class="scalable add" onclick="setLocation(\'' . $addUrl . '\')">';
        $html .= '<span><span><span>' . $helper->__('+ 添加规则') . '</span></span></span>';
        $html .= '</button>';
        $html .= '</p>';
        $html .= parent::_toHtml();
        $html .= '</div>';
        return $html;
    }
}