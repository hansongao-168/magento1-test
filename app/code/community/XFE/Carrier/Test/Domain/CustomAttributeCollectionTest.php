<?php
/**
 * CustomAttributeCollection 单元测试(L1 Domain)
 *
 * 覆盖:
 *   - add / has / get / remove / count
 *   - field_key 唯一性(DomainException)
 *   - getRequiredKeys() / filterActive() / toArray() / toCustomFieldArray()
 *   - fromArray() 从 DB 行 list 构建集合
 *   - IteratorAggregate 排序:sort_order 升序 → field_key 字母序
 *
 * 运行:php app/code/community/XFE/Carrier/Test/Domain/CustomAttributeCollectionTest.php
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

function mkAttr($key, $opts = array())
{
    $entityType = isset($opts['entity_type']) ? $opts['entity_type'] : 'account';
    $label      = isset($opts['label'])      ? $opts['label']      : strtoupper($key);
    $fieldType  = isset($opts['field_type']) ? $opts['field_type'] : 'text';
    $options    = isset($opts['options'])    ? $opts['options']    : null;
    $defaultVal = isset($opts['default'])    ? $opts['default']    : null;
    $isRequired = isset($opts['required'])   ? $opts['required']   : false;
    $isActive   = isset($opts['active'])     ? $opts['active']     : true;
    $sortOrder  = isset($opts['sort'])       ? $opts['sort']       : 0;
    $desc       = isset($opts['desc'])       ? $opts['desc']       : null;
    return new XFE_Carrier_Domain_CustomAttribute(
        null, $entityType, $key, $label, $fieldType, $options,
        $defaultVal, $isRequired, $isActive, $sortOrder, $desc
    );
}

// ---------- 1. add / has / get / count ----------------------------------
$coll = new XFE_Carrier_Domain_CustomAttributeCollection();
assertEq(0, $coll->count(), '初始 count=0');
$coll->add(mkAttr('wh', array('label' => '仓库')));
assertEq(1, $coll->count(), 'add 后 count=1');
assertEq(true, $coll->has('wh'), 'has true');
assertEq(false, $coll->has('zzz'), 'has false');
assertEq('仓库', $coll->get('wh')->getLabel(), 'get 返回原对象');
assertEq(null, $coll->get('zzz'), 'get 不存在 → null');

// ---------- 2. key 唯一性 -----------------------------------------------
assertThrows('DomainException', function () use ($coll) {
    $coll->add(mkAttr('wh', array('label' => '重复')));
}, '重复 key 抛 DomainException');

// ---------- 3. remove ----------------------------------------------------
$coll->remove('wh');
assertEq(0, $coll->count(), 'remove 后 count=0');
assertEq(false, $coll->has('wh'), 'remove 后 has=false');

// remove 不存在的 key 不报错
$coll->remove('zzz');
assertEq(0, $coll->count(), 'remove 不存在 key 静默');

// ---------- 4. getKeys ---------------------------------------------------
$coll = new XFE_Carrier_Domain_CustomAttributeCollection();
$coll->add(mkAttr('a'));
$coll->add(mkAttr('b'));
$coll->add(mkAttr('c'));
assertEq(array('a', 'b', 'c'), $coll->getKeys(), 'getKeys 顺序');

// ---------- 5. getRequiredKeys -------------------------------------------
$coll = new XFE_Carrier_Domain_CustomAttributeCollection();
$coll->add(mkAttr('req1', array('required' => true)));
$coll->add(mkAttr('opt1', array('required' => false)));
$coll->add(mkAttr('req2', array('required' => true)));
assertEq(array('req1', 'req2'), $coll->getRequiredKeys(), '必填 keys');

// ---------- 6. filterActive ----------------------------------------------
$coll = new XFE_Carrier_Domain_CustomAttributeCollection();
$coll->add(mkAttr('on1',  array('active' => true)));
$coll->add(mkAttr('off',  array('active' => false)));
$coll->add(mkAttr('on2',  array('active' => true)));
$active = $coll->filterActive();
assertEq(2, $active->count(), 'filterActive 排除 inactive');
assertEq(true, $active->has('on1'), 'on1 保留');
assertEq(false, $active->has('off'), 'off 排除');
assertEq(true, $active->has('on2'), 'on2 保留');
// 原集合不变
assertEq(3, $coll->count(), 'filterActive 不改原集合');

// ---------- 7. toArray / toCustomFieldArray ----------------------------
$coll = new XFE_Carrier_Domain_CustomAttributeCollection();
$coll->add(mkAttr('wh', array(
    'label' => '仓库', 'field_type' => 'text', 'default' => 'WH-1'
)));
$coll->add(mkAttr('svc', array(
    'label' => '服务', 'field_type' => 'select',
    'options' => array('a', 'b'), 'default' => 'a'
)));

$arr = $coll->toArray();
assertEq(true, isset($arr['wh']), 'toArray 包含 wh');
assertEq(true, isset($arr['svc']), 'toArray 包含 svc');
assertEq('仓库', $arr['wh']->getLabel(), 'toArray 值是 Domain 对象');

$cf = $coll->toCustomFieldArray();
assertEq('仓库', $cf['wh']['label'], 'toCustomFieldArray wh.label');
assertEq('text',  $cf['wh']['type'],  'toCustomFieldArray wh.type');
assertEq('WH-1',  $cf['wh']['value'], 'toCustomFieldArray wh.value');
assertEq('a',     $cf['svc']['value'],'toCustomFieldArray svc.value');
assertEq(array('a', 'b'), $cf['svc']['options'], 'toCustomFieldArray svc.options');
assertEq(false, isset($cf['wh']['options']), 'text 不含 options');
assertEq(true,  isset($cf['svc']['options']), 'select 含 options');

// ---------- 8. fromArray() 从 DB 行 list --------------------------------
$rows = array(
    array(
        'id' => 1, 'entity_type' => 'account', 'field_key' => 'wh',
        'label' => '仓库', 'field_type' => 'text',
        'options_csv' => '', 'default_value' => null,
        'is_required' => 1, 'is_active' => 1, 'sort_order' => 10,
        'description' => '仓库四字码',
    ),
    array(
        'id' => 2, 'entity_type' => 'account', 'field_key' => 'svc',
        'label' => '服务', 'field_type' => 'select',
        'options_csv' => 'a, b , c', 'default_value' => '"b"',
        'is_required' => 0, 'is_active' => 1, 'sort_order' => 20,
        'description' => null,
    ),
    array(
        'id' => 3, 'entity_type' => 'account', 'field_key' => 'areas',
        'label' => '区域', 'field_type' => 'multiselect',
        'options_csv' => '', 'default_value' => '["华东","华南"]',
        'is_required' => 0, 'is_active' => 0, 'sort_order' => 30,
        'description' => null,
    ),
);
$dbColl = XFE_Carrier_Domain_CustomAttributeCollection::fromArray($rows);
assertEq(3, $dbColl->count(), 'fromArray 3 行');
assertEq('仓库', $dbColl->get('wh')->getLabel(), 'wh label');
assertEq(true, $dbColl->get('wh')->isRequired(), 'wh isRequired');
assertEq(array('a', 'b', 'c'), $dbColl->get('svc')->getOptions(), 'svc options CSV 拆分');
assertEq('b', $dbColl->get('svc')->getDefaultValue(), 'svc default JSON 解码');
assertEq(false, $dbColl->get('areas')->isActive(), 'areas is_active=0 保留');
assertEq(array('华东', '华南'), $dbColl->get('areas')->getDefaultValue(),
    'areas default JSON 数组');

// 非法行:整行不是数组 / 缺关键字段 / field_type 非法 → 跳过
$bad = array(
    'not_an_array',  // ← 整行非数组
    array('id' => 4, 'entity_type' => 'account'),  // 缺 field_key/label/field_type
    array(
        'id' => 5, 'entity_type' => 'account', 'field_key' => 'bad',
        'label' => 'B', 'field_type' => 'date',
    ),
    array(
        'id' => 6, 'entity_type' => 'BOGUS', 'field_key' => 'k',
        'label' => 'L', 'field_type' => 'text',
    ),
);
$badColl = XFE_Carrier_Domain_CustomAttributeCollection::fromArray($bad);
assertEq(0, $badColl->count(), 'fromArray 全部非法 → 空集合');

// 部分合法:挑 1 行有效 + 1 行非法
$mixed = array_merge($bad, array(
    array(
        'id' => 99, 'entity_type' => 'logo', 'field_key' => 'note',
        'label' => '备注', 'field_type' => 'text',
    ),
));
$mixedColl = XFE_Carrier_Domain_CustomAttributeCollection::fromArray($mixed);
assertEq(1, $mixedColl->count(), '部分合法仅保留 1 条');
assertEq('logo', $mixedColl->get('note')->getEntityType(), 'logo 分类保留');

// ---------- 9. IteratorAggregate 排序:sort_order → field_key -------------
$coll = new XFE_Carrier_Domain_CustomAttributeCollection();
$coll->add(mkAttr('z_third',  array('sort' => 30)));
$coll->add(mkAttr('a_first',  array('sort' => 10)));
$coll->add(mkAttr('b_second', array('sort' => 20)));
$coll->add(mkAttr('a_tied',   array('sort' => 10)));   // 同 sort 与 a_first

$keys = array();
foreach ($coll as $attr) { $keys[] = $attr->getFieldKey(); }
assertEq(array('a_first', 'a_tied', 'b_second', 'z_third'), $keys,
    'sort_order 升序 → field_key 字母序');

// ---------- 10. defaultValue 不影响 key 唯一性 -------------------------
$coll2 = new XFE_Carrier_Domain_CustomAttributeCollection();
$coll2->add(mkAttr('k1', array('default' => 'v1')));
try {
    $coll2->add(mkAttr('k1', array('default' => 'v2')));
    echo "[FAIL] 重复 key(不同 default)未抛异常\n"; $failed++;
} catch (DomainException $e) {
    echo "[PASS] 重复 key(不同 default)抛 DomainException\n";
}

echo PHP_EOL . ($failed ? "FAILED: {$failed} assertion(s)" : 'ALL PASS') . PHP_EOL;
exit($failed ? 1 : 0);
