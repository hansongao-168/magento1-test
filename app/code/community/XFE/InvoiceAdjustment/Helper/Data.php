<?php
/**
 * Invoice Adjustment Helper
 *
 * @category   Community
 * @package    XFE_InvoiceAdjustment
 */
class XFE_InvoiceAdjustment_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Get status options
     *
     * @return array
     */
    public function getStatusOptions()
    {
        return array(
            'pending'    => Mage::helper('invoiceadjustment')->__('Pending'),
            'processed'  => Mage::helper('invoiceadjustment')->__('Processed'),
            'cancelled'  => Mage::helper('invoiceadjustment')->__('Cancelled'),
        );
    }

    /**
     * Calculate TTC from HT and TVA
     *
     * @param float $ht
     * @param float $tva
     * @return float
     */
    public function calculateTtc($ht, $tva)
    {
        return round($ht + $tva, 4);
    }

    /**
     * Calculate customer pay amount (positive diff = customer pays more)
     *
     * @param float $adjustedTtc
     * @param float $originalTtc
     * @return float
     */
    public function calculateCustomerPay($adjustedTtc, $originalTtc)
    {
        $diff = $adjustedTtc - $originalTtc;
        return $diff > 0 ? round($diff, 4) : 0;
    }

    /**
     * Calculate customer refund amount (negative diff = customer gets refund)
     *
     * @param float $adjustedTtc
     * @param float $originalTtc
     * @return float
     */
    public function calculateCustomerRefund($adjustedTtc, $originalTtc)
    {
        $diff = $adjustedTtc - $originalTtc;
        return $diff < 0 ? round(abs($diff), 4) : 0;
    }
}
