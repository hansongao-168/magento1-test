<?php
/**
 * XFE_Injection 集成测试:PodService 主流路径接入 XML 注入 (ADR 0019)。
 *
 * 验证:
 *   1. PodService::_resolveCredentialsViaInjection 私有方法签名(2 params, param[1]=array)
 *   2. PodService::getProofOfDelivery 签名未改(1 param: trackId)
 *   3. PodService::resolveCredentialsViaInjection 旧 public API 保留
 *   4. XFE_Carrier_Service_Account_CredentialViaInjection::getCredentialsByCarrier 签名(array)
 *   5. 注入路径 + fallback 路径端到端(mock service 替换)
 *   6. PodService.php 中 0 处 XFE_Carrier_ 类型耦合
 *
 * 不验证:
 *   - Mage factory / DB 真实查询(那是 Carrier 自身的事)
 *   - network connectivity(那是 gateway 自身的事)
 *
 * 运行: php Test/Integration/PodServiceMainPathTest.php
 * 退出码: 0 = 通过,非 0 = 失败
 *
 * 关联文档:docs/architecture/decouple-podservice-main-path.md §5.2
 */

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

// Mock service 用于端到端测试
class MockCredentialsService {
    public $callCount = 0;
    public $lastCarrierCode = null;
    public $lastContextValues = null;
    public $returnValue = null; // array or null
    public function getCredentialsByCarrier($carrierCode, array $contextValues = array()) {
        $this->callCount++;
        $this->lastCarrierCode = $carrierCode;
        $this->lastContextValues = $contextValues;
        return $this->returnValue;
    }
}

echo "\n=== Integration Test: PodService main path via XML injection (ADR 0019) ===\n\n";

// ---------------------------------------------------------
// Test 1: PodService 反射验证
// ---------------------------------------------------------
echo "--- Test 1: PodService reflection ---\n";

$rfl = new ReflectionMethod('XFE_Logistic_Service_PodService', '_resolveCredentialsViaInjection');
$params = $rfl->getParameters();
ok($rfl->isPrivate(), "_resolveCredentialsViaInjection is private (ADR 0019)");
ok(count($params) === 2, "_resolveCredentialsViaInjection has 2 params, got: " . count($params));
ok($params[0]->getName() === 'carrierCode', "param[0] name = carrierCode");
$type1 = $params[1]->getType();
$type1Str = $type1 ? (string)$type1 : 'null';
ok($type1Str === 'array', "param[1] type = array (ADR 0016), got: " . $type1Str);

$rfl2 = new ReflectionMethod('XFE_Logistic_Service_PodService', 'getProofOfDelivery');
$params2 = $rfl2->getParameters();
ok(count($params2) === 1, "getProofOfDelivery still has 1 param");
ok($params2[0]->getName() === 'trackId', "getProofOfDelivery param[0] = trackId");
ok($rfl2->isPublic(), "getProofOfDelivery still public");

$rfl3 = new ReflectionMethod('XFE_Logistic_Service_PodService', 'resolveCredentialsViaInjection');
$params3 = $rfl3->getParameters();
ok($rfl3->isPublic(), "resolveCredentialsViaInjection still public (backward compat)");
ok(count($params3) === 2, "resolveCredentialsViaInjection has 2 params");
$type3 = $params3[1]->getType();
ok($type3 !== null && (string)$type3 === 'array', "resolveCredentialsViaInjection param[1] type = array");

// ---------------------------------------------------------
// Test 2: Carrier 适配器新方法签名
// ---------------------------------------------------------
echo "\n--- Test 2: Carrier adapter getCredentialsByCarrier signature ---\n";

$rfl4 = new ReflectionMethod('XFE_Carrier_Service_Account_CredentialViaInjection', 'getCredentialsByCarrier');
$params4 = $rfl4->getParameters();
ok($rfl4->isPublic(), "getCredentialsByCarrier is public");
ok(count($params4) === 2, "getCredentialsByCarrier has 2 params, got: " . count($params4));
ok($params4[0]->getName() === 'carrierCode', "getCredentialsByCarrier param[0] = carrierCode");
$type4 = $params4[1]->getType();
$type4Str = $type4 ? (string)$type4 : 'null';
ok($type4Str === 'array', "getCredentialsByCarrier param[1] type = array (zero XFE_Carrier coupling), got: " . $type4Str);

// ---------------------------------------------------------
// Test 3: PodService 中零 XFE_Carrier_ 类型耦合
// ---------------------------------------------------------
echo "\n--- Test 3: PodService zero XFE_Carrier type coupling ---\n";
$podServiceFile = __DIR__ . '/../../../Logistic/Service/PodService.php';
ok(file_exists($podServiceFile), "PodService.php exists");

if (file_exists($podServiceFile)) {
    $lines = file($podServiceFile);
    $realHits = array();
    foreach ($lines as $line) {
        $trimmed = trim($line);
        // 跳过 PHPDoc 注释行(以 * 或 // 开头)
        if (preg_match('/^\s*[\\*]/', $line)) continue;
        // 真实类型签名/导入/实例化
        if (preg_match('/^(use|new|\\(|\$|extends) XFE_Carrier_[A-Za-z_]+/', $line)
            || preg_match('/XFE_Carrier_[A-Za-z_]+::/', $line)) {
            $realHits[] = $trimmed;
        }
    }
    ok(count($realHits) === 0, "PodService.php 0 处 XFE_Carrier_ PHP 类型签名 (got: " . count($realHits) . ")");
}

// ---------------------------------------------------------
// Test 4: 注入路径成功 → 拿到凭据 array
// ---------------------------------------------------------
echo "\n--- Test 4: injection success path ---\n";
XFE_Injection_Model_Registry::resetForTesting();

// 加载 3 个真实 XML(Injection + Carrier + Logistic)
$selfXml = __DIR__ . '/../../etc/injection.xml';
$carrierXml = __DIR__ . '/../../../Carrier/etc/injection.xml';
$logisticXml = __DIR__ . '/../../../Logistic/etc/injection.xml';

ok(file_exists($selfXml), "XFE_Injection self XML exists");
ok(file_exists($carrierXml), "Carrier XML exists");
ok(file_exists($logisticXml), "Logistic XML exists");

if (file_exists($selfXml) && file_exists($carrierXml) && file_exists($logisticXml)) {
    $merger = new XFE_Injection_Model_Config_Merger();
    $merger->mergeFromXml('XFE_Injection', file_get_contents($selfXml));
    $merger->mergeFromXml('XFE_Carrier', file_get_contents($carrierXml));
    $merger->mergeFromXml('XFE_Logistic', file_get_contents($logisticXml));

    $reg = XFE_Injection_Model_Registry::getInstance();
    $reg->validateIntegrity();
    ok($reg->hasService('service_carrier_get_credentials'), "service_carrier_get_credentials registered");
    ok($reg->hasService('service_carrier_resolve_account'), "service_carrier_resolve_account still registered");
    ok($reg->hasService('service_carrier_list_accounts'), "service_carrier_list_accounts still registered");

    $svc = $reg->getService('service_carrier_get_credentials');
    ok($svc->getClassName() === 'XFE_Carrier_Service_Account_CredentialViaInjection', "service class = adapter");
    ok($svc->getMethodName() === 'getCredentialsByCarrier', "service method = getCredentialsByCarrier");

    $logisticCallings = $reg->getCallingsForHook('hook_logistic_before_request');
    ok(count($logisticCallings) === 2, "logistic hook has 2 callings (resolve_account + resolve_credentials)");

    // 用 mock 替换新 service,模拟"成功路径"
    $mock = new MockCredentialsService();
    $mock->returnValue = array(
        'account_id'    => 42,
        'carrier_id'    => 7,
        'username'      => 'gls_user',
        'password'      => 'gls_pass',
        'endpoint_url'  => 'https://api.gls.fr',
        'api_key'       => null,
        'api_secret'    => null,
        'used_fallback' => true,
    );

    $locator = new XFE_Injection_Model_ServiceLocator();
    $locator->setOverride('service_carrier_get_credentials', $mock);

    // 复刻 PodService::_resolveCredentialsViaInjection 内部的核心调用链
    $injCtx = new XFE_Injection_Domain_InjectionContext(array(
        'carrierCode'   => 'gls',
        'contextValues' => array(),
    ));
    $result = new XFE_Injection_Domain_InjectionResult();
    foreach ($logisticCallings as $call) {
        $svcId = $call->getServiceId();
        // 跳过未 mock 的旧 service
        if ($svcId === 'service_carrier_resolve_account') {
            // 旧 service 不 mock,跳过(避免调用 Carrier factory)
            continue;
        }
        $svcDef = $reg->getService($svcId);
        $instance = $locator->resolve($svcDef);
        $args = array();
        foreach ($call->getArguments() as $arg) {
            if ($arg['from'] === 'context.carrierCode') {
                $args[] = $injCtx->get('carrierCode');
            } elseif ($arg['from'] === 'context.contextValues') {
                $args[] = $injCtx->get('contextValues');
            }
        }
        $value = call_user_func_array(array($instance, $call->getMethodName()), $args);
        $result->set($call->getId(), $value);
    }

    ok($mock->callCount === 1, "mock getCredentialsByCarrier called once");
    ok($mock->lastCarrierCode === 'gls', "mock received carrierCode = 'gls'");
    ok(is_array($mock->lastContextValues), "mock received contextValues array");

    $first = $result->first();
    ok(is_array($first), "InjectionResult first() returns array (mock returned array)");
    ok(isset($first['endpoint_url']), "credentials array has endpoint_url");
    ok($first['endpoint_url'] === 'https://api.gls.fr', "endpoint_url value matches mock");
    ok($first['username'] === 'gls_user', "username value matches mock");
    ok($first['password'] === 'gls_pass', "password value matches mock");
    ok($first['account_id'] === 42, "account_id value matches mock");
}

// ---------------------------------------------------------
// Test 5: 注入路径返回 null → PodService 主流程 fallback
// ---------------------------------------------------------
echo "\n--- Test 5: injection null -> fallback path ---\n";
XFE_Injection_Model_Registry::resetForTesting();
$merger5 = new XFE_Injection_Model_Config_Merger();
$merger5->mergeFromXml('XFE_Injection', file_get_contents($selfXml));
$merger5->mergeFromXml('XFE_Carrier', file_get_contents($carrierXml));
$merger5->mergeFromXml('XFE_Logistic', file_get_contents($logisticXml));

$reg5 = XFE_Injection_Model_Registry::getInstance();

$mock5 = new MockCredentialsService();
$mock5->returnValue = null; // 模拟"无账号 / Carrier 端返回 null"
$locator5 = new XFE_Injection_Model_ServiceLocator();
$locator5->setOverride('service_carrier_get_credentials', $mock5);

$injCtx5 = new XFE_Injection_Domain_InjectionContext(array('carrierCode' => 'gls', 'contextValues' => array()));
$result5 = new XFE_Injection_Domain_InjectionResult();

foreach ($reg5->getCallingsForHook('hook_logistic_before_request') as $call) {
    if ($call->getServiceId() === 'service_carrier_resolve_account') continue;
    $svcDef = $reg5->getService($call->getServiceId());
    $instance = $locator5->resolve($svcDef);
    $args = array();
    foreach ($call->getArguments() as $arg) {
        if ($arg['from'] === 'context.carrierCode') $args[] = $injCtx5->get('carrierCode');
        elseif ($arg['from'] === 'context.contextValues') $args[] = $injCtx5->get('contextValues');
    }
    $value = call_user_func_array(array($instance, $call->getMethodName()), $args);
    $result5->set($call->getId(), $value);
}

$first5 = $result5->first();
ok($first5 === null, "InjectionResult first() returns null when service returns null (fallback condition)");

// ---------------------------------------------------------
// Test 6: 真实 XML 注册的服务列表
// ---------------------------------------------------------
echo "\n--- Test 6: real XML services / callings count ---\n";
$reg6 = XFE_Injection_Model_Registry::getInstance();
ok($reg6->countServices() === 3, "merged 3 services (carrier: resolve + list + get_credentials)");
ok($reg6->countCallings() === 2, "merged 2 callings (logistic: resolve_account + resolve_credentials)");

echo "\n========================================\n";
echo "Total: {$assertions} assertions, {$failures} failures\n";
echo "========================================\n";

exit($failures > 0 ? 1 : 0);
