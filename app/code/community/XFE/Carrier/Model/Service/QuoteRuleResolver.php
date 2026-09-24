<?php
/**
 * 面向 Mage_Sales_Model_Quote 的规则解析门面。
 *
 * 与 OrderRuleResolver 同构，但使用 QuoteContextBuilder，并补一条
 * 从 shipping_method 拆分 carrier_code 的回退路径：
 *   xfe_carrier_2  ->  carrier_code = "xfe_carrier"
 *
 * 类型声明沿用接口的 Varien_Object；Order 与 Quote 都继承自 Varien_Object，
 * 生产调用方传 Mage_Sales_Model_Order 或 Mage_Sales_Model_Quote 皆可。
 */
class XFE_Carrier_Model_Service_QuoteRuleResolver implements XFE_Carrier_Model_Service_CarrierRuleResolverInterface
{
    /**
     * @var XFE_Carrier_Model_Service_Rule_QuoteContextBuilder
     */
    protected $_builder;

    /**
     * @var XFE_Carrier_Model_Service_Registry
     */
    protected $_registry;

    public function __construct()
    {
        $this->_builder  = Mage::getModel('xfe_carrier/service_rule_quoteContextBuilder');
        $this->_registry = Mage::getSingleton('xfe_carrier/service_registry');
    }

    /** Test seam. */
    public function _builder()
    {
        return $this->_builder;
    }

    /** Test seam. */
    public function _registry()
    {
        return $this->_registry;
    }

    /**
     * 从 $carrierCode 查 xfe_carrier.entity_id；找不到返回 0。
     * Test seam: 替换为 mock 后可不必走数据库。
     *
     * @param string $carrierCode
     * @return int
     */
    public function _carrierIdForCode($carrierCode)
    {
        if (!$carrierCode) {
            return 0;
        }
        $carrier = Mage::getModel('xfe_carrier/carrier')->load($carrierCode, 'code');
        return $carrier && $carrier->getId() ? (int)$carrier->getId() : 0;
    }

    /**
     * @param Varien_Object $quote  实参为 Mage_Sales_Model_Quote
     * @param string|null $carrierCode
     * @return array{logo_id:int}
     * @throws XFE_Carrier_Exception_NoRuleMatch
     */
    public function resolveLogo(Varien_Object $quote, $carrierCode = null)
    {
        return $this->resolveFor($quote, XFE_Carrier_Model_Service_Rule_Resolver::TARGET_LOGO, $carrierCode);
    }

    /**
     * @param Varien_Object $quote  实参为 Mage_Sales_Model_Quote
     * @param string|null $carrierCode
     * @return array{account_id:int}
     * @throws XFE_Carrier_Exception_NoRuleMatch
     */
    public function resolveAccount(Varien_Object $quote, $carrierCode = null)
    {
        return $this->resolveFor($quote, XFE_Carrier_Model_Service_Rule_Resolver::TARGET_ACCOUNT, $carrierCode);
    }

    /**
     * 通用 module 解析入口（保留向后兼容）。
     *
     * @param Varien_Object $quote 实参为 Mage_Sales_Model_Quote
     * @param string $moduleCode  Resolver::TARGET_LOGO 或 Resolver::TARGET_ACCOUNT
     * @param string|null $carrierCode
     * @return array
     * @throws XFE_Carrier_Exception_NoRuleMatch
     */
    public function resolveFor(Varien_Object $quote, $moduleCode, $carrierCode = null)
    {
        if ($carrierCode === null) {
            if (method_exists($quote, 'getShippingCarrierCode')) {
                $carrierCode = $quote->getShippingCarrierCode();
            }
            if (!$carrierCode && method_exists($quote, 'getShippingMethod')) {
                $method = (string)$quote->getShippingMethod();
                if ($method !== '' && strpos($method, '_') !== false) {
                    $carrierCode = explode('_', $method, 2)[0];
                }
            }
        }
        $carrierId = $this->_carrierIdForCode($carrierCode);
        if (!$carrierId) {
            throw new XFE_Carrier_Exception_NoRuleMatch(
                sprintf('No carrier matched code "%s".', (string)$carrierCode)
            );
        }

        $context = $this->_builder()->setQuote($quote)->build();
        $resolverService = $this->_registry()->ruleResolver();

        $id = $resolverService->resolveOne($carrierId, $moduleCode, $context, false);
        if ($id === null) {
            throw new XFE_Carrier_Exception_NoRuleMatch(
                sprintf('No carrier rule matched for carrier_id=%d, module=%s.', $carrierId, $moduleCode)
            );
        }

        return $moduleCode === XFE_Carrier_Model_Service_Rule_Resolver::TARGET_LOGO
            ? array('logo_id' => (int)$id)
            : array('account_id' => (int)$id);
    }
}