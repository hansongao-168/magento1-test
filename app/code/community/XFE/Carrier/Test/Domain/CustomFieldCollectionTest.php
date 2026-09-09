<?php
/**
 * CustomFieldCollection 单元测试(L1 Domain)
 *
 * 覆盖:add/has/get/remove、key 唯一性、toArray/fromArray 往返。
 *
 * 运行:php app/code/community/XFE/Carrier/Test/Domain/CustomFieldCollectionTest.php
 */

spl_autoload_register(function ($class) {
    if (strpos($class, 'XFE_Carrier_Domain_') !== 0) {
        return;
    }
    $parts = explode('_', $class);
    array_shift($parts); array_shift($parts); array_shift($parts); // XFE_Carrier_Domain
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
        if ($e instanceof $class) { echo "[PASS] $label\n"; }
        else { $failed++; echo "[FAIL] $label - got " . get_class($e) . "\n"; }
    }
}

// 1. add / has / get
$coll = new XFE_Carrier_Domain_CustomFieldCollection();
assertEq(0, $coll->count(), '初始 count=0');
$f1 = new XFE_Carrier_Domain_CustomField('a', 'A', 'text', '1');
$coll->add($f1);
assertEq(1, $coll->count(), 'add 后 count=1');
assertEq(true, $coll->has('a'), 'has true');
assertEq(false, $coll->has('b'), 'has false');
assertEq('A', $coll->get('a')->getLabel(), 'get 返回原对象');

// 2. key 唯一
assertThrows('DomainException', function () use ($coll) {
    $coll->add(new XFE_Carrier_Domain_CustomField('a', 'A2', 'text', '2'));
}, '重复 key 抛 DomainException');

// 3. remove
$coll->remove('a');
assertEq(0, $coll->count(), 'remove 后 count=0');
assertEq(false, $coll->has('a'), 'remove 后 has=false');

// 4. IteratorAggregate
$coll = new XFE_Carrier_Domain_CustomFieldCollection();
$coll->add(new XFE_Carrier_Domain_CustomField('a', 'A', 'text', '1'));
$coll->add(new XFE_Carrier_Domain_CustomField('b', 'B', 'number', 2));
$coll->add(new XFE_Carrier_Domain_CustomField('c', 'C', 'boolean', true));
$keys = array();
foreach ($coll as $f) { $keys[] = $f->getKey(); }
assertEq(array('a', 'b', 'c'), $keys, 'foreach 顺序');

// 5. toArray() 与 fromArray() 往返
$arr = $coll->toArray();
assertEq(true, isset($arr['a']['label']), 'toArray 包含 a');
assertEq(2, $arr['b']['value'], 'toArray 包含 b 的值');

$restored = XFE_Carrier_Domain_CustomFieldCollection::fromArray($arr);
assertEq(3, $restored->count(), 'fromArray 后 count=3');
assertEq(true, $restored->has('a'), 'restored has a');
assertEq(true, $restored->get('c')->getValue(), 'restored c 是 bool true');

// 6. fromArray() 跳过非法 spec
$bad = XFE_Carrier_Domain_CustomFieldCollection::fromArray(array(
    'good' => array('label' => 'G', 'type' => 'text', 'value' => 'x'),
    'bad_type' => array('label' => 'B', 'type' => 'date', 'value' => 'x'),
    'bad_key'  => 'not an array',  // 整个 spec 不是数组
    'list_form' => array('a', 'b'),  // 整个 spec 不是 assoc
));
assertEq(1, $bad->count(), 'fromArray 仅保留合法 spec');

// 7. 空集合
$empty = XFE_Carrier_Domain_CustomFieldCollection::fromArray(array());
assertEq(0, $empty->count(), '空数组 fromArray 得空集合');

// 8. multiselect: fromArray 接受 options
$msArr = array(
    'areas_free' => array(
        'label' => '支持区域',
        'type'  => 'multiselect',
        'value' => array('华东', '华南'),
        'options' => array(),
    ),
    'levels_fixed' => array(
        'label' => '服务等级',
        'type'  => 'multiselect',
        'value' => array('express'),
        'options' => array('standard', 'express', 'economy'),
    ),
);
$msColl = XFE_Carrier_Domain_CustomFieldCollection::fromArray($msArr);
assertEq(2, $msColl->count(), 'multiselect fromArray 两个字段');
assertEq(array('华东', '华南'),
    $msColl->get('areas_free')->getValue(),
    'multiselect 自由模式 fromArray value');
assertEq(array(),
    $msColl->get('areas_free')->getOptions(),
    'multiselect 自由模式 fromArray options 空');
assertEq(array('express'),
    $msColl->get('levels_fixed')->getValue(),
    'multiselect 固定模式 fromArray value');
assertEq(array('standard', 'express', 'economy'),
    $msColl->get('levels_fixed')->getOptions(),
    'multiselect 固定模式 fromArray options 保留');

// 9. toArray: multiselect 节点应包含 options
$msTo = $msColl->toArray();
assertEq(true, isset($msTo['levels_fixed']['options']),
    'multiselect toArray 含 options');
assertEq(array('standard', 'express', 'economy'),
    $msTo['levels_fixed']['options'],
    'multiselect toArray options 数组');

echo PHP_EOL . ($failed ? "FAILED: {$failed} assertion(s)" : 'ALL PASS') . PHP_EOL;
exit($failed ? 1 : 0);
