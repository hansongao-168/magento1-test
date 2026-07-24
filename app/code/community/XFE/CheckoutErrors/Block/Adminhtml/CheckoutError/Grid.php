<?php
class XFE_CheckoutErrors_Block_Adminhtml_CheckoutError_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('checkoutErrorGrid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('xfe_checkouterrors/checkoutError')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('reference_id', [
            'header' => $this->__('参考号'),
            'index'  => 'reference_id',
            'width'  => '180px',
        ]);

        $this->addColumn('step', [
            'header'  => $this->__('步骤'),
            'index'   => 'step',
            'type'    => 'options',
            'options' => [
                'saveBilling'        => $this->__('保存账单地址'),
                'saveShipping'       => $this->__('保存配送地址'),
                'saveShippingMethod' => $this->__('选择配送方式'),
                'savePayment'        => $this->__('选择支付方式'),
                'saveOrder'          => $this->__('提交订单'),
            ],
            'width'   => '120px',
        ]);

        $this->addColumn('error_message', [
            'header'   => $this->__('错误信息'),
            'index'    => 'error_message',
            'renderer' => 'adminhtml/widget_grid_column_renderer_longtext',
        ]);

        $this->addColumn('customer_email', [
            'header' => $this->__('客户邮箱'),
            'index'  => 'customer_email',
            'width'  => '180px',
        ]);

        $this->addColumn('created_at', [
            'header' => $this->__('时间'),
            'index'  => 'created_at',
            'type'   => 'datetime',
            'width'  => '160px',
        ]);

        return parent::_prepareColumns();
    }

    public function getRowUrl($row)
    {
        return false;
    }
}
