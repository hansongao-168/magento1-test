<?php
/**
 * XFE_MagePlugin 订单详情 View（退款控制）。
 *
 * 当订单已有承运商物流跟踪记录时，不显示「Credit Memo（退款）」按钮。
 */
class XFE_MagePlugin_Block_Adminhtml_Sales_Order_View extends Mage_Adminhtml_Block_Sales_Order_View
{
    /**
     * 构造函数。
     */
    public function __construct()
    {
        parent::__construct();

        $order = $this->getOrder();
        if ($order && $order->getId()) {
            /** @var XFE_MagePlugin_Model_Service_TrackingDetector $detector */
            $detector = Mage::getModel('xfe_mageplugin/service_trackingDetector');
            if ($detector->hasCarrierTracking($order)) {
                $this->_removeButton('order_creditmemo');
            }
        }
    }
}
