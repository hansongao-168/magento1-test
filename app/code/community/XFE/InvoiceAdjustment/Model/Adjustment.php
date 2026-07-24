<?php
/**
 * Invoice Adjustment Model
 *
 * @category   Community
 * @package    XFE_InvoiceAdjustment
 */
class XFE_InvoiceAdjustment_Model_Adjustment extends Mage_Core_Model_Abstract
{
    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'invoice_adjustment';

    /**
     * Event object key
     *
     * @var string
     */
    protected $_eventObject = 'adjustment';

    /**
     * Order instances cache
     *
     * @var array
     */
    protected $_orders = null;

    /**
     * Init model
     */
    protected function _construct()
    {
        $this->_init('invoiceadjustment/adjustment');
    }

    /**
     * Get associated orders
     *
     * @return XFE_InvoiceAdjustment_Model_Resource_Adjustment_Order_Collection
     */
    public function getOrders()
    {
        if (is_null($this->_orders)) {
            $this->_orders = Mage::getResourceModel('invoiceadjustment/adjustment_order_collection')
                ->addFieldToFilter('adjustment_id', $this->getId());
        }
        return $this->_orders;
    }

    /**
     * Process after save - save related orders and recalculate totals
     *
     * @return XFE_InvoiceAdjustment_Model_Adjustment
     */
    protected function _afterSave()
    {
        parent::_afterSave();

        if ($this->getData('orders_data')) {
            $this->_saveOrders($this->getData('orders_data'));
        }

        return $this;
    }

    /**
     * Save related orders and recalculate adjustment totals
     *
     * @param array $ordersData
     * @return XFE_InvoiceAdjustment_Model_Adjustment
     */
    protected function _saveOrders($ordersData)
    {
        $resource = $this->getResource();
        $write    = $resource->getWriteConnection();
        $table    = $resource->getTable('invoiceadjustment/adjustment_order');

        // Delete existing orders
        $write->delete($table, array('adjustment_id = ?' => $this->getId()));

        $totalHt   = 0;
        $totalTva  = 0;
        $totalTtc  = 0;
        $totalPay  = 0;
        $totalRefund = 0;

        $helper = Mage::helper('invoiceadjustment');

        foreach ($ordersData as $orderData) {
            $orderId     = isset($orderData['order_id']) ? $orderData['order_id'] : 0;
            $orderNumber = isset($orderData['order_number']) ? $orderData['order_number'] : '';
            $originalHt  = isset($orderData['original_ht']) ? (float)$orderData['original_ht'] : 0;
            $originalTva = isset($orderData['original_tva']) ? (float)$orderData['original_tva'] : 0;
            $originalTtc = isset($orderData['original_ttc']) ? (float)$orderData['original_ttc'] : 0;
            $adjHt       = isset($orderData['adjusted_ht']) ? (float)$orderData['adjusted_ht'] : $originalHt;
            $adjTva      = isset($orderData['adjusted_tva']) ? (float)$orderData['adjusted_tva'] : $originalTva;
            $adjTtc      = $helper->calculateTtc($adjHt, $adjTva);
            $pay         = $helper->calculateCustomerPay($adjTtc, $originalTtc);
            $refund      = $helper->calculateCustomerRefund($adjTtc, $originalTtc);

            $write->insert($table, array(
                'adjustment_id'  => $this->getId(),
                'order_id'       => $orderId,
                'order_number'   => $orderNumber,
                'original_ht'    => $originalHt,
                'original_tva'   => $originalTva,
                'original_ttc'   => $originalTtc,
                'adjusted_ht'    => $adjHt,
                'adjusted_tva'   => $adjTva,
                'adjusted_ttc'   => $adjTtc,
                'customer_pay'   => $pay,
                'customer_refund'=> $refund,
            ));

            $totalHt     += $adjHt;
            $totalTva    += $adjTva;
            $totalTtc    += $adjTtc;
            $totalPay    += $pay;
            $totalRefund += $refund;
        }

        // Update adjustment totals
        $write->update(
            $resource->getMainTable(),
            array(
                'adjusted_ht'     => $totalHt,
                'adjusted_tva'    => $totalTva,
                'adjusted_ttc'    => $totalTtc,
                'customer_pay'    => $totalPay,
                'customer_refund' => $totalRefund,
            ),
            array('adjustment_id = ?' => $this->getId())
        );

        $this->addData(array(
            'adjusted_ht'     => $totalHt,
            'adjusted_tva'    => $totalTva,
            'adjusted_ttc'    => $totalTtc,
            'customer_pay'    => $totalPay,
            'customer_refund' => $totalRefund,
        ));

        return $this;
    }

    /**
     * Get status label
     *
     * @return string
     */
    public function getStatusLabel()
    {
        $options = Mage::helper('invoiceadjustment')->getStatusOptions();
        $status  = $this->getStatus();
        return isset($options[$status]) ? $options[$status] : $status;
    }

    /**
     * Check if adjustment can be deleted
     *
     * @return bool
     */
    public function canDelete()
    {
        return $this->getStatus() !== 'processed';
    }

    /**
     * Check if adjustment can be edited
     *
     * @return bool
     */
    public function canEdit()
    {
        return $this->getStatus() !== 'processed';
    }
}
