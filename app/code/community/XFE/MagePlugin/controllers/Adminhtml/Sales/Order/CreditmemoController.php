<?php
/**
 * XFE_MagePlugin 重写后台退款（Credit Memo）Controller。
 *
 * 目的：当订单已有承运商物流跟踪记录时，禁止创建退款（防绕过 UI）。
 *
 * 通过 adminhtml 路由命名空间前置（XFE_MagePlugin before="Mage_Adminhtml"）生效。
 */

// 父类 controller 不会被 Magento 路由自动加载，需显式引入（Magento 标准做法）
require_once 'Mage/Adminhtml/controllers/Sales/Order/CreditmemoController.php';

class XFE_MagePlugin_Adminhtml_Sales_Order_CreditmemoController extends Mage_Adminhtml_Sales_Order_CreditmemoController
{
    /**
     * 检查订单是否可创建退款。
     *
     * 在核心校验基础上，追加「有承运商物流跟踪记录则禁止退款」。
     *
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    protected function _canCreditmemo($order)
    {
        if (!parent::_canCreditmemo($order)) {
            return false;
        }

        /** @var XFE_MagePlugin_Model_Service_TrackingDetector $detector */
        $detector = Mage::getModel('xfe_mageplugin/service_trackingDetector');
        if ($detector->hasCarrierTracking($order)) {
            $this->_getSession()->addError(
                $this->__('该订单已有承运商物流跟踪记录，不能操作退款。')
            );
            return false;
        }
        return true;
    }
}
