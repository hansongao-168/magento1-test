<?php

/**
 * Carrier Edit Tab - FTP账号专属 Rules Grid
 *
 * 平行于 XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Account_Rules_Grid,
 * 改为:
 *   - 取 xfe_carrier_ftp_account_data 寄存器中的 ftp_account 行
 *   - 过滤条件:carrier_id + module_code='ftp' + ftp_account_id=...
 *   - 行内编辑/删除规则继续用统一 Carrier Rule Edit 页面
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_FtpAccount_Rules_Grid
    extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('carrier_ftp_account_rule_grid');
        $this->setDefaultSort('priority');
        $this->setDefaultDir('DESC');
        $this->setUseAjax(false);
        $this->setSaveParametersInSession(false);
    }

    protected function _prepareCollection()
    {
        $ftpAccount = Mage::registry('xfe_carrier_ftp_account_data');
        if ($ftpAccount && $ftpAccount->getId()) {
            $collection = Mage::getModel('xfe_carrier/carrier_rule')->getCollection()
                ->addFieldToFilter('carrier_id', (int)$ftpAccount->getCarrierId())
                ->addFieldToFilter('module_code', 'ftp')
                ->addFieldToFilter('ftp_account_id', (int)$ftpAccount->getId())
                ->setOrder('priority', 'DESC')
                ->setOrder('updated_at', 'DESC')
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
            'header'  => $helper->__('操作'),
            'width'   => '140px',
            'type'    => 'action',
            'getter'  => 'getId',
            'actions' => array(
                array(
                    'caption' => $helper->__('编辑'),
                    'url'     => array(
                        'base'   => '*/carrier/editRule',
                        'params' => array(
                            'carrier_id'     => $this->_getCarrierId(),
                            'ftp_account_id' => $this->_getFtpAccountId(),
                        ),
                    ),
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

    protected function _getCarrierId()
    {
        $ftp = Mage::registry('xfe_carrier_ftp_account_data');
        return $ftp && $ftp->getId() ? (int)$ftp->getCarrierId() : 0;
    }

    protected function _getFtpAccountId()
    {
        $ftp = Mage::registry('xfe_carrier_ftp_account_data');
        return $ftp && $ftp->getId() ? (int)$ftp->getId() : 0;
    }

    public function getEmptyText()
    {
        return Mage::helper('xfe_carrier')->__('暂无规则，请点击上方按钮添加。');
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
        $helper        = Mage::helper('xfe_carrier');
        $carrierId     = $this->_getCarrierId();
        $ftpAccountId  = $this->_getFtpAccountId();
        $addUrl        = $this->getUrl('*/carrier/editRule', array(
            'carrier_id'     => $carrierId,
            'ftp_account_id' => $ftpAccountId,
        ));

        $html  = '<div id="ftp-account-rules-list-wrapper">';
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