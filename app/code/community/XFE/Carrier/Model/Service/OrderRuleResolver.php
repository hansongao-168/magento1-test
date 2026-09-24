<?php
/**
 * 面向 Mage_Sales_Model_Order 的规则解析门面。
 *
 * 负责：
 *   - 用 OrderContextBuilder 构造 MatchContext
 *   - 由 $carrierCode 解析出 xfe_carrier.entity_id
 *   - 调用 Registry::ruleResolver()->resolveOne(...) 解析 logo 或 account
 *   - 未命中抛出 XFE_Carrier_Exception_NoRuleMatch
 *
 * 未命中时的处理策略：禁止 fallback。设计意图是"符合才输出正确数据，
 * 不符合报错"，因此本类在内部固定传 $fallback=false。
 */
class XFE_Carrier_Model_Service_OrderRuleResolver implements XFE_Carrier_Model_Service_CarrierRuleResolverInterface
{
    /**
     * @var XFE_Carrier_Model_Service_Rule_OrderContextBuilder
     */
    protected $_builder;

    /**
     * @var XFE_Carrier_Model_Service_Registry
     */
    protected $_registry;

    public function __construct()
    {
        $this->_builder  = Mage::getModel('xfe_carrier/service_rule_orderContextBuilder');
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
     * @param Varien_Object $order  实参为 Mage_Sales_Model_Order
     * @param string|null $carrierCode 可选；缺省时取 $order->getShippingCarrierCode()
     * @return array{logo_id:int}
     * @throws XFE_Carrier_Exception_NoRuleMatch
     */
    public function resolveLogo(Varien_Object $order, $carrierCode = null)
    {
        return $this->resolveFor($order, XFE_Carrier_Model_Service_Rule_Resolver::TARGET_LOGO, $carrierCode);
    }

    /**
     * @param Varien_Object $order  实参为 Mage_Sales_Model_Order
     * @param string|null $carrierCode 可选；缺省时取 $order->getShippingCarrierCode()
     * @return array{account_id:int}
     * @throws XFE_Carrier_Exception_NoRuleMatch
     */
    public function resolveAccount(Varien_Object $order, $carrierCode = null)
    {
        return $this->resolveFor($order, XFE_Carrier_Model_Service_Rule_Resolver::TARGET_ACCOUNT, $carrierCode);
    }

    /**
     * 通用 module 解析入口（保留向后兼容）。
     *
     * @param Varien_Object $order  实参为 Mage_Sales_Model_Order
     * @param string $moduleCode  Resolver::TARGET_LOGO 或 Resolver::TARGET_ACCOUNT
     * @param string|null $carrierCode
     * @return array
     * @throws XFE_Carrier_Exception_NoRuleMatch
     */
    public function resolveFor(Varien_Object $order, $moduleCode, $carrierCode = null)
    {
        if ($carrierCode === null) {
            $carrierCode = method_exists($order, 'getShippingCarrierCode')
                ? $order->getShippingCarrierCode()
                : null;
        }
        $carrierId = $this->_carrierIdForCode($carrierCode);
        if (!$carrierId) {
            throw new XFE_Carrier_Exception_NoRuleMatch(
                sprintf('No carrier matched code "%s".', (string)$carrierCode)
            );
        }

        $context = $this->_builder()->setOrder($order)->build();
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