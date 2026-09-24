<?php
/**
 * XFE_MagePlugin 承运商物流跟踪记录判定（L3 Service）。
 *
 * 判定订单是否已有「承运商物流跟踪记录」。若已有，则不应显示退款（Credit Memo）按钮、禁止退款操作。
 *
 * 说明：「我们自己的扫描」属于另一项目，此处仅预留扩展方法 isSelfScanned()，
 * 当前固定返回 false（即不把「自己扫描」计为承运商跟踪）。
 */
class XFE_MagePlugin_Model_Service_TrackingDetector
{
    /**
     * 订单是否已有承运商物流跟踪记录。
     *
     * @param Mage_Sales_Model_Order|int|null $order
     * @return bool
     */
    public function hasCarrierTracking($order)
    {
        if ($order instanceof Mage_Sales_Model_Order) {
            $orderId = (int)$order->getId();
        } else {
            $orderId = (int)$order;
        }
        if (!$orderId) {
            return false;
        }

        // 承运商跟踪记录 = sales_flat_shipment_track 中存在该订单的跟踪号
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $table = Mage::getSingleton('core/resource')->getTableName('sales/shipment_track');
        $select = $read->select()
            ->from($table, 'COUNT(*)')
            ->where('order_id = ?', $orderId);
        $count = (int)$read->fetchOne($select);

        // 排除「自己扫描」的记录
        if ($this->isSelfScanned($orderId)) {
            $count -= $this->_countSelfScanned($orderId);
        }

        return $count > 0;
    }

    /**
     * 判断某订单的记录是否为「自己扫描」。
     *
     * 「自己扫描」属于另一项目，当前未接入，恒返回 false。
     * 后续接入时：可通过 tracking_number、carrier code、状态字段或外部接口判定。
     *
     * @param int $orderId
     * @return bool
     */
    public function isSelfScanned($orderId)
    {
        return false;
    }

    /**
     * 统计该订单中「自己扫描」的跟踪记录数量。
     *
     * 预留扩展点；当前实现按 isSelfScanned 结果返回 0。
     *
     * @param int $orderId
     * @return int
     */
    protected function _countSelfScanned($orderId)
    {
        return 0;
    }
}
