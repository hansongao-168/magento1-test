<?php
/**
 * CustomField 单元测试(纯 L1 Domain,不依赖 Magento)
 *
 * 覆盖:
 *   - key / label / type / options 的不变量(非法 → InvalidArgumentException)
 *   - 类型强转(text→string, number→int/float, boolean→bool, select→options 中)
 *   - withValue() 不可变副本
 *   - toArray() 形态
 *
 * 运行:php app/code/community/XFE/Carrier/Test/Domain/CustomFieldTest.php
 */

spl_autoload_register(function ($class) {
    if (strpos($class, 'XFE_Carrier_Domain_') !== 0) {
        return;
    }
    $parts = explode('_', $class);
    array_shift($parts); // XFE
    array_shift($parts); // Carrier
    array_shift($parts); // Domain  ← 需要去掉这一段
    $path = implode('/', $parts) . '.php';
    $candidate = __DIR__ . '/../../Domain/' . $path;
    if (file_exists($candidate)) {
        require_once $candidate;
    }
});

$failed = 0;
function assertEq($expected, $actual, $label) {
    global $failed;
    $ok = $expected === $actual;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label
       . ' - expected ' . var_export($expected, true)
       . ', got ' . var_export($actual, true) . PHP_EOL;
    if (!$ok) $failed++;
}
function assertThrows($class, callable $fn, $label) {
    global $failed;
    try { $fn(); $failed++; echo "[FAIL] $label - no exception\n"; }
    catch (Throwable $e) {
        if ($e instanceof $class) {
            echo "[PASS] $label\n";
        } else {
            $failed++;
            echo "[FAIL] $label - got " . get_class($e) . ': ' . $e->getMessage() . "\n";
        }
    }
}

// ---------- 1. happy path --------------------------------------------------
$f = new XFE_Carrier_Domain_CustomField('warehouse_code', '仓库代码', 'text', 'WH-001');
assertEq('warehouse_code', $f->getKey(), 'key getter');
assertEq('仓库代码', $f->getLabel(), 'label getter');
assertEq('text', $f->getType(), 'type getter');
assertEq('WH-001', $f->getValue(), 'value getter');
assertEq(null, $f->getOptions(), 'text no options');

// ---------- 2. key 非法 ---------------------------------------------------
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomField('BadKey', 'name', 'text');
}, 'key 含大写抛异常');
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomField('', 'name', 'text');
}, 'key 空抛异常');
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomField(str_repeat('a', 65), 'name', 'text');
}, 'key 超长抛异常');
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomField('with-dash', 'name', 'text');
}, 'key 含短横线抛异常');

// ---------- 3. label 非法 -------------------------------------------------
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomField('ok_key', '', 'text');
}, 'label 空抛异常');
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomField('ok_key', str_repeat('字', 65), 'text');
}, 'label 超长抛异常');

// ---------- 4. type 非法 --------------------------------------------------
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomField('ok_key', 'name', 'date');
}, '非法 type 抛异常');

// ---------- 5. select 必须有 options ---------------------------------------
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomField('ok_key', 'name', 'select', 'x', array());
}, 'select 空 options 抛异常');
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomField('ok_key', 'name', 'select', 'x');
}, 'select 无 options 抛异常');

// ---------- 6. value 类型强转 ---------------------------------------------
$numInt = new XFE_Carrier_Domain_CustomField('n', 'n', 'number', '42');
assertEq(42, $numInt->getValue(), 'number 整数字符串转 int');

$numFloat = new XFE_Carrier_Domain_CustomField('n', 'n', 'number', '3.14');
assertEq(3.14, $numFloat->getValue(), 'number 小数字符串转 float');

$numNull = new XFE_Carrier_Domain_CustomField('n', 'n', 'number', '');
assertEq(null, $numNull->getValue(), 'number 空值转 null');

$boolTrue = new XFE_Carrier_Domain_CustomField('b', 'b', 'boolean', '1');
assertEq(true, $boolTrue->getValue(), 'boolean "1" 转 true');
$boolFalse = new XFE_Carrier_Domain_CustomField('b', 'b', 'boolean', '0');
assertEq(false, $boolFalse->getValue(), 'boolean "0" 转 false');
$boolEmpty = new XFE_Carrier_Domain_CustomField('b', 'b', 'boolean', '');
assertEq(false, $boolEmpty->getValue(), 'boolean 空字符串转 false');

$sel = new XFE_Carrier_Domain_CustomField(
    's', 's', 'select', 'express', array('standard', 'express', 'economy')
);
assertEq('express', $sel->getValue(), 'select 合法的 value');
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomField(
        's', 's', 'select', 'overnight', array('standard', 'express', 'economy')
    );
}, 'select value 不在 options 中抛异常');

// ---------- 7. withValue() 不可变副本 -------------------------------------
$orig = new XFE_Carrier_Domain_CustomField('k', 'l', 'text', 'old');
$copy = $orig->withValue('new');
assertEq('old', $orig->getValue(), '原对象 value 不变');
assertEq('new', $copy->getValue(), '副本是新 value');
assertEq($orig->getKey(), $copy->getKey(), 'key 保留');
assertEq($orig->getType(), $copy->getType(), 'type 保留');

// ---------- 8. toArray() --------------------------------------------------
$arr = $sel->toArray();
assertEq('s', $arr['label'], 'toArray label');
assertEq('select', $arr['type'], 'toArray type');
assertEq('express', $arr['value'], 'toArray value');
assertEq(true, isset($arr['options']), 'toArray 包含 options');
assertEq(array('standard', 'express', 'economy'), $arr['options'], 'toArray options 数组');

$arrText = (new XFE_Carrier_Domain_CustomField('k', 'l', 'text', 'v'))->toArray();
assertEq(false, isset($arrText['options']), 'text toArray 不含 options');

// ---------- 9. multiselect: options 可空 ----------------------------------
// 场景 A:options 为 null → 自由标签输入;value 接受 array / 逗号串 / null
$msFree = new XFE_Carrier_Domain_CustomField(
    'areas', '支持区域', 'multiselect', array('华东', '华南', '华东')
);
assertEq(array('华东', '华南'), $msFree->getValue(), 'multiselect 去重 + 保留顺序');
assertEq(array(), $msFree->getOptions(), 'multiselect options=null 规范为空数组');

$msFreeFromStr = new XFE_Carrier_Domain_CustomField(
    'areas', '支持区域', 'multiselect', '华东, 华南 , 华北'
);
assertEq(array('华东', '华南', '华北'), $msFreeFromStr->getValue(), 'multiselect 逗号字符串拆 + trim');

$msFreeEmpty = new XFE_Carrier_Domain_CustomField(
    'areas', '支持区域', 'multiselect', null
);
assertEq(array(), $msFreeEmpty->getValue(), 'multiselect null value 规范为空数组');

$msFreeEmptyStr = new XFE_Carrier_Domain_CustomField(
    'areas', '支持区域', 'multiselect', ''
);
assertEq(array(), $msFreeEmptyStr->getValue(), 'multiselect "" value 规范为空数组');

// 场景 B:options 非空 → 强制从列表选;value 必须在 options 内
$msFixed = new XFE_Carrier_Domain_CustomField(
    'levels', '服务等级', 'multiselect',
    array('express'), array('standard', 'express', 'economy')
);
assertEq(array('express'), $msFixed->getValue(), 'multiselect 固定 options 接受合法值');
assertEq(array('standard', 'express', 'economy'), $msFixed->getOptions(), 'multiselect 固定 options 保留');

// 非法:固定 options 下,value 含 options 之外的元素 → 抛异常
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomField(
        'levels', '服务等级', 'multiselect',
        array('overnight'), array('standard', 'express', 'economy')
    );
}, 'multiselect 固定 options 下 value 含 options 外元素抛异常');

// 自由输入场景:options=null 时,任何字符串值都合法(不抛异常)
$msFreeOpen = new XFE_Carrier_Domain_CustomField(
    'tags', '自由标签', 'multiselect',
    array('任意-字符串', 'with 空格', '中英 mix 123')
);
assertEq(array('任意-字符串', 'with 空格', '中英 mix 123'), $msFreeOpen->getValue(),
    'multiselect 自由输入模式接受任意字符串');

// toArray:multiselect 节点应包含 options
$msArr = $msFixed->toArray();
assertEq('multiselect', $msArr['type'], 'multiselect toArray type');
assertEq(array('express'), $msArr['value'], 'multiselect toArray value');
assertEq(true, isset($msArr['options']), 'multiselect toArray 包含 options');
assertEq(array('standard', 'express', 'economy'), $msArr['options'],
    'multiselect toArray options');

$msFreeArr = $msFree->toArray();
assertEq(array('华东', '华南'), $msFreeArr['value'], 'multiselect 自由模式 toArray value');
assertEq(array(), $msFreeArr['options'], 'multiselect 自由模式 toArray options 空数组');

echo PHP_EOL . ($failed ? "FAILED: {$failed} assertion(s)" : 'ALL PASS') . PHP_EOL;
exit($failed ? 1 : 0);
