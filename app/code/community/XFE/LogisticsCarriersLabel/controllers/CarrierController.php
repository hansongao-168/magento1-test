<?php
/**
 * XFE LogisticsCarriersLabel Carrier Front Controller
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_CarrierController extends Mage_Core_Controller_Front_Action
{
    /**
     * Display carrier offer page (承运商列表页)
     */
    public function offreAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * AJAX endpoint to get partner info by country code
     * Supports condition matching: additional params (weight, order_total, package_count, customer_group)
     * can be passed to evaluate rule conditions
     */
    public function partnerAction()
    {
        $countryCode = $this->getRequest()->getParam('country');
        $zip = $this->getRequest()->getParam('zip', '');

        if (!$countryCode) {
            $this->getResponse()->setHeader('Content-Type', 'application/json');
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array('error' => 'Country code required')));
            return;
        }

        /** @var XFE_LogisticsCarriersLabel_Helper_Data $helper */
        $helper = Mage::helper('xcarrierslabel');
        $orderData = $helper->buildOrderDataFromRequest($this->getRequest());
        $rule = $helper->findMatchingRule($countryCode, $orderData);

        if (!$rule || !$rule->getId()) {
            $this->getResponse()->setHeader('Content-Type', 'application/json');
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array('error' => 'No partner found')));
            return;
        }

        $data = array(
            'rule_id'       => $rule->getId(),
            'country_code'  => $rule->getCountryCode(),
            'country_name'  => $rule->getCountryName(),
            'partner_name'  => $rule->getPartnerName(),
            'display_title' => $rule->getDisplayTitle(),
            'tracking_url'  => $rule->buildTrackingUrl('', $zip),
        );

        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($data));
    }
}
