<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Account_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('carrier_account_grid');
        $this->setDefaultSort('sort_order');
        $this->setDefaultDir('ASC');
        $this->setUseAjax(false);
        $this->setSaveParametersInSession(false);
    }

    /**
     * Prepare collection: load accounts for current carrier
     *
     * @return $this
     */
    protected function _prepareCollection()
    {
        $model = Mage::registry('xfe_carrier_data');
        if ($model && $model->getId()) {
            $collection = Mage::getModel('xfe_carrier/carrier_account')->getCollection()
                ->addFieldToFilter('carrier_id', $model->getId())
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

        $this->addColumn('account_name', array(
            'header' => $helper->__('账号名称'),
            'index'  => 'account_name',
        ));

        $this->addColumn('account_no', array(
            'header' => $helper->__('账号编号'),
            'index'  => 'account_no',
        ));

        $this->addColumn('username', array(
            'header' => $helper->__('用户名'),
            'index'  => 'username',
        ));

        $this->addColumn('endpoint_url', array(
            'header' => $helper->__('API端点URL'),
            'index'  => 'endpoint_url',
        ));

        $this->addColumn('status', array(
            'header'  => $helper->__('状态'),
            'index'   => 'status',
            'type'    => 'options',
            'width'   => '80px',
            'options' => Mage::getSingleton('xfe_carrier/source_status')->toArray(),
            'renderer' => 'xfe_carrier/adminhtml_carrier_edit_tab_account_grid_renderer_status',
        ));

        $this->addColumn('sort_order', array(
            'header' => $helper->__('排序'),
            'index'  => 'sort_order',
            'type'   => 'number',
            'width'  => '60px',
        ));

        $this->addColumn('action', array(
            'header'    => $helper->__('操作'),
            'width'     => '140px',
            'type'      => 'action',
            'getter'    => 'getId',
            'actions'   => array(
                array(
                    'caption' => $helper->__('编辑'),
                    'url'     => array('base'=> '*/carrier/editAccount', 'params'=> array('carrier_id'=> $this->_getCarrierId())),
                    'field'   => 'account_id',
                ),
                array(
                    'caption' => $helper->__('删除'),
                    'url'     => array('base'=> '*/carrier/deleteAccount', 'params'=> array()),
                    'field'   => 'account_id',
                    'confirm' => $helper->__('确定要删除该账号吗？'),
                ),
            ),
            'filter'    => false,
            'sortable'  => false,
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
        return Mage::helper('xfe_carrier')->__('暂无账号，请点击上方按钮添加。');
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
     * No mass actions for account grid inside edit tab
     *
     * @return $this
     */
    protected function _prepareMassaction()
    {
        return $this;
    }

    /**
     * Add "添加账号" button above grid
     *
     * @return string
     */
    protected function _toHtml()
    {
        $helper = Mage::helper('xfe_carrier');
        $carrierId = $this->_getCarrierId();
        $addUrl = $this->getUrl('*/carrier/editAccount', array('carrier_id' => $carrierId));

        $html = '<div id="accounts-list-wrapper">';
        $html .= '<div class="content-header" style="padding:0 0 10px 0;border:none;">';
        $html .= '<button type="button" class="scalable add" onclick="setLocation(\'' . $addUrl . '\')">';
        $html .= '<span><span><span>' . $helper->__('+ 添加账号') . '</span></span></span>';
        $html .= '</button>';
        // 预设示例入口:选择一个示例后,会带着 preset=xxx 跳到新建账号页面,
        // 账号表单会自动填入示例字段(账号编号/名称/物流公司/API Endpoint)。
        // 凭据字段(api_key/api_secret)不预填,需管理员手动补。
        $presetUrl = $this->getUrl('*/carrier/editAccount', array('carrier_id' => $carrierId));
        $html .= '<select id="xfe_carrier_account_preset" style="margin-left:8px;" '
              . 'onchange="if(this.value){setLocation(\'' . $presetUrl . '&preset=\' + this.value);}">';
        $html .= '<option value="">' . $helper->__('— 从示例新建 —') . '</option>';
        $html .= '<option value="sf_international">SF-International(顺丰国际标快)</option>';
        $html .= '<option value="sf_economy">SF-Economy(顺丰国际特惠)</option>';
        $html .= '<option value="fedex_ie">FedEx-IE(联邦快递国际经济)</option>';
        $html .= '<option value="fedex_ip">FedEx-IP(联邦快递国际优先)</option>';
        $html .= '<option value="chinapost_ems">ChinaPost-EMS(中国邮政 EMS)</option>';
        $html .= '<option value="dhl_express">DHL-Express(DHL 国际快递)</option>';
        $html .= '</select>';
        $html .= '</div>';
        $html .= parent::_toHtml();
        $html .= '</div>';
        return $html;
    }
}
