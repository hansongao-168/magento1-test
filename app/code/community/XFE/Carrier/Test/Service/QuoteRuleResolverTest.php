<?php
require_once __DIR__ . '/../../../../../../../app/Mage.php';
Mage::app();

/**
 * Test stub for QuoteRuleResolver.
 *
 * 项目里其他测试（如 ResolverIntegrationTest、OrderContextBuilderTest）都使用真实
 * 类 + fixture，不使用 PHPUnit 的 getMock() 框架——这跟 PHPUnit 3.7.21 + PHP 7.3
 * 的兼容性也吻合：getMock() 在参数被传递时会走 IsEqual -> ComparatorFactory ->
 * PHPUnit_Framework_Comparator_DOMDocument 路径，而后者的 assertEquals 签名
 * (`array &$processed = array()`) 在 PHP 7.3 触发非兼容声明致命错误。
 *
 * 本测试遵循同款 no-mock 约定：通过子类 QuoteRuleResolver 并直接覆盖其三个
 * public test seam (_builder / _registry / _carrierIdForCode) 注入桩对象。
 */
class XFE_Carrier_Test_Service_QuoteRuleResolverStub extends XFE_Carrier_Model_Service_QuoteRuleResolver
{
    /** @var XFE_Carrier_Model_Service_Rule_QuoteContextBuilder */
    public $stubBuilder;
    /** @var object 鸭子类型：只需具备 ruleResolver() 方法 */
    public $stubRegistry;
    /** @var int */
    public $stubCarrierId = 0;

    public function _builder()
    {
        return $this->stubBuilder;
    }

    public function _registry()
    {
        return $this->stubRegistry;
    }

    public function _carrierIdForCode($carrierCode)
    {
        return $this->stubCarrierId;
    }
}

/**
 * Builder stub: 行为像 QuoteContextBuilder 但不读 quote 真实字段。
 * QuoteRuleResolver 只需要 setQuote()->build() 返回一个 MatchContext。
 */
class XFE_Carrier_Test_Service_StubQuoteContextBuilder extends XFE_Carrier_Model_Service_Rule_QuoteContextBuilder
{
    /** @var XFE_Carrier_Model_Service_Rule_MatchContext */
    public $stubContext;

    public function setQuote(Varien_Object $quote)
    {
        return $this;
    }

    public function build()
    {
        if ($this->stubContext === null) {
            $this->stubContext = new XFE_Carrier_Model_Service_Rule_MatchContext();
        }
        return $this->stubContext;
    }
}

/**
 * 鸭子类型 registry stub: 仅暴露 ruleResolver() 方法返回构造时指定的 resolver。
 * 不继承 Registry，避免 PHP 7.3 上"static -> non-static"签名变更错误。
 */
class XFE_Carrier_Test_Service_StubRegistryForQuote
{
    /** @var XFE_Carrier_Model_Service_Rule_Resolver */
    public $stubResolverService;

    public function ruleResolver()
    {
        return $this->stubResolverService;
    }
}

/**
 * Resolver stub: 直接记录调用参数并返回预设值。
 */
class XFE_Carrier_Test_Service_StubResolverForQuote extends XFE_Carrier_Model_Service_Rule_Resolver
{
    /** @var int|null */
    public $stubReturn = null;
    public $lastCarrierId = null;
    public $lastTargetType = null;
    public $lastContext = null;
    public $lastFallback = null;
    public $callCount = 0;

    public function __construct()
    {
        // 跳过 parent::__construct (需要 Evaluator)，测试中不调用 resolver 内部算法。
    }

    public function resolveOne($carrierId, $targetType, XFE_Carrier_Model_Service_Rule_MatchContext $context, $fallback = true)
    {
        $this->lastCarrierId = (int)$carrierId;
        $this->lastTargetType = $targetType;
        $this->lastContext = $context;
        $this->lastFallback = $fallback;
        $this->callCount++;
        return $this->stubReturn;
    }
}

class XFE_Carrier_Test_Service_QuoteRuleResolverTest extends PHPUnit_Framework_TestCase
{
    public function testResolveLogoReturnsMatch()
    {
        $quote = new Mage_Sales_Model_Quote();

        $builder = new XFE_Carrier_Test_Service_StubQuoteContextBuilder();
        $builder->stubContext = new XFE_Carrier_Model_Service_Rule_MatchContext();

        $resolverService = new XFE_Carrier_Test_Service_StubResolverForQuote();
        $resolverService->stubReturn = 42;

        $registry = new XFE_Carrier_Test_Service_StubRegistryForQuote();
        $registry->stubResolverService = $resolverService;

        $stub = new XFE_Carrier_Test_Service_QuoteRuleResolverStub();
        $stub->stubBuilder = $builder;
        $stub->stubRegistry = $registry;
        $stub->stubCarrierId = 7;

        $result = $stub->resolveLogo($quote, 'xfe_carrier');

        $this->assertSame(42, $result['logo_id']);
        $this->assertSame(7, $resolverService->lastCarrierId);
        $this->assertSame(XFE_Carrier_Model_Service_Rule_Resolver::TARGET_LOGO, $resolverService->lastTargetType);
        $this->assertFalse($resolverService->lastFallback);
    }

    public function testThrowsWhenNoMatch()
    {
        $quote = new Mage_Sales_Model_Quote();

        $builder = new XFE_Carrier_Test_Service_StubQuoteContextBuilder();
        $builder->stubContext = new XFE_Carrier_Model_Service_Rule_MatchContext();

        $resolverService = new XFE_Carrier_Test_Service_StubResolverForQuote();
        $resolverService->stubReturn = null;

        $registry = new XFE_Carrier_Test_Service_StubRegistryForQuote();
        $registry->stubResolverService = $resolverService;

        $stub = new XFE_Carrier_Test_Service_QuoteRuleResolverStub();
        $stub->stubBuilder = $builder;
        $stub->stubRegistry = $registry;
        $stub->stubCarrierId = 7;

        $this->setExpectedException('XFE_Carrier_Exception_NoRuleMatch');
        $stub->resolveLogo($quote, 'xfe_carrier');
    }
}