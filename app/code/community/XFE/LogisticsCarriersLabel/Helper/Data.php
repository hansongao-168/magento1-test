<?php
/**
 * XFE LogisticsCarriersLabel Helper
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Get status options
     *
     * @return array
     */
    public function getStatusOptions()
    {
        return array(
            '1' => $this->__('Enabled'),
            '0' => $this->__('Disabled'),
        );
    }

    /**
     * Get condition attribute options
     *
     * @return array
     */
    public function getConditionAttributeOptions()
    {
        return array(
            'country_code'   => $this->__('Country Code'),
            'zip_code'       => $this->__('Zip/Postal Code'),
            'weight'         => $this->__('Weight'),
            'order_total'    => $this->__('Order Total'),
            'package_count'  => $this->__('Package Count'),
            'customer_group' => $this->__('Customer Group'),
        );
    }

    /**
     * Get operator options (all)
     *
     * @return array
     */
    public function getOperatorOptions()
    {
        return array(
            '=='       => $this->__('Equals'),
            '!='       => $this->__('Not Equals'),
            '>'        => $this->__('Greater Than'),
            '>='       => $this->__('Greater or Equal'),
            '<'        => $this->__('Less Than'),
            '<='       => $this->__('Less or Equal'),
            'in'       => $this->__('In (comma-separated)'),
            'contains' => $this->__('Contains'),
            'between'  => $this->__('Between (x~y)'),
        );
    }

    /**
     * Get numeric operators
     *
     * @return array
     */
    public function getNumericOperators()
    {
        return array(
            '=='      => $this->__('Equals'),
            '!='      => $this->__('Not Equals'),
            '>'       => $this->__('Greater Than'),
            '>='      => $this->__('Greater or Equal'),
            '<'       => $this->__('Less Than'),
            '<='      => $this->__('Less or Equal'),
            'between' => $this->__('Between (x~y)'),
        );
    }

    /**
     * Get string operators
     *
     * @return array
     */
    public function getStringOperators()
    {
        return array(
            '=='       => $this->__('Equals'),
            '!='       => $this->__('Not Equals'),
            'in'       => $this->__('In (comma-separated)'),
            'contains' => $this->__('Contains'),
        );
    }

    /**
     * Check if attribute is numeric
     *
     * @param string $attribute
     * @return bool
     */
    public function isNumericAttribute($attribute)
    {
        $numericAttrs = array('weight', 'order_total', 'package_count');
        return in_array($attribute, $numericAttrs);
    }

    /**
     * Build order data array from a Mage_Sales_Model_Order for condition matching
     *
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    public function buildOrderDataFromOrder(Mage_Sales_Model_Order $order)
    {
        $shippingAddress = $order->getShippingAddress();
        $countryCode = $shippingAddress ? $shippingAddress->getCountryId() : '';
        $zipCode = $shippingAddress ? $shippingAddress->getPostcode() : '';

        // Get customer group
        $customerGroupId = $order->getCustomerGroupId();
        $customerGroup = '';
        if ($customerGroupId) {
            $group = Mage::getModel('customer/group')->load($customerGroupId);
            if ($group && $group->getId()) {
                $customerGroup = $group->getCode();
            }
        }

        return array(
            'country_code'   => $countryCode,
            'zip_code'       => $zipCode,
            'weight'         => (float)$order->getWeight(),
            'order_total'    => (float)$order->getGrandTotal(),
            'package_count'  => max(1, (int)$order->getTotalQtyOrdered()),
            'customer_group' => $customerGroup,
        );
    }

    /**
     * Build order data array from request params (AJAX context)
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @return array
     */
    public function buildOrderDataFromRequest(Mage_Core_Controller_Request_Http $request)
    {
        return array(
            'country_code'   => $request->getParam('country', ''),
            'zip_code'       => $request->getParam('zip', ''),
            'weight'         => (float)$request->getParam('weight', 0),
            'order_total'    => (float)$request->getParam('order_total', 0),
            'package_count'  => (int)$request->getParam('package_count', 1),
            'customer_group' => $request->getParam('customer_group', ''),
        );
    }

    /**
     * Find the first matching rule by conditions
     * Loads all active rules for the given country, then evaluates conditions
     *
     * @param string $countryCode
     * @param array  $orderData
     * @return XFE_LogisticsCarriersLabel_Model_Rule|null
     */
    public function findMatchingRule($countryCode, array $orderData)
    {
        $rules = Mage::getModel('xcarrierslabel/rule')->getCollection()
            ->addActiveFilter()
            ->addCountryFilter($countryCode);

        foreach ($rules as $rule) {
            if ($rule->matches($orderData)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Get partner label data by country code and optional shipping company ID.
     *
     * Returns an array with partner name and styled HTML for the
     * "Livré par [partner]" label that appears below the main title
     * on the carrier offer page.
     *
     * @param string      $countryCode       e.g. 'DE', 'AT', 'BE'
     * @param int|null    $shippingCompanyId Optional shipping company ID filter
     * @return array                          Keys: partner_name, label_html, bg_color, text_color
     */
    public function getPartnerLabelByCode($countryCode, $shippingCompanyId = null)
    {
        $collection = Mage::getModel('xcarrierslabel/rule')->getCollection()
            ->addActiveFilter()
            ->addCountryFilter($countryCode);

        if ($shippingCompanyId !== null) {
            $collection->addFieldToFilter('shipping_company_id', $shippingCompanyId);
        }

        $collection->setPageSize(1);

        if ($collection->getSize() === 0) {
            return array(
                'partner_name' => '',
                'label_html'   => '',
                'bg_color'     => '#EAF6FB',
                'text_color'   => '#7792FE',
            );
        }

        $rule = $collection->getFirstItem();

        $partnerName = $rule->getPartnerName();

        // Use per-rule colors if configured, otherwise default to PDF document spec
        $bgColor   = $rule->getBackgroundColor() ?: '#EAF6FB';
        $textColor = $rule->getTextColor() ?: '#7792FE';

        // Build the "Livré par [partner]" HTML label
        $labelHtml = sprintf(
            '<span class="partner-label-badge" style="background-color:%s;color:%s;">%s</span>',
            $bgColor,
            $textColor,
            $this->__('Livré par %s', $partnerName)
        );

        return array(
            'partner_name' => $partnerName,
            'label_html'   => $labelHtml,
            'bg_color'     => $bgColor,
            'text_color'   => $textColor,
        );
    }

    /**
     * Extract partner tracking number from the original tracking number.
     *
     * Rule: return the first 14 digits from the tracking number.
     * Example: "XW255247830JF" → "04893180837525" (stripped of non-digits, first 14).
     *
     * @param string $trackNumber Original tracking number
     * @return string
     */
    public function extractPartnerTrackingNumber($trackNumber)
    {
        $digits = preg_replace('/[^0-9]/', '', $trackNumber);
        if (strlen($digits) < 14) {
            return $digits;
        }
        return substr($digits, 0, 14);
    }

    /**
     * Get partner tracking row data for the order detail page.
     *
     * Given a country code, shipping company ID, and original tracking number,
     * looks up the partner rule, builds the international logistics tracking URL,
     * and returns everything needed to render the "N° suivi partenaire" row
     * with an external link button and a copy button.
     *
     * @param string      $countryCode       e.g. 'DE', 'AT', 'BE'
     * @param int|null    $shippingCompanyId Optional shipping company ID filter
     * @param string      $trackNumber       Original tracking number (e.g. "XW255247830JF")
     * @param string      $zip               Optional zip/postcode for the tracking URL
     * @return array Keys: partner_name, partner_tracking, tracking_url, row_html, has_data
     */
    public function getPartnerTrackingRow($countryCode, $shippingCompanyId, $trackNumber, $zip = '')
    {
        $emptyResult = array(
            'partner_name'     => '',
            'partner_tracking' => '',
            'tracking_url'     => '',
            'row_html'         => '',
            'has_data'         => false,
        );

        if (empty($countryCode) || empty($trackNumber)) {
            return $emptyResult;
        }

        // Look up the partner rule
        $collection = Mage::getModel('xcarrierslabel/rule')->getCollection()
            ->addActiveFilter()
            ->addCountryFilter($countryCode);

        if ($shippingCompanyId !== null) {
            $collection->addFieldToFilter('shipping_company_id', $shippingCompanyId);
        }

        $collection->setPageSize(1);

        if ($collection->getSize() === 0) {
            return $emptyResult;
        }

        $rule = $collection->getFirstItem();
        $partnerName = $rule->getPartnerName();

        // Extract partner tracking number: first 14 digits
        $partnerTracking = $this->extractPartnerTrackingNumber($trackNumber);
        if (empty($partnerTracking)) {
            return $emptyResult;
        }

        // Build the international logistics tracking URL
        $trackingUrl = $rule->buildTrackingUrl($partnerTracking, $zip);

        // Build the HTML row:
        // N° suivi partenaire (DPD): 04893180837525 [🔗] [📋]
        $rowHtml = '<div class="partner-tracking-row">'
            . '<span class="label">' . $this->__('N° suivi partenaire') . '</span> '
            . '<span class="partner-name">(' . $this->escapeHtml($partnerName) . ')</span>'
            . '<span class="tracking-number">: ' . $this->escapeHtml($partnerTracking) . '</span>'
            . '<span class="tracking-actions">';

        // External link button (opens international logistics URL in new tab)
        if ($trackingUrl) {
            $rowHtml .= '<a href="' . $trackingUrl . '" target="_blank" rel="noopener" '
                . 'class="tracking-icon" title="' . $this->__('Track on partner website') . '">'
                . '<svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">'
                . '<path d="M10.5 1.5L5.5 6.5M10.5 1.5H7M10.5 1.5V5M11 7.5V10C11 10.5523 10.5523 11 10 11H2C1.44772 11 1 10.5523 1 10V2C1 1.44772 1.44772 1 2 1H4.5" '
                . 'stroke="#7792FE" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>'
                . '</svg></a>';
        }

        // Copy button
        $rowHtml .= '<a href="javascript:void(0)" class="tracking-icon copy-link" '
            . 'onclick="copyPartnerTracking(this, \'' . $this->escapeHtml($partnerTracking) . '\')" '
            . 'title="' . $this->__('Copy tracking number') . '">'
            . '<svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">'
            . '<path d="M3.5 8.5H2.5C2.10218 8.5 1.72064 8.34196 1.43934 8.06066C1.15804 7.77936 1 7.39782 1 7V2.5C1 2.10218 1.15804 1.72064 1.43934 1.43934C1.72064 1.15804 2.10218 1 2.5 1H7C7.39782 1 7.77936 1.15804 8.06066 1.43934C8.34196 1.72064 8.5 2.10218 8.5 2.5V3.5M5 11H9.5C9.89782 11 10.2794 10.842 10.5607 10.5607C10.842 10.2794 11 9.89782 11 9.5V5C11 4.60218 10.842 4.22064 10.5607 3.93934C10.2794 3.65804 9.89782 3.5 9.5 3.5H5C4.60218 3.5 4.22064 3.65804 3.93934 3.93934C3.65804 4.22064 3.5 4.60218 3.5 5V9.5C3.5 9.89782 3.65804 10.2794 3.93934 10.5607C4.22064 10.842 4.60218 11 5 11Z" '
            . 'stroke="#7792FE" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>'
            . '</svg></a>';

        $rowHtml .= '<span class="copy-success" style="display:none;">' . $this->__('Copied!') . '</span>'
            . '</span>'
            . '</div>';

        return array(
            'partner_name'     => $partnerName,
            'partner_tracking' => $partnerTracking,
            'tracking_url'     => $trackingUrl,
            'row_html'         => $rowHtml,
            'has_data'         => true,
        );
    }
}
