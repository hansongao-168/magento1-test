<?php
/**
 * Invoice Adjustment Resource Model
 *
 * @category   Community
 * @package    XFE_InvoiceAdjustment
 */
class XFE_InvoiceAdjustment_Model_Resource_Adjustment extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Init resource
     */
    protected function _construct()
    {
        $this->_init('invoiceadjustment/adjustment', 'adjustment_id');
    }

    /**
     * After load - load related orders
     *
     * @param Mage_Core_Model_Abstract $object
     * @return XFE_InvoiceAdjustment_Model_Resource_Adjustment
     */
    protected function _afterLoad(Mage_Core_Model_Abstract $object)
    {
        parent::_afterLoad($object);

        if ($object->getId()) {
            $orders = Mage::getResourceModel('invoiceadjustment/adjustment_order_collection')
                ->addFieldToFilter('adjustment_id', $object->getId());
            $object->setData('orders', $orders->getItems());
        }

        return $this;
    }

    /**
     * Before delete - check if adjustment can be deleted
     *
     * @param Mage_Core_Model_Abstract $object
     * @return XFE_InvoiceAdjustment_Model_Resource_Adjustment
     */
    protected function _beforeDelete(Mage_Core_Model_Abstract $object)
    {
        if ($object->getStatus() === 'processed') {
            Mage::throwException(
                Mage::helper('invoiceadjustment')->__('Cannot delete a processed adjustment.')
            );
        }
        return parent::_beforeDelete($object);
    }

    /**
     * Update original TTC from order (utility method)
     *
     * @param int $adjustmentId
     * @return $this
     */
    public function recalculateTotals($adjustmentId)
    {
        $read  = $this->getReadConnection();
        $write = $this->getWriteConnection();

        $select = $read->select()
            ->from($this->getTable('invoiceadjustment/adjustment_order'), array(
                'total_ht'      => 'SUM(adjusted_ht)',
                'total_tva'     => 'SUM(adjusted_tva)',
                'total_ttc'     => 'SUM(adjusted_ttc)',
                'total_pay'     => 'SUM(customer_pay)',
                'total_refund'  => 'SUM(customer_refund)',
            ))
            ->where('adjustment_id = ?', $adjustmentId);

        $totals = $read->fetchRow($select);

        if ($totals) {
            $write->update(
                $this->getMainTable(),
                array(
                    'adjusted_ht'     => $totals['total_ht'],
                    'adjusted_tva'    => $totals['total_tva'],
                    'adjusted_ttc'    => $totals['total_ttc'],
                    'customer_pay'    => $totals['total_pay'],
                    'customer_refund' => $totals['total_refund'],
                ),
                array('adjustment_id = ?' => $adjustmentId)
            );
        }

        return $this;
    }
}
