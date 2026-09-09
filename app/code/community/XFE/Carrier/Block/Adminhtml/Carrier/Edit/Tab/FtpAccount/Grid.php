<?php

/**
 * Carrier Edit Tab - FTP账号列表 Grid
 *
 * 平行于 XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Account_Grid,
 * 列改为 host/port/protocol/username/remote_path/status/sort_order。
 *
 * 行内操作:
 *   - 编辑  ->  carrier/editFtpAccount Action
 *   - 删除  ->  carrier/deleteFtpAccount Action
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_FtpAccount_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('carrier_ftp_account_grid');
        $this->setDefaultSort('sort_order');
        $this->setDefaultDir('ASC');
        $this->setUseAjax(false);
        $this->setSaveParametersInSession(false);
    }

    protected function _prepareCollection()
    {
        $model = Mage::registry('xfe_carrier_data');
        if ($model && $model->getId()) {
            $collection = Mage::getModel('xfe_carrier/carrier_ftp_account')->getCollection()
                ->addFieldToFilter('carrier_id', $model->getId())
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

        $this->addColumn('account_name', array(
            'header' => $helper->__('账号名称'),
            'index'  => 'account_name',
        ));

        $this->addColumn('account_no', array(
            'header' => $helper->__('账号编号'),
            'index'  => 'account_no',
        ));

        $this->addColumn('protocol', array(
            'header'  => $helper->__('协议'),
            'index'   => 'protocol',
            'width'   => '70px',
            'type'    => 'options',
            'options' => array(
                'ftp'  => $helper->__('FTP'),
                'sftp' => $helper->__('SFTP'),
                'ftps' => $helper->__('FTPS'),
            ),
        ));

        $this->addColumn('host', array(
            'header' => $helper->__('主机'),
            'index'  => 'host',
        ));

        $this->addColumn('port', array(
            'header' => $helper->__('端口'),
            'index'  => 'port',
            'type'   => 'number',
            'width'  => '60px',
        ));

        $this->addColumn('username', array(
            'header' => $helper->__('用户名'),
            'index'  => 'username',
        ));

        $this->addColumn('remote_path', array(
            'header' => $helper->__('远程路径'),
            'index'  => 'remote_path',
        ));

        $this->addColumn('status', array(
            'header'   => $helper->__('状态'),
            'index'    => 'status',
            'type'     => 'options',
            'width'    => '80px',
            'options'  => Mage::getSingleton('xfe_carrier/source_status')->toArray(),
            'renderer' => 'xfe_carrier/adminhtml_carrier_edit_tab_ftpaccount_grid_renderer_status',
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
                        'base'   => '*/carrier/editFtpAccount',
                        'params' => array('carrier_id' => $this->_getCarrierId()),
                    ),
                    'field'   => 'ftp_account_id',
                ),
                array(
                    'caption' => $helper->__('删除'),
                    'url'     => array('base' => '*/carrier/deleteFtpAccount', 'params' => array()),
                    'field'   => 'ftp_account_id',
                    'confirm' => $helper->__('确定要删除该 FTP账号吗？'),
                ),
            ),
            'filter'   => false,
            'sortable' => false,
        ));

        return parent::_prepareColumns();
    }

    protected function _getCarrierId()
    {
        $model = Mage::registry('xfe_carrier_data');
        return $model && $model->getId() ? (int)$model->getId() : 0;
    }

    public function getEmptyText()
    {
        return Mage::helper('xfe_carrier')->__('暂无 FTP账号，请点击上方按钮添加。');
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
        $addUrl    = $this->getUrl('*/carrier/editFtpAccount', array('carrier_id' => $carrierId));

        $html  = '<div id="ftp-accounts-list-wrapper">';
        $html .= '<div class="content-header" style="padding:0 0 10px 0;border:none;">';
        $html .= '<button type="button" class="scalable add" onclick="setLocation(\'' . $addUrl . '\')">';
        $html .= '<span><span><span>' . $helper->__('+ 添加 FTP账号') . '</span></span></span>';
        $html .= '</button>';
        $html .= '</div>';
        $html .= parent::_toHtml();
        $html .= '</div>';
        return $html;
    }
}