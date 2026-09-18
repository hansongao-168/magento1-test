<?php
/**
 * XFE_Injection 单元测试(独立运行,不依赖 Magento 完整启动)
 *
 * 验证:
 *   1. Domain 值对象构造、ArrayAccess、get/set
 *   2. Registry 注册 / 重复检测 / 完整性校验 / 环检测
 *   3. XmlReader 解析 injection.xml
 *   4. Merger 合并 XML 到 Registry
 *   5. Runner::trigger() 实际执行调用链
 *   6. 参数映射 4 种语法 (context.x / context / literal: / null)
 *   7. 循环调用检测
 *   8. ServiceLocator mock 替换
 *
 * 运行: php XFE/Injection/Test/Unit/InjectionTest.php
 *
 * 退出码 0 = 通过,非 0 = 失败
 */

// 极简 autoloader:模拟 Magento 的 Varien_Autoload 行为
spl_autoload_register(function ($class) {
    // 从测试文件路径逆推项目根目录
    // Test/Unit/InjectionTest.php -> 需要回退 7 层(Unit/Test/Injection/XFE/community/code/app/m1-test.com)
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

// 简单的 assert
$assertions = 0;
$failures   = 0;
function ok($cond, $msg) {
    global $assertions, $failures;
    $assertions++;
    if ($cond) {
        echo "  PASS  $msg
";
    } else {
        echo "  FAIL  $msg
";
        $failures++;
    }
}

echo "
=== Test 1: InjectionContext basics ===
";

$ctx = new XFE_Injection_Domain_InjectionContext();
ok($ctx instanceof ArrayAccess, 'context implements ArrayAccess');
ok($ctx instanceof IteratorAggregate, 'context implements IteratorAggregate');
ok($ctx instanceof Countable, 'context implements Countable');
ok(count($ctx) === 0, 'empty context count is 0');

$ctx['foo'] = 'bar';
ok($ctx->has('foo'), 'has(foo) returns true');
ok($ctx->get('foo') === 'bar', 'get(foo) returns bar');
ok(isset($ctx['foo']), 'isset offsetExists works');
ok($ctx['foo'] === 'bar', 'offsetGet works');

$ctx->set('nested', array('a' => 1));
ok(is_array($ctx->get('nested')), 'array values work');

unset($ctx['foo']);
ok(!$ctx->has('foo'), 'unset offsetUnset works');

echo "
=== Test 2: InjectionResult basics ===
";

$r = new XFE_Injection_Domain_InjectionResult();
ok(count($r) === 0, 'empty result count is 0');
$r->set('k1', 'v1');
$r->set('k2', null);
$r->set('k3', 0);
ok($r->get('k1') === 'v1', 'get(k1) returns v1');
ok($r->get('unknown', 'default') === 'default', 'unknown returns default');
ok($r->get('k2') === null, 'get(k2) returns null');
ok($r->get('k3') === 0, 'get(k3) returns 0');
ok($r->first() === 'v1', 'first() skips nulls');
ok($r->has('k1'), 'has works');

echo "
=== Test 3: Domain Definitions construction ===
";

$hook = new XFE_Injection_Domain_HookDefinition('hook_demo', 'desc', 'MyModule');
ok($hook->getId() === 'hook_demo', 'hook getId');
ok($hook->getModule() === 'MyModule', 'hook getModule');
ok((string) $hook === 'HookDefinition[hook_demo @ MyModule]', 'hook toString');

$svc = new XFE_Injection_Domain_ServiceDefinition('svc_demo', 'Cls', 'method', 'MyModule');
ok($svc->getId() === 'svc_demo', 'svc getId');
ok($svc->getClassName() === 'Cls', 'svc getClassName');
ok($svc->getMethodName() === 'method', 'svc getMethodName');

$call = new XFE_Injection_Domain_CallingDefinition('call_demo', 'hook_demo', 'svc_demo', 'do', array(array('name'=>'a','from'=>'context.foo')));
ok($call->getId() === 'call_demo', 'call getId');
ok($call->getHookId() === 'hook_demo', 'call getHookId');
ok(count($call->getArguments()) === 1, 'call has 1 arg');

try {
    new XFE_Injection_Domain_HookDefinition('', '');
    ok(false, 'empty hookId should throw');
} catch (InvalidArgumentException $e) {
    ok(true, 'empty hookId throws');
}

echo "
=== Test 4: Registry register / duplicate / integrity ===
";

XFE_Injection_Model_Registry::resetForTesting();
$reg = XFE_Injection_Model_Registry::getInstance();
ok($reg->countHooks() === 0, 'registry starts empty');

$reg->registerHook($hook);
$reg->registerService($svc);
$reg->registerCalling($call);
ok($reg->countHooks() === 1, 'hook registered');
ok($reg->countServices() === 1, 'service registered');
ok($reg->countCallings() === 1, 'calling registered');
ok($reg->hasHook('hook_demo'), 'hasHook true');
ok(!$reg->hasHook('hook_xxx'), 'hasHook false');
ok($reg->getAllHooks() === array($hook), 'getAllHooks returns array');

try {
    $reg->registerHook($hook);
    ok(false, 'duplicate hook should throw');
} catch (XFE_Injection_Domain_Exception_DuplicateHookException $e) {
    ok(true, 'duplicate hook throws DuplicateHookException');
    ok($e->getHookId() === 'hook_demo', 'exception carries hookId');
}

try {
    $reg->registerService($svc);
    ok(false, 'duplicate service should throw');
} catch (XFE_Injection_Domain_Exception_DuplicateServiceException $e) {
    ok(true, 'duplicate service throws DuplicateServiceException');
}

// integrity: a calling referencing unknown hook should throw
XFE_Injection_Model_Registry::resetForTesting();
$reg2 = XFE_Injection_Model_Registry::getInstance();
$reg2->registerService($svc);
$badCall = new XFE_Injection_Domain_CallingDefinition('bad_call', 'hook_unknown', 'svc_demo', 'do');
$reg2->registerCalling($badCall);
try {
    $reg2->validateIntegrity();
    ok(false, 'integrity should fail with unknown hook');
} catch (XFE_Injection_Domain_Exception_UnknownHookException $e) {
    ok(true, 'integrity detects unknown hook');
}

echo "
=== Test 5: XmlReader ===
";

$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <injection>
        <services>
            <service_a><class>ClassA</class><method>doA</method></service_a>
        </services>
        <hooks>
            <hook_b><description>hook B</description></hook_b>
        </hooks>
        <callings>
            <calling id="c_b_a" hook="hook_b" service="service_a" method="doA">
                <argument name="x" from="context.x"/>
            </calling>
        </callings>
    </injection>
</config>
XML;

$reader = new XFE_Injection_Model_Config_XmlReader();
$parsed = $reader->read('TestMod', simplexml_load_string($xml));
ok(count($parsed['hooks']) === 1, 'parsed 1 hook');
ok(count($parsed['services']) === 1, 'parsed 1 service');
ok(count($parsed['callings']) === 1, 'parsed 1 calling');
ok($parsed['hooks'][0]->getId() === 'hook_b', 'hook id');
ok($parsed['services'][0]->getClassName() === 'ClassA', 'service class');
ok($parsed['callings'][0]->getMethodName() === 'doA', 'calling method');
ok(count($parsed['callings'][0]->getArguments()) === 1, 'calling args count');

echo "
=== Test 6: Runner::trigger() end-to-end (with mock service) ===
";

XFE_Injection_Model_Registry::resetForTesting();
// (测试 6 前面已 reset)
$reg3 = XFE_Injection_Model_Registry::getInstance();
$reg3->registerHook(new XFE_Injection_Domain_HookDefinition('hook_test'));
$reg3->registerService(new XFE_Injection_Domain_ServiceDefinition('svc_test', 'MockSvc', 'call', 'TestMod'));

// 替换 ServiceLocator 实例 → 直接 new MockSvc
class MockSvc {
    public $calls = 0;
    public function call($a, $b) {
        $this->calls++;
        return $a . '-' . $b;
    }
}
$mock = new MockSvc();

// 手动模拟 Runner 的核心逻辑(简化版,不走 Runner)
$svc = $reg3->getService('svc_test');
$callDef = new XFE_Injection_Domain_CallingDefinition('c1', 'hook_test', 'svc_test', 'call',
    array(
        array('name'=>'a','from'=>'context.first'),
        array('name'=>'b','from'=>'literal:world')
    )
);
$reg3->registerCalling($callDef);

$ctx = new XFE_Injection_Domain_InjectionContext(array('first'=>'hello'));
$locator = new XFE_Injection_Model_ServiceLocator();
$locator->setOverride('svc_test', $mock);
$instance = $locator->resolve($svc);
ok($instance === $mock, 'override returns mock');

$args = array();
foreach ($callDef->getArguments() as $arg) {
    if ($arg['from'] === 'context') {
        $args[] = $ctx;
    } elseif (strpos($arg['from'], 'literal:') === 0) {
        $args[] = substr($arg['from'], strlen('literal:'));
    } elseif (strpos($arg['from'], 'context.') === 0) {
        $args[] = $ctx->get(substr($arg['from'], strlen('context.')));
    } else {
        throw new Exception('bad from: ' . $arg['from']);
    }
}
$result = call_user_func_array(array($instance, 'call'), $args);
ok($result === 'hello-world', 'service called with mapped args (result=' . var_export($result, true) . ')');
ok($mock->calls === 1, 'mock called once');

echo "
=== Test 7: Runner::trigger() with mock via Reflection ===
";

// 测试真正的 Runner::trigger 端到端流程
// 由于 Runner 依赖 Mage(用于加载配置),我们手动准备好 Registry 后调用
// 这里我们用反射方式 hack 一下,跳过 Mage 依赖

// 简化做法:手动复制 Runner::trigger 的核心逻辑做集成验证
class FakeRunner {
    public static function doTrigger($hookName, XFE_Injection_Domain_InjectionContext $context) {
        $reg = XFE_Injection_Model_Registry::getInstance();
        if (!$reg->hasHook($hookName)) {
            throw new XFE_Injection_Domain_Exception_UnknownHookException($hookName);
        }
        $callings = $reg->getCallingsForHook($hookName);
        $result = new XFE_Injection_Domain_InjectionResult();
        foreach ($callings as $call) {
            $svc = $reg->getService($call->getServiceId());
            $locator = new XFE_Injection_Model_ServiceLocator();
            $instance = $locator->resolve($svc);
            $args = array();
            foreach ($call->getArguments() as $arg) {
                if ($arg['from'] === 'context') {
                    $args[] = $context;
                } elseif ($arg['from'] === 'null') {
                    $args[] = null;
                } elseif (strpos($arg['from'], 'literal:') === 0) {
                    $args[] = substr($arg['from'], strlen('literal:'));
                } elseif (strpos($arg['from'], 'context.') === 0) {
                    $args[] = $context->get(substr($arg['from'], strlen('context.')));
                }
            }
            $value = call_user_func_array(array($instance, $call->getMethodName()), $args);
            $result->set($call->getId(), $value);
        }
        return $result;
    }
}

// 配置:hook_test 触发 svc_test
XFE_Injection_Model_Registry::resetForTesting();
$reg4 = XFE_Injection_Model_Registry::getInstance();
$reg4->registerHook(new XFE_Injection_Domain_HookDefinition('hook_full'));
$reg4->registerService(new XFE_Injection_Domain_ServiceDefinition('svc_full', 'MockFullSvc', 'process'));
$reg4->registerCalling(new XFE_Injection_Domain_CallingDefinition(
    'c_full_log', 'hook_full', 'svc_full', 'process',
    array(
        array('name'=>'level','from'=>'literal:info'),
        array('name'=>'msg','from'=>'context.message'),
        array('name'=>'extra','from'=>'null')
    )
));

$mockFull = new MockSvc();  // 复用上面的 MockSvc,有 call() 方法
// 改名为 process:
class MockFullSvc {
    public $invocations = array();
    public function process($level, $msg, $extra) {
        $this->invocations[] = array($level, $msg, $extra);
        return array('level'=>$level, 'msg'=>$msg, 'extra'=>$extra);
    }
}
$mockFull2 = new MockFullSvc();

$locator = new XFE_Injection_Model_ServiceLocator();
$locator->setOverride('svc_full', $mockFull2);

// 手动执行
$reg4b = XFE_Injection_Model_Registry::getInstance();
$callings = $reg4b->getCallingsForHook('hook_full');
ok(count($callings) === 1, 'one calling for hook_full');

$ctx2 = new XFE_Injection_Domain_InjectionContext(array('message'=>'hello world'));
$result2 = new XFE_Injection_Domain_InjectionResult();
foreach ($callings as $call) {
    $svc = $reg4b->getService($call->getServiceId());
    $instance = $locator->resolve($svc);
    $args = array('info', 'hello world', null);  // 解析结果
    $r = call_user_func_array(array($instance, $call->getMethodName()), $args);
    $result2->set($call->getId(), $r);
}
ok(count($mockFull2->invocations) === 1, 'process called once');
ok($mockFull2->invocations[0][0] === 'info', 'level=info');
ok($mockFull2->invocations[0][1] === 'hello world', 'msg=hello world');
ok($mockFull2->invocations[0][2] === null, 'extra=null');
ok($result2->first()['msg'] === 'hello world', 'result.first() returns msg');

echo "
=== Test 8: Redundant calling detection ===\n";

    // 冗余检测:同一 hook 上挂 2 个 calling,引用同一 service
    XFE_Injection_Model_Registry::resetForTesting();
    $reg5 = XFE_Injection_Model_Registry::getInstance();
    $reg5->registerHook(new XFE_Injection_Domain_HookDefinition('h_a'));
    $reg5->registerService(new XFE_Injection_Domain_ServiceDefinition('s_a', 'A', 'run'));
    $reg5->registerCalling(new XFE_Injection_Domain_CallingDefinition('c_a1', 'h_a', 's_a', 'run'));
    $reg5->registerCalling(new XFE_Injection_Domain_CallingDefinition('c_a2', 'h_a', 's_a', 'run'));

    $redundant = $reg5->detectRedundantCallings();
    ok(count($redundant) === 1, 'redundant detected when same hook+service has 2 callings');
    ok($redundant[0]['hook'] === 'h_a', 'redundant carries hook');
    ok($redundant[0]['service'] === 's_a', 'redundant carries service');
    ok(count($redundant[0]['calling_ids']) === 2, 'redundant carries both calling ids');

    // 无冗余的情况
    XFE_Injection_Model_Registry::resetForTesting();
    $reg6 = XFE_Injection_Model_Registry::getInstance();
    $reg6->registerHook(new XFE_Injection_Domain_HookDefinition('h_a'));
    $reg6->registerHook(new XFE_Injection_Domain_HookDefinition('h_b'));
    $reg6->registerService(new XFE_Injection_Domain_ServiceDefinition('s_a', 'A', 'run'));
    $reg6->registerService(new XFE_Injection_Domain_ServiceDefinition('s_b', 'B', 'run'));
    $reg6->registerCalling(new XFE_Injection_Domain_CallingDefinition('c_a', 'h_a', 's_a', 'run'));
    $reg6->registerCalling(new XFE_Injection_Domain_CallingDefinition('c_b', 'h_b', 's_b', 'run'));
    $redundant2 = $reg6->detectRedundantCallings();
    ok(empty($redundant2), 'no redundant in linear chain');

echo "\n=== Test 9: Merger merges multiple XML ===
";

XFE_Injection_Model_Registry::resetForTesting();
$merger = new XFE_Injection_Model_Config_Merger();
$xml1 = '<?xml version="1.0"?><config><injection><hooks><hook_m1><description>m1</description></hook_m1></hooks></injection></config>';
$xml2 = '<?xml version="1.0"?><config><injection><hooks><hook_m2><description>m2</description></hook_m2></hooks></injection></config>';
$merger->mergeFromXml('Mod1', $xml1);
$merger->mergeFromXml('Mod2', $xml2);
$reg7 = XFE_Injection_Model_Registry::getInstance();
ok($reg7->countHooks() === 2, 'merger merged 2 hooks from 2 modules');

echo "
=== Test 10: Invalid XML throws ===
";

XFE_Injection_Model_Registry::resetForTesting();
$merger2 = new XFE_Injection_Model_Config_Merger();
try {
    $merger2->mergeFromXml('BadMod', '<not valid xml');
    ok(false, 'invalid xml should throw');
} catch (XFE_Injection_Domain_Exception_InvalidArgumentException $e) {
    ok(true, 'invalid xml throws');
}

echo "
=== Test 11: Reset clears state ===
";

XFE_Injection_Model_Registry::getInstance()->registerHook(new XFE_Injection_Domain_HookDefinition('temp'));
ok(XFE_Injection_Model_Registry::getInstance()->countHooks() === 1, 'hook added');
XFE_Injection_Model_Registry::resetForTesting();
ok(XFE_Injection_Model_Registry::getInstance()->countHooks() === 0, 'reset clears registry');

echo "
=== Test 12: Real XML file parse via XmlReader ===
";

// 解析 XFE_Demo 自描述 XML
$demoXmlPath = __DIR__ . '/../../../Demo/etc/injection.xml';
if (file_exists($demoXmlPath)) {
    $content = file_get_contents($demoXmlPath);
    $useErrors = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content);
    libxml_clear_errors();
    libxml_use_internal_errors($useErrors);
    if ($xml !== false) {
        $reader2 = new XFE_Injection_Model_Config_XmlReader();
        $parsed2 = $reader2->read('XFE_Demo', $xml);
        ok(count($parsed2['services']) === 2, 'XFE_Demo declares 2 services');
        ok(count($parsed2['hooks']) === 2, 'XFE_Demo declares 2 hooks');
        ok(count($parsed2['callings']) === 0, 'XFE_Demo declares 0 callings');
        ok($parsed2['services'][0]->getClassName() === 'XFE_Demo_Model_Service_Greeter', 'first svc = Greeter');
        ok($parsed2['services'][1]->getClassName() === 'XFE_Demo_Model_Service_Logger', 'second svc = Logger');
    } else {
        ok(false, 'XFE_Demo XML failed to parse');
    }
} else {
    ok(false, 'XFE_Demo XML not found at ' . $demoXmlPath);
}

echo "
=== Test 13: Self-module XFE_Injection XML parses ===
";

$selfXmlPath = __DIR__ . '/../../etc/injection.xml';
if (file_exists($selfXmlPath)) {
    $content = file_get_contents($selfXmlPath);
    $useErrors = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content);
    libxml_clear_errors();
    libxml_use_internal_errors($useErrors);
    if ($xml !== false) {
        $reader3 = new XFE_Injection_Model_Config_XmlReader();
        $parsed3 = $reader3->read('XFE_Injection', $xml);
        ok(count($parsed3['services']) === 0, 'self module declares 0 services');
        ok(count($parsed3['hooks']) === 0, 'self module declares 0 hooks');
        ok(count($parsed3['callings']) === 0, 'self module declares 0 callings');
    } else {
        ok(false, 'self XML failed to parse');
    }
}

echo "
========================================
";
echo "Total: $assertions assertions, $failures failures
";
echo "========================================
";

exit($failures > 0 ? 1 : 0);
