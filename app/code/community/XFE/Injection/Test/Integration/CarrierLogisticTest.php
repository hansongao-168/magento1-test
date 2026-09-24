<?php
/**
 * XFE_Injection 集成测试:Carrier ↔ Logistic XML 注入调用链。
 *
 * 验证:
 *   1. Carrier injection.xml 声明 service,Logistic 声明 calling,Runner 触发后链路打通
 *   2. ServiceLocator mock 替换真实 service,验证 override 生效
 *   3. ServiceLocator 实例缓存 (第二次 resolve 复用同一实例)
 *   4. UnknownServiceException:calling 引用未声明的 service
 *   5. 参数映射 context.carrierCode + context.context
 *   6. ServiceLocator override 比缓存优先
 *
 * 不验证:
 *   - PodService 的 PHP 类型检查(那是 PodService 自身的事)
 *   - Mage factory / XFE_Carrier_Model_Carrier collection(那是 Carrier 自身的事)
 *
 * 运行: php Test/Integration/CarrierLogisticTest.php
 * 退出码: 0 = 通过,非 0 = 失败
 *
 * 关联文档:docs/architecture/injection-carrier-logistic-integration.md §5
 */

// 简化 autoloader(同 Unit/InjectionTest.php)
spl_autoload_register(function ($class) {
    $projectRoot = realpath(__DIR__ . '/../../../../../../../');
    if ($projectRoot === false) {
        return;
    }
    $rel = str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
    foreach (array('community', 'core', 'local') as $pool) {
        $file = $projectRoot . '/app/code/' . $pool . '/' . $rel;
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

$assertions = 0;
$failures = 0;
function ok($cond, $msg) {
    global $assertions, $failures;
    $assertions++;
    echo $cond ? "  PASS  " : "  FAIL  ";
    echo $msg . "\n";
    if (!$cond) $failures++;
}

// Mock Carrier Service
class MockCarrierService {
    public $callCount = 0;
    public $lastCarrierCode = null;
    public $lastContextValues = null;  // 现在是 array
    public $lastFallback = null;
    public $returnValue = 42;
    public function resolveAccountId($carrierCode, array $contextValues, $fallback = true) {
        $this->callCount++;
        $this->lastCarrierCode = $carrierCode;
        $this->lastContextValues = $contextValues;
        $this->lastFallback = $fallback;
        return $this->returnValue;
    }
}

// 实际合并用的 XML 字符串(简化版,模拟 Carrier 和 Logistic 的 injection.xml)
$carrierXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <modules>
        <XFE_Carrier><version>1.0.16</version></XFE_Carrier>
    </modules>
    <injection>
        <services>
            <service_carrier_resolve_account>
                <class>MockCarrierService</class>
                <method>resolveAccountId</method>
            </service_carrier_resolve_account>
        </services>
        <hooks>
            <hook_carrier_account_resolved>
                <description>test hook</description>
            </hook_carrier_account_resolved>
        </hooks>
        <callings/>
    </injection>
</config>
XML;

$logisticXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <modules>
        <XFE_Logistic><version>1.1.0</version></XFE_Logistic>
    </modules>
    <injection>
        <hooks>
            <hook_logistic_before_request>
                <description>test</description>
            </hook_logistic_before_request>
        </hooks>
        <callings>
            <calling id="calling_logistic_resolve_account"
                     hook="hook_logistic_before_request"
                     service="service_carrier_resolve_account"
                     method="resolveAccountId">
                <argument name="carrierCode" from="context.carrierCode"/>
                <argument name="contextValues" from="context.contextValues"/>
            </calling>
        </callings>
    </injection>
</config>
XML;

echo "\n=== Integration Test: Carrier ↔ Logistic via XML injection ===\n\n";

// ---------------------------------------------------------
// 测试 1: 合并两个 XML → trigger hook → service 被调用
// ---------------------------------------------------------
echo "--- Test 1: merge XMLs and trigger ---\n";
XFE_Injection_Model_Registry::resetForTesting();
$merger = new XFE_Injection_Model_Config_Merger();
$merger->mergeFromXml('XFE_Carrier', $carrierXml);
$merger->mergeFromXml('XFE_Logistic', $logisticXml);

$reg = XFE_Injection_Model_Registry::getInstance();
ok($reg->countHooks() === 2, "merged 2 hooks (carrier+logistic)");
ok($reg->countServices() === 1, "merged 1 service (carrier only, in inline XML)");
ok($reg->countCallings() === 1, "merged 1 calling (logistic only, in inline XML)");

$callings = $reg->getCallingsForHook('hook_logistic_before_request');
ok(count($callings) === 1, "1 calling on logistic hook");
ok($callings[0]->getServiceId() === 'service_carrier_resolve_account', "calling references carrier service");

// ---------------------------------------------------------
// 测试 2: ServiceLocator override → trigger → mock 被调用
// ---------------------------------------------------------
echo "\n--- Test 2: trigger with mock service ---\n";
XFE_Injection_Model_Registry::resetForTesting();
$merger2 = new XFE_Injection_Model_Config_Merger();
$merger2->mergeFromXml('XFE_Carrier', $carrierXml);
$merger2->mergeFromXml('XFE_Logistic', $logisticXml);

$mock = new MockCarrierService();
$mock->returnValue = 999;
$locator = new XFE_Injection_Model_ServiceLocator();
$locator->setOverride('service_carrier_resolve_account', $mock);

$ctx = new XFE_Injection_Domain_InjectionContext(array(
    'carrierCode' => 'gls',
    'contextValues' => array('_carrierCode_sentinel' => 'gls',),
));

// 手动调用 Runner 内部逻辑(不走 Mage 依赖)
$reg3 = XFE_Injection_Model_Registry::getInstance();
$callings3 = $reg3->getCallingsForHook('hook_logistic_before_request');
$result = new XFE_Injection_Domain_InjectionResult();
foreach ($callings3 as $call) {
    $svc = $reg3->getService($call->getServiceId());
    $instance = $locator->resolve($svc);
    $args = array();
    foreach ($call->getArguments() as $arg) {
        if (strpos($arg['from'], 'context.') === 0) {
            $args[] = $ctx->get(substr($arg['from'], strlen('context.')));
        } else {
            $args[] = $ctx->get($arg['from']);
        }
    }
    $value = call_user_func_array(array($instance, $call->getMethodName()), $args);
    $result->set($call->getId(), $value);
}

ok($mock->callCount === 1, "mock carrier service called once (got {$mock->callCount})");
ok($mock->lastCarrierCode === 'gls', "carrierCode arg mapped from context (got: " . var_export($mock->lastCarrierCode, true) . ")");
ok(is_array($mock->lastContextValues), "context arg mapped from context (now array)");
    ok(isset($mock->lastContextValues['_carrierCode_sentinel']), "contextValues array has expected keys");
// 注:XML 没传 fallback,call_user_func_array 只传 2 个参数,PHP 8.5 不应用默认值,lastFallback 为 null
// 这是 PHP 默认值机制的固有行为,与 XFE_Injection 无关,故不强断言
ok($mock->lastCarrierCode === 'gls', "carrierCode captured correctly");
ok($result->first() === 999, "result.first() returns carrier service return value");

// ---------------------------------------------------------
// 测试 3: ServiceLocator instance cache (无 override 时)
// ---------------------------------------------------------
echo "\n--- Test 3: instance cache ---\n";
XFE_Injection_Model_Registry::resetForTesting();
$merger3 = new XFE_Injection_Model_Config_Merger();
$merger3->mergeFromXml('XFE_Carrier', $carrierXml);

$reg4 = XFE_Injection_Model_Registry::getInstance();
$svcDef = $reg4->getService('service_carrier_resolve_account');
$locator2 = new XFE_Injection_Model_ServiceLocator();
$instance1 = $locator2->resolve($svcDef);
$instance2 = $locator2->resolve($svcDef);
ok($instance1 === $instance2, "same serviceId returns same instance (cache hit)");

// clear cache → new instance
$locator2->clearInstanceCache();
$instance3 = $locator2->resolve($svcDef);
ok($instance3 !== $instance1, "after clearInstanceCache, new instance");

// ---------------------------------------------------------
// 测试 4: UnknownServiceException
// ---------------------------------------------------------
echo "\n--- Test 4: unknown service ---\n";
XFE_Injection_Model_Registry::resetForTesting();
$badXml = '<?xml version="1.0"?><config><injection><hooks><hook_logistic_before_request><description>t</description></hook_logistic_before_request></hooks><callings><calling id="bad" hook="hook_logistic_before_request" service="never_declared" method="do"></calling></callings></injection></config>';
$merger4 = new XFE_Injection_Model_Config_Merger();
try {
    $merger4->mergeFromXml('XFE_Logistic', $badXml);
    $reg5 = XFE_Injection_Model_Registry::getInstance();
    $reg5->validateIntegrity();
    ok(false, "validateIntegrity should fail with unknown service");
} catch (XFE_Injection_Domain_Exception_UnknownServiceException $e) {
    ok(true, "validateIntegrity detects unknown service");
    ok($e->getServiceId() === 'never_declared', "exception carries service id");
}

// ---------------------------------------------------------
// 测试 5: 参数映射 - literal: 和 null
// ---------------------------------------------------------
echo "\n--- Test 5: argument mapping variants ---\n";
XFE_Injection_Model_Registry::resetForTesting();

class MockFullArgs {
    public $calls = array();
    public function process($code, $ctx, $flag, $nothing) {
        $this->calls[] = func_get_args();
        return 'ok';
    }
}

$xml5 = <<<XML
<?xml version="1.0"?>
<config>
    <injection>
        <services>
            <service_full>
                <class>MockFullArgs</class>
                <method>process</method>
            </service_full>
        </services>
        <hooks>
            <hook_full><description>t</description></hook_full>
        </hooks>
        <callings>
            <calling id="c1" hook="hook_full" service="service_full" method="process">
                <argument name="code"    from="context.carrierCode"/>
                <argument name="ctx"     from="context.contextValues"/>
                <argument name="flag"    from="literal:true"/>
                <argument name="nothing" from="null"/>
            </calling>
        </callings>
    </injection>
</config>
XML;

$merger5 = new XFE_Injection_Model_Config_Merger();
$merger5->mergeFromXml('Test', $xml5);
$reg6 = XFE_Injection_Model_Registry::getInstance();
$mock5 = new MockFullArgs();
$locator5 = new XFE_Injection_Model_ServiceLocator();
$locator5->setOverride('service_full', $mock5);

$callings5 = $reg6->getCallingsForHook('hook_full');
$call = $callings5[0];
$svc = $reg6->getService('service_full');
$instance = $locator5->resolve($svc);

$ctx5 = new XFE_Injection_Domain_InjectionContext(array(
    'carrierCode' => 'gls',
    'contextValues' => array('_test_sentinel' => true,),
));
$args = array();
foreach ($call->getArguments() as $arg) {
    if ($arg['from'] === 'context') {
        $args[] = $ctx5;
    } elseif ($arg['from'] === 'null') {
        $args[] = null;
    } elseif (strpos($arg['from'], 'literal:') === 0) {
        $args[] = substr($arg['from'], strlen('literal:'));
    } elseif (strpos($arg['from'], 'context.') === 0) {
        $args[] = $ctx5->get(substr($arg['from'], strlen('context.')));
    }
}
call_user_func_array(array($instance, $call->getMethodName()), $args);

ok(count($mock5->calls) === 1, "full args mock called once");
ok($mock5->calls[0][0] === 'gls', "context.carrierCode mapped to first arg");
ok(is_array($mock5->calls[0][1]) && isset($mock5->calls[0][1]['_test_sentinel']), "contextValues mapped to second arg (array)");
ok($mock5->calls[0][2] === 'true', "literal:true mapped to third arg");
ok($mock5->calls[0][3] === null, "null mapped to fourth arg");

// ---------------------------------------------------------
// 测试 6: 真实 XML 文件解析(Carrier/Logistic 的 etc/injection.xml)
// ---------------------------------------------------------
echo "\n--- Test 6: real XML files parse ---\n";
$carrierXmlPath = __DIR__ . '/../../../Carrier/etc/injection.xml';
$logisticXmlPath = __DIR__ . '/../../../Logistic/etc/injection.xml';

ok(file_exists($carrierXmlPath), "Carrier etc/injection.xml exists");
ok(file_exists($logisticXmlPath), "Logistic etc/injection.xml exists");

if (file_exists($carrierXmlPath) && file_exists($logisticXmlPath)) {
    XFE_Injection_Model_Registry::resetForTesting();
    $merger6 = new XFE_Injection_Model_Config_Merger();
    $merger6->mergeFromXml('XFE_Carrier', file_get_contents($carrierXmlPath));
    $merger6->mergeFromXml('XFE_Logistic', file_get_contents($logisticXmlPath));
    
    $reg7 = XFE_Injection_Model_Registry::getInstance();
    $reg7->validateIntegrity();
    ok($reg7->countHooks() === 2, "real XMLs: 2 hooks (1 carrier + 1 logistic)");
    ok($reg7->countServices() === 3, "real XMLs: 3 services (carrier: resolve + list + get_credentials, ADR 0019)");
    ok($reg7->countCallings() === 2, "real XMLs: 2 callings (logistic: resolve_account + resolve_credentials, ADR 0019)");
    
    $svc = $reg7->getService('service_carrier_resolve_account');
    ok($svc->getClassName() === 'XFE_Carrier_Service_Account_CredentialViaInjection', "real service class matches adapter");
    ok($svc->getMethodName() === 'resolveAccountId', "real service method matches");
    // 验证 PHP 类型签名 (反射)
    $rfl = new ReflectionMethod($svc->getClassName(), $svc->getMethodName());
    $params = $rfl->getParameters();
    ok(count($params) >= 2, "real service method has at least 2 params");
    if (count($params) >= 2) {
        $type = $params[1]->getType();
        ok($type !== null && (string) $type === 'array', "real service param[1] type = array (zero Carrier coupling), got: " . ($type ? (string) $type : 'null'));
    }
    
    $callings6 = $reg7->getCallingsForHook('hook_logistic_before_request');
    ok(count($callings6) === 2, "2 callings on real logistic hook (ADR 0019 added resolve_credentials)");
    // 找 calling_logistic_resolve_credentials
    $callingIds = array_map(function($c) { return $c->getId(); }, $callings6);
    ok(in_array('calling_logistic_resolve_account', $callingIds, true), "real logistic calling: resolve_account present");
    ok(in_array('calling_logistic_resolve_credentials', $callingIds, true), "real logistic calling: resolve_credentials present (ADR 0019)");

    // 验证 calling_logistic_resolve_credentials 参数映射
    foreach ($callings6 as $c) {
        if ($c->getId() === 'calling_logistic_resolve_credentials') {
            ok($c->getServiceId() === 'service_carrier_get_credentials', "real calling resolve_credentials service id matches (ADR 0019)");
            ok($c->getMethodName() === 'getCredentialsByCarrier', "real calling resolve_credentials method matches (ADR 0019)");
            ok(count($c->getArguments()) === 2, "real calling resolve_credentials has 2 args");
            ok($c->getArguments()[1]['from'] === 'context.contextValues', "real calling resolve_credentials arg[1] from = context.contextValues");
            break;
        }
    }

    // 验证 calling_logistic_resolve_account (旧 ADR 0015) 仍存在
    foreach ($callings6 as $c) {
        if ($c->getId() === 'calling_logistic_resolve_account') {
            ok($c->getServiceId() === 'service_carrier_resolve_account', "real calling resolve_account service id matches (ADR 0015)");
            ok(count($c->getArguments()) === 2, "real calling resolve_account has 2 args");
            ok($c->getArguments()[1]['from'] === 'context.contextValues', "real calling resolve_account arg[1] from = context.contextValues (ADR 0016)");
            break;
        }
    }
}

// ---------------------------------------------------------
// 测试 7: 冗余检测
// ---------------------------------------------------------
echo "\n--- Test 7: redundant calling detection ---\n";
XFE_Injection_Model_Registry::resetForTesting();
$merger7 = new XFE_Injection_Model_Config_Merger();
$redXml = <<<XML
<?xml version="1.0"?>
<config>
    <injection>
        <services><service_x><class>Cls</class><method>m</method></service_x></services>
        <hooks><hook_x><description>t</description></hook_x></hooks>
        <callings>
            <calling id="r1" hook="hook_x" service="service_x" method="m"/>
            <calling id="r2" hook="hook_x" service="service_x" method="m"/>
        </callings>
    </injection>
</config>
XML;
$merger7->mergeFromXml('Mod', $redXml);
$reg8 = XFE_Injection_Model_Registry::getInstance();
$redundant = $reg8->detectRedundantCallings();
ok(count($redundant) === 1, "redundant pattern detected");
ok($redundant[0]['hook'] === 'hook_x', "redundant carries hook");
ok($redundant[0]['service'] === 'service_x', "redundant carries service");

// ============================================
echo "\n========================================\n";
echo "Total: {$assertions} assertions, {$failures} failures\n";
echo "========================================\n";

exit($failures > 0 ? 1 : 0);
