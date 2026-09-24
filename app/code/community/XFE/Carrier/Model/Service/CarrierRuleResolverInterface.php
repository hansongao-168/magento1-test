<?php
/**
 * 规则解析对外接口。
 *
 * 实现类负责从 Order/Quote 构造 MatchContext 并调用底层 Resolver。
 * 未命中时应抛出 XFE_Carrier_Exception_NoRuleMatch。
 *
 * 类型说明：参数使用 Varien_Object 因为 Order 与 Quote 没有共同的领域
 * 父类；二者均继承 Varien_Object。生产调用方传 Mage_Sales_Model_Order
 * 或 Mage_Sales_Model_Quote 皆可。
 */
interface XFE_Carrier_Model_Service_CarrierRuleResolverInterface
{
    /**
     * 解析 Logo 模块，返回 logo 链接与标签。
     *
     * @param Varien_Object $order 实参为 Mage_Sales_Model_Order 或 Mage_Sales_Model_Quote
     * @param string|null $carrierCode 可选；缺省时取 $order->getShippingCarrierCode()
     * @return array{logo_id:int}
     * @throws XFE_Carrier_Exception_NoRuleMatch
     */
    public function resolveLogo(Varien_Object $order, $carrierCode = null);

    /**
     * 解析账号模块，返回账号 ID。
     *
     * @param Varien_Object $order
     * @param string|null $carrierCode
     * @return array{account_id:int}
     * @throws XFE_Carrier_Exception_NoRuleMatch
     */
    public function resolveAccount(Varien_Object $order, $carrierCode = null);

    /**
     * 通用模块解析。
     *
     * @param Varien_Object $order
     * @param string $moduleCode  Resolver::TARGET_LOGO 或 Resolver::TARGET_ACCOUNT
     * @param string|null $carrierCode
     * @return array
     * @throws XFE_Carrier_Exception_NoRuleMatch
     */
    public function resolveFor(Varien_Object $order, $moduleCode, $carrierCode = null);
}