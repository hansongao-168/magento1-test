<?php
/**
 * XFE_Injection 集成测试:DocumentUpload 通过 XML 注入接入 XFE_Carrier。
 *
 * 验证:
 *   1. DocumentUpload injection.xml 解析:1 hook + 1 calling + 0 services
 *   2. Carrier service_carrier_list_accounts 存在,引用 CredentialViaInjection
 *   3. 合并 3 个 XML (Injection 自描述 + Carrier + DocumentUpload) 完整性
 *   4. ServiceLocator mock 替换 listAccountsByCarrier → Colissimo adapter 拿到 mock 返回值
 *   5. 参数映射 context.carrierCode + context.contextValues → adapter 收到正确参数
 *   6. 反射验证 listAccountsByCarrier 签名第二参数是 array (零 XFE_Carrier 耦合)
 *
 * 不验证:
 *   - Colissimo adapter 的 Mage factory / DB 访问(那是 DocumentUpload 自身的事)
 *   - accounts_json 旧路径(本轮不删)
 *
 * 运行: php Test/Integration/DocumentUploadTest.php
 * 退出码: 0 = 通过, 非 0 = 失败
 *
 * 关联文档:docs/architecture/documentupload-injection-integration.md §5
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

// Mock Carrier listAccountsByCarrier
class MockListAccountsService {
    public $callCount = 0;
    public $lastCarrierCode = null;
    public $lastContextValues = null;
    public $returnValue = array(101, 102, 103);
    public function listAccountsByCarrier($carrierCode, array $contextValues = array()) {
        $this->callCount++;
        $this->lastCarrierCode = $carrierCode;
        $this->lastContextValues = $contextValues;
        return $this->returnValue;
    }
}

// 真实 XML 字符串
$carrierXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <modules>
        <XFE_Carrier><version>1.0.18</version></XFE_Carrier>
    </modules>
    <injection>
        <services>
            <service_carrier_resolve_account>
                <class>XFE_Carrier_Service_Account_CredentialViaInjection</class>
                <method>resolveAccountId</method>
            </service_carrier_resolve_account>
            <service_carrier_list_accounts>
                <class>XFE_Carrier_Service_Account_CredentialViaInjection</class>
                <method>listAccountsByCarrier</method>
            </service_carrier_list_accounts>
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

$documentUploadXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <modules>
        <XFE_DocumentUpload><version>1.1.0</version></XFE_DocumentUpload>
    </modules>
    <injection>
        <hooks>
            <hook_documentupload_resolve_accounts>
                <description>DocumentUpload 解析账号列表时触发</description>
            </hook_documentupload_resolve_accounts>
        </hooks>
        <callings>
            <calling id="calling_documentupload_list_accounts"
                     hook="hook_documentupload_resolve_accounts"
                     service="service_carrier_list_accounts"
                     method="listAccountsByCarrier">
                <argument name="carrierCode"   from="context.carrierCode"/>
                <argument name="contextValues" from="context.contextValues"/>
            </calling>
        </callings>
    </injection>
</config>
XML;

echo "\n=== Integration Test: DocumentUpload ↔ Carrier via XML injection ===\n\n";

// ---------------------------------------------------------
// 测试 1: DocumentUpload injection.xml 解析
// ---------------------------------------------------------
echo "--- Test 1: DocumentUpload injection.xml parse ---\n";
XFE_Injection_Model_Registry::resetForTesting();
$merger = new XFE_Injection_Model_Config_Merger();
$merger->mergeFromXml('XFE_DocumentUpload', $documentUploadXml);

$reg = XFE_Injection_Model_Registry::getInstance();
ok($reg->countHooks() === 1, "DocumentUpload: 1 hook declared");
ok($reg->countServices() === 0, "DocumentUpload: 0 services declared");
ok($reg->countCallings() === 1, "DocumentUpload: 1 calling declared");

$hooks = $reg->getAllHooks();
$hookIds = array_map(function($h) { return $h->getId(); }, $hooks);
ok(in_array('hook_documentupload_resolve_accounts', $hookIds, true), "hook id matches");

$callings = $reg->getCallingsForHook('hook_documentupload_resolve_accounts');
ok(count($callings) === 1, "calling registered on documentupload hook");
ok($callings[0]->getId() === 'calling_documentupload_list_accounts', "calling id matches");
ok($callings[0]->getServiceId() === 'service_carrier_list_accounts', "calling references carrier list service");
ok($callings[0]->getMethodName() === 'listAccountsByCarrier', "calling method matches");
ok(count($callings[0]->getArguments()) === 2, "calling has 2 args (carrierCode + contextValues)");
ok($callings[0]->getArguments()[0]['name'] === 'carrierCode', "arg[0] name = carrierCode");
ok($callings[0]->getArguments()[0]['from'] === 'context.carrierCode', "arg[0] from = context.carrierCode");
ok($callings[0]->getArguments()[1]['name'] === 'contextValues', "arg[1] name = contextValues");
ok($callings[0]->getArguments()[1]['from'] === 'context.contextValues', "arg[1] from = context.contextValues (ADR 0016 命名)");

// ---------------------------------------------------------
// 测试 2: Carrier service_carrier_list_accounts 存在
// ---------------------------------------------------------
echo "\n--- Test 2: Carrier list-accounts service exists ---\n";
XFE_Injection_Model_Registry::resetForTesting();
$merger2 = new XFE_Injection_Model_Config_Merger();
$merger2->mergeFromXml('XFE_Carrier', $carrierXml);

$reg2 = XFE_Injection_Model_Registry::getInstance();
ok($reg2->hasService('service_carrier_list_accounts'), "service_carrier_list_accounts registered");

$svc = $reg2->getService('service_carrier_list_accounts');
ok($svc->getClassName() === 'XFE_Carrier_Service_Account_CredentialViaInjection', "service class = CredentialViaInjection");
ok($svc->getMethodName() === 'listAccountsByCarrier', "service method = listAccountsByCarrier");

// ---------------------------------------------------------
// 测试 3: 合并 3 个 XML 完整性
// ---------------------------------------------------------
echo "\n--- Test 3: 3-XML merge integrity ---\n";
$selfXmlPath = __DIR__ . '/../../etc/injection.xml';
ok(file_exists($selfXmlPath), "XFE_Injection self injection.xml exists at: " . $selfXmlPath);

XFE_Injection_Model_Registry::resetForTesting();
$merger3 = new XFE_Injection_Model_Config_Merger();
$merger3->mergeFromXml('XFE_Injection', file_get_contents($selfXmlPath));
$merger3->mergeFromXml('XFE_Carrier', $carrierXml);
$merger3->mergeFromXml('XFE_DocumentUpload', $documentUploadXml);

$reg3 = XFE_Injection_Model_Registry::getInstance();
$integrityErrors = $reg3->validateIntegrity();
ok(empty($integrityErrors), "integrity check passes (no errors)");

// 期望: Injection 自描述空骨架 (0 hooks/0 services/0 callings)
//      + Carrier (1 hook/2 services/0 callings)
//      + DocumentUpload (1 hook/0 services/1 calling)
//      = 合计 2 hooks / 2 services / 1 calling
ok($reg3->countHooks() === 2, "merged 2 hooks (carrier + documentupload)");
ok($reg3->countServices() === 2, "merged 2 services (carrier: resolve + list)");
ok($reg3->countCallings() === 1, "merged 1 calling (documentupload)");

ok($reg3->hasHook('hook_documentupload_resolve_accounts'), "documentupload hook present");
ok($reg3->hasHook('hook_carrier_account_resolved'), "carrier hook present");
ok($reg3->hasService('service_carrier_list_accounts'), "carrier list service present");
ok($reg3->hasService('service_carrier_resolve_account'), "carrier resolve service present");

// ---------------------------------------------------------
// 测试 4: ServiceLocator mock 替换 → trigger → adapter 拿到 mock 返回值
// ---------------------------------------------------------
echo "\n--- Test 4: ServiceLocator mock override end-to-end ---\n";
XFE_Injection_Model_Registry::resetForTesting();
$merger4 = new XFE_Injection_Model_Config_Merger();
$merger4->mergeFromXml('XFE_Carrier', $carrierXml);
$merger4->mergeFromXml('XFE_DocumentUpload', $documentUploadXml);

$reg4 = XFE_Injection_Model_Registry::getInstance();

$mock = new MockListAccountsService();
$locator = new XFE_Injection_Model_ServiceLocator();
$locator->setOverride('service_carrier_list_accounts', $mock);

// 复刻 Colissimo::getAccountsViaInjection 内部的核心调用链
$injCtx = new XFE_Injection_Domain_InjectionContext(array(
    'carrierCode'   => 'colissimo',
    'contextValues' => array('_test' => 'sentinel'),
));

$callings4 = $reg4->getCallingsForHook('hook_documentupload_resolve_accounts');
$result = new XFE_Injection_Domain_InjectionResult();
foreach ($callings4 as $call) {
    $svcId = $call->getServiceId();
    $svc = $reg4->getService($svcId);
    $instance = $locator->resolve($svc);
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

ok($mock->callCount === 1, "mock listAccountsByCarrier called once");
ok($mock->lastCarrierCode === 'colissimo', "mock received carrierCode = 'colissimo'");
ok(is_array($mock->lastContextValues) && isset($mock->lastContextValues['_test']), "mock received contextValues array");
ok($mock->lastContextValues['_test'] === 'sentinel', "mock contextValues contains sentinel value");

$first = $result->first();
ok(is_array($first), "InjectionResult first() returns array");
ok(count($first) === 3, "array has 3 elements (mock returned [101,102,103])");
ok($first[0] === 101 && $first[1] === 102 && $first[2] === 103, "array values match mock return");

// ---------------------------------------------------------
// 测试 5: 参数映射 context.carrierCode + context.contextValues
// ---------------------------------------------------------
echo "\n--- Test 5: argument mapping ---\n";
$mock5 = new MockListAccountsService();
$locator5 = new XFE_Injection_Model_ServiceLocator();
$locator5->setOverride('service_carrier_list_accounts', $mock5);

$ctx5 = new XFE_Injection_Domain_InjectionContext(array(
    'carrierCode'   => 'dhl',
    'contextValues' => array('country_code' => 'FR', 'weight' => 1.5),
));

$callings5 = $reg4->getCallingsForHook('hook_documentupload_resolve_accounts');
$call = $callings5[0];
$svc = $reg4->getService($call->getServiceId());
$instance = $locator5->resolve($svc);

// 完整参数解析链 (覆盖 literal / null / context 三种语法也走一遍)
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

ok($mock5->callCount === 1, "second mock call counted");
ok($mock5->lastCarrierCode === 'dhl', "context.carrierCode = 'dhl' mapped");
ok(is_array($mock5->lastContextValues), "context.contextValues mapped to array");
ok($mock5->lastContextValues['country_code'] === 'FR', "contextValues.country_code = 'FR'");
ok((float)$mock5->lastContextValues['weight'] === 1.5, "contextValues.weight = 1.5");

// ---------------------------------------------------------
// 测试 6: 反射验证 listAccountsByCarrier 签名零耦合
// ---------------------------------------------------------
echo "\n--- Test 6: reflection check - second param is array ---\n";
$rfl = new ReflectionMethod(
    'XFE_Carrier_Service_Account_CredentialViaInjection',
    'listAccountsByCarrier'
);
$params = $rfl->getParameters();
ok(count($params) >= 2, "listAccountsByCarrier has >= 2 params, got: " . count($params));
if (count($params) >= 2) {
    $type = $params[1]->getType();
    $typeStr = $type ? (string)$type : 'null';
    ok($typeStr === 'array', "param[1] type = array (zero XFE_Carrier coupling), got: " . $typeStr);
}
ok($params[0]->getName() === 'carrierCode', "param[0] name = carrierCode");
if (isset($params[2])) {
    $type2 = $params[2]->getType();
    $type2Str = $type2 ? (string)$type2 : 'null';
    ok($type2Str === 'array', "param[2] type = array (signature consistency), got: " . $type2Str);
}

// ---------------------------------------------------------
// 测试 7: 真实 XML 文件解析
// ---------------------------------------------------------
echo "\n--- Test 7: real XML files parse ---\n";
$carrierRealPath = __DIR__ . '/../../../Carrier/etc/injection.xml';
$documentUploadRealPath = __DIR__ . '/../../../DocumentUpload/etc/injection.xml';

ok(file_exists($carrierRealPath), "Carrier etc/injection.xml exists");
ok(file_exists($documentUploadRealPath), "DocumentUpload etc/injection.xml exists");

if (file_exists($carrierRealPath) && file_exists($documentUploadRealPath)) {
    XFE_Injection_Model_Registry::resetForTesting();
    $merger7 = new XFE_Injection_Model_Config_Merger();
    $merger7->mergeFromXml('XFE_Carrier', file_get_contents($carrierRealPath));
    $merger7->mergeFromXml('XFE_DocumentUpload', file_get_contents($documentUploadRealPath));
    
    $reg7 = XFE_Injection_Model_Registry::getInstance();
    $reg7->validateIntegrity();
    
    ok($reg7->hasService('service_carrier_list_accounts'), "real Carrier declares service_carrier_list_accounts");
    $realSvc = $reg7->getService('service_carrier_list_accounts');
    ok($realSvc->getClassName() === 'XFE_Carrier_Service_Account_CredentialViaInjection', "real service class = adapter");
    ok($realSvc->getMethodName() === 'listAccountsByCarrier', "real service method = listAccountsByCarrier");
    
    ok($reg7->hasHook('hook_documentupload_resolve_accounts'), "real DocumentUpload declares hook");
    $realCallings = $reg7->getCallingsForHook('hook_documentupload_resolve_accounts');
    ok(count($realCallings) === 1, "real DocumentUpload has 1 calling");
    ok($realCallings[0]->getServiceId() === 'service_carrier_list_accounts', "real calling references list service");
    ok($realCallings[0]->getArguments()[1]['from'] === 'context.contextValues', "real calling arg[1] from = context.contextValues");
}

echo "\n========================================\n";
echo "Total: {$assertions} assertions, {$failures} failures\n";
echo "========================================\n";

exit($failures > 0 ? 1 : 0);
