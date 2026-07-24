<?php
/**
 * Order Tracking Block
 * Displays partner tracking information on order detail page
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_Block_Order_Tracking extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('xcarriers_label/order/tracking.phtml');
    }

    /**
     * Get order from parent block
     *
     * @return Mage_Sales_Model_Order|null
     */
    public function getOrder()
    {
        $order = Mage::registry('current_order');
        if (!$order) {
            $order = $this->getParentBlock() ? $this->getParentBlock()->getOrder() : null;
        }
        return $order;
    }

    /**
     * Get partner tracking data for the order
     *
     * Returns array of tracking info items:
     *   [{
     *     'track_number'     => string (original tracking number),
     *     'partner_tracking' => string (first 14 digits),
     *     'partner_name'     => string,
     *     'country_code'     => string,
     *     'tracking_url'     => string,
     *   }]
     *
     * @return array
     */
    public function getPartnerTrackingData()
    {
        $order = $this->getOrder();
        if (!$order || !$order->getId()) {
            return array();
        }

        // Get shipping address country code
        $shippingAddress = $order->getShippingAddress();
        if (!$shippingAddress) {
            return array();
        }
        $countryCode = $shippingAddress->getCountryId();

        // Find matching partner rule by country code + conditions
        /** @var XFE_LogisticsCarriersLabel_Helper_Data $helper */
        $helper = Mage::helper('xcarrierslabel');
        $orderData = $helper->buildOrderDataFromOrder($order);
        $rule = $helper->findMatchingRule($countryCode, $orderData);

        if (!$rule || !$rule->getId()) {
            return array();
        }

        // Get all shipment tracks
        $tracks = $order->getTracksCollection();
        if (!$tracks || $tracks->count() == 0) {
            return array();
        }

        $result = array();
        foreach ($tracks as $track) {
            $trackNumber = trim($track->getTrackNumber());
            if (empty($trackNumber)) {
                continue;
            }

            // Extract partner tracking number: first 14 digits
            $partnerTracking = $this->_extractPartnerTrackingNumber($trackNumber);
            if (empty($partnerTracking)) {
                continue;
            }

            // Build tracking URL
            $zip = $shippingAddress->getPostcode();
            $trackingUrl = $rule->buildTrackingUrl($partnerTracking, $zip);

            $result[] = array(
                'track_number'     => $trackNumber,
                'partner_tracking' => $partnerTracking,
                'partner_name'     => $rule->getPartnerName(),
                'country_code'     => $countryCode,
                'tracking_url'     => $trackingUrl,
            );
        }

        return $result;
    }

    /**
     * Extract partner tracking number from original tracking number
     * Rule: return first 14 digits from the tracking number
     *
     * @param string $trackNumber
     * @return string
     */
    protected function _extractPartnerTrackingNumber($trackNumber)
    {
        // Extract all digits from the tracking number
        $digits = preg_replace('/[^0-9]/', '', $trackNumber);
        if (strlen($digits) < 14) {
            return $digits;
        }
        return substr($digits, 0, 14);
    }

    /**
     * Check if there's any partner tracking data to display
     *
     * @return bool
     */
    public function hasPartnerTracking()
    {
        return count($this->getPartnerTrackingData()) > 0;
    }
}
