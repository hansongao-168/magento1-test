<?php
class XFE_OrderFrontend_Block_Order_History extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('xfe_order/order/history.phtml');
    }

    /**
     * 获取 AJAX JSON 端点 URL
     *
     * @return string
     */
    public function getListUrl()
    {
        return $this->getUrl('xfe_order/order/list');
    }

    /**
     * 获取订单状态下拉选项
     *
     * @return array
     */
    public function getStatusOptions()
    {
        return Mage::getSingleton('sales/order_config')->getStatuses();
    }
}
