<?php
/**
 * CustomAttributeApplierAbstract 严格模式单元测试
 *
 * Applier 的 _buildFromPost($post, $defs, $oldColl) 是 protected 且
 * 完全自包含(只依赖 Domain + Mage::throwException)。通过子类 probe
 * 暴露后,可独立测试。
 *
 * Mage::throwException 在 Magento 1 中是抛 Mage_Core_Exception。
 * 这里定义极简桩类:
 *   - class Mage { public static function throwException($msg) { throw new Mage_Core_Exception($msg); } }
 *   - class Mage_Core_Exception extends Exception {}
 *
 * 覆盖:
 *   - 兼容模式(defs 为空):接受任意 key / label / type / options
 *   - 严格模式(defs 非空):未登记 key 抛异常
 *   - 严格模式:必填字段空 value 抛异常
 *   - 严格模式:select 非法 value 抛异常
 *   - 严格模式:multiselect 固定模式非法 value 抛异常
 *   - 严格模式:label / type / options 来自 def,POST 不采纳
 *   - 严格模式:value 在 POST 决定(从 spec['value'] 取)
 *   - 严格模式:multiselect 接受 array / 逗号字符串
 *   - 兼容模式:非法 spec 静默跳过
 *   - 缺/空 custom_fields 段 → 空集合
 *
 * 运行:php app/code/community/XFE/Carrier/Test/Service/CustomAttributeApplierStrictTest.php
 */

// ---- Mage 桩 ----------------------------------------------------------
if (!class_exists('Mage', false)) {
    class Mage
    {
        public static function throwException($msg)
        {
            throw new Mage_Core_Exception((string) $msg);
        }
        public static function helper($name) { return new Mage_Core_Helper(); }
    }
}
if (!class_exists('Mage_Core_Exception', false)) {
    class Mage_Core_Exception extends Exception {}
}
if (!class_exists('Mage_Core_Helper', false)) {
    class Mage_Core_Helper
    {
        public function __($s) { return (string) $s; }
    }
}

spl_autoload_register(function ($class) {
    $prefixes = array('XFE_Carrier_Domain_', 'XFE_Carrier_Model_Service_Account_', 'XFE_Carrier_Model_Service_CustomAttributeApplierAbstract');
    $matched = false;
    foreach ($prefixes as $p) {
        if (strpos($class, $p) === 0) { $matched = true; break; }
    }
    if (!$matched) return;

    if (strpos($class, 'XFE_Carrier_Domain_') === 0) {
        $parts = explode('_', $class);
        array_shift($parts); array_shift($parts); array_shift($parts);
        $path = implode('/', $parts) . '.php';
        $candidate = __DIR__ . '/../../Domain/' . $path;
    } else {
        $parts = explode('_', $class);
        array_shift($parts); array_shift($parts); array_shift($parts); array_shift($parts);
        $path = implode('/', $parts) . '.php';
        $candidate = __DIR__ . '/../../Model/Service/' . $path;
    }
    if (file_exists($candidate)) {
        require_once $candidate;
    }
});

/** 探针:把 protected _buildFromPost 暴露为 public build() */
class ApplierProbe extends XFE_Carrier_Model_Service_CustomAttributeApplierAbstract
{
    protected function _getModelAlias()   { return 'xfe_carrier/carrier_account'; }
    protected function _getEntityIdColumn() { return 'account_id'; }
    protected function _getEntityType()    { return 'account'; }
    public function build(array $post, $defs, $oldColl = null)
    {
        return $this->_buildFromPost($post, $defs, $oldColl ?: new XFE_Carrier_Domain_CustomFieldCollection());
    }
}

$failed = 0;
function assertEq($expected, $actual, $label) {
    global $failed;
    $ok = $expected === $actual;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label
       . ' - expected ' . var_export($expected, true)
       . ', got ' . var_export($actual, true) . PHP_EOL;
    if (!$ok) $failed++;
}
function assertThrowsMage($label, callable $fn) {
    global $failed;
    try { $fn(); $failed++; echo "[FAIL] $label - no exception\n"; }
    catch (Mage_Core_Exception $e) {
        echo "[PASS] $label - " . $e->getMessage() . "\n";
    } catch (Throwable $e) {
        $failed++;
        echo "[FAIL] $label - got " . get_class($e) . ': ' . $e->getMessage() . "\n";
    }
}

// 构造 defs helper
function mkDef($key, $opts = array())
{
    $label = isset($opts['label'])    ? $opts['label']    : strtoupper($key);
    $type  = isset($opts['type'])     ? $opts['type']     : 'text';
    $opts2 = isset($opts['options'])  ? $opts['options']  : null;
    $req   = isset($opts['required']) ? $opts['required'] : false;
    $act   = isset($opts['active'])   ? $opts['active']   : true;
    return new XFE_Carrier_Domain_CustomAttribute(
        null, 'account', $key, $label, $type, $opts2, null, $req, $act, 0, null
    );
}
function mkDefs(array $defs)
{
    $c = new XFE_Carrier_Domain_CustomAttributeCollection();
    foreach ($defs as $d) { $c->add($d); }
    return $c;
}

$probe = new ApplierProbe();

// ============================================================
// 兼容模式(defs 为空)
// ============================================================
$emptyDefs = mkDefs(array());

// 任意 key 接受 / POST 自带 label/type
$post = array(
    'custom_fields' => array(
        'free_key' => array('label' => '自由', 'type' => 'text', 'value' => 'hi'),
    ),
);
$coll = $probe->build($post, $emptyDefs);
assertEq(1, $coll->count(), '兼容模式:接受任意 key');
assertEq('自由', $coll->get('free_key')->getLabel(),
    '兼容模式:label 来自 POST');
assertEq('text', $coll->get('free_key')->getType(),
    '兼容模式:type 来自 POST');
assertEq('hi', $coll->get('free_key')->getValue(),
    '兼容模式:value 来自 POST');

// 非法 spec 的 type 在兼容模式被忽略(强制 text)→ spec 仍会进入集合
$post = array(
    'custom_fields' => array(
        'bad'  => array('label' => 'B', 'type' => 'date', 'value' => 'x'),
        'good' => array('label' => 'G', 'type' => 'text', 'value' => 'v'),
    ),
);
$coll = $probe->build($post, $emptyDefs);
assertEq(2, $coll->count(), '兼容模式:type 被忽略,2 条都进集合');
assertEq('text', $coll->get('bad')->getType(),
    '兼容模式:bad 的 type=date 被强制为 text');
assertEq('x', $coll->get('bad')->getValue(),
    '兼容模式:bad value 保留');
assertEq(true, $coll->has('good'), '兼容模式:good 保留');

// 真正触发 Domain 非法(spec key 不合法 - 用 123 这种)→ 跳过
$post = array(
    'custom_fields' => array(
        'good'      => array('label' => 'G', 'type' => 'text', 'value' => 'v'),
        123         => array('label' => 'bad-spec', 'type' => 'text', 'value' => 'x'),
        ''          => array('label' => 'empty-key', 'type' => 'text', 'value' => 'y'),
    ),
);
$coll = $probe->build($post, $emptyDefs);
assertEq(1, $coll->count(), '兼容模式:整行非法跳过,只留 1 条');
assertEq(true, $coll->has('good'), '兼容模式:good 保留');

// 缺/空段
assertEq(0, $probe->build(array(), $emptyDefs)->count(), '兼容模式:空 POST');
assertEq(0, $probe->build(array('custom_fields' => null), $emptyDefs)->count(),
    '兼容模式:custom_fields=null');
assertEq(0, $probe->build(array('custom_fields' => 'not_array'), $emptyDefs)->count(),
    '兼容模式:custom_fields 非数组');

// ============================================================
// 严格模式(defs 非空)
// ============================================================
$defs = mkDefs(array(
    mkDef('wh', array('label' => '仓库', 'type' => 'text')),
    mkDef('svc', array('label' => '服务', 'type' => 'select',
        'options' => array('standard', 'express'), 'required' => true)),
    mkDef('areas', array('label' => '区域', 'type' => 'multiselect')),
    mkDef('levels', array('label' => '等级', 'type' => 'multiselect',
        'options' => array('gold', 'silver'))),
));

// 1. 全部合法 → POST 决定 value,def 决定 label/type/options
$post = array(
    'custom_fields' => array(
        'wh'    => array('label' => 'IGNORE_ME', 'type' => 'date',
                         'value' => 'WH-001'),
        'svc'   => array('label' => 'IGNORE_ME', 'type' => 'text',
                         'value' => 'express'),
    ),
);
$coll = $probe->build($post, $defs);
assertEq(2, $coll->count(), '严格模式:2 个登记 key 接受');
assertEq('仓库', $coll->get('wh')->getLabel(),
    '严格模式:wh.label 来自 def 覆盖 POST');
assertEq('text', $coll->get('wh')->getType(),
    '严格模式:wh.type 来自 def 覆盖 POST');
assertEq('WH-001', $coll->get('wh')->getValue(),
    '严格模式:wh.value 来自 POST');
assertEq('服务', $coll->get('svc')->getLabel(),
    '严格模式:svc.label 来自 def');
assertEq('select', $coll->get('svc')->getType(),
    '严格模式:svc.type 来自 def');
assertEq(array('standard', 'express'), $coll->get('svc')->getOptions(),
    '严格模式:svc.options 来自 def');
assertEq('express', $coll->get('svc')->getValue(),
    '严格模式:svc.value 来自 POST');

// 2. 未登记 key → 抛
assertThrowsMage('严格模式:未登记 key 抛异常', function () use ($probe, $defs) {
    $probe->build(array(
        'custom_fields' => array(
            'unknown_key' => array('label' => 'U', 'type' => 'text', 'value' => 'x'),
        ),
    ), $defs);
});

// 3. 必填字段空 value → 抛(svc 是必填)
assertThrowsMage('严格模式:必填空字符串抛异常', function () use ($probe, $defs) {
    $probe->build(array(
        'custom_fields' => array(
            'svc' => array('label' => 'svc', 'type' => 'select', 'value' => ''),
        ),
    ), $defs);
});

assertThrowsMage('严格模式:必填 null 抛异常', function () use ($probe, $defs) {
    $probe->build(array(
        'custom_fields' => array(
            'svc' => array('label' => 'svc', 'type' => 'select'),  // value 缺省
        ),
    ), $defs);
});

assertThrowsMage('严格模式:必填空数组抛异常', function () use ($probe, $defs) {
    $probe->build(array(
        'custom_fields' => array(
            'svc' => array('label' => 'svc', 'type' => 'select', 'value' => array()),
        ),
    ), $defs);
});

// 必填字段有值 → 通过
$coll = $probe->build(array(
    'custom_fields' => array('svc' => array('value' => 'express')),
), $defs);
assertEq(1, $coll->count(), '严格模式:必填有值通过');

// 4. select 非法 value → 抛
assertThrowsMage('严格模式:select 非法 value 抛异常', function () use ($probe, $defs) {
    $probe->build(array(
        'custom_fields' => array(
            'svc' => array('value' => 'overnight'),   // 不在 options 中
        ),
    ), $defs);
});

// 5. multiselect 自由模式(areas options=[])→ 任意字符串 array
$coll = $probe->build(array(
    'custom_fields' => array(
        'areas' => array('value' => '华东, 华南 , 华东'),
    ),
), $defs);
assertEq(1, $coll->count(), '严格模式:multiselect 自由模式');
assertEq(array('华东', '华南'), $coll->get('areas')->getValue(),
    '严格模式:multiselect 自由模式 value 拆 + 去重');
assertEq(array(), $coll->get('areas')->getOptions(),
    '严格模式:multiselect 自由模式 options=[]');

// 6. multiselect 固定模式(levels options=gold/silver)→ 非法值抛
assertThrowsMage('严格模式:multiselect 固定模式非法 value 抛异常',
    function () use ($probe, $defs) {
        $probe->build(array(
            'custom_fields' => array(
                'levels' => array('value' => 'gold,platinum'),
            ),
        ), $defs);
    });

// 合法
$coll = $probe->build(array(
    'custom_fields' => array(
        'levels' => array('value' => array('silver', 'gold')),
    ),
), $defs);
assertEq(array('silver', 'gold'), $coll->get('levels')->getValue(),
    '严格模式:multiselect 固定模式 合法 array value');

// 7. 缺/空段
assertEq(0, $probe->build(array(), $defs)->count(), '严格模式:空 POST');
assertEq(0, $probe->build(array('custom_fields' => null), $defs)->count(),
    '严格模式:custom_fields=null');

// 8. spec 整行不是 array / key 是非字符串 / key 空 → 跳过(不抛)
$post = array(
    'custom_fields' => array(
        'wh'  => array('value' => 'WH-1'),
        123   => 'not_an_array',          // 整行不是 array
        ''    => array('value' => 'x'),   // key 空
    ),
);
$coll = $probe->build($post, $defs);
assertEq(1, $coll->count(), '严格模式:非法 spec 静默跳过,仅留 1 条');
assertEq(true, $coll->has('wh'), '严格模式:合法 spec 保留');

// 9. POST 中 value 是 array(multiselect 直接传 array)
$coll = $probe->build(array(
    'custom_fields' => array(
        'areas' => array('value' => array('华东', '华南')),
    ),
), $defs);
assertEq(array('华东', '华南'), $coll->get('areas')->getValue(),
    '严格模式:multiselect 接受 array value');

echo PHP_EOL . ($failed ? "FAILED: {$failed} assertion(s)" : 'ALL PASS') . PHP_EOL;
exit($failed ? 1 : 0);
