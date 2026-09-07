<?php
/**
 * CustomAttribute 单元测试(L1 Domain,不依赖 Magento)
 *
 * 覆盖:
 *   - 4 个 entity_type 常量 + ALLOWED_ENTITY_TYPES
 *   - 合法构造 → getter
 *   - 非法:entity_type / field_key / label / field_type / options / default_value 抛 InvalidArgumentException
 *   - defaultValue 类型强转(text / number / boolean / select / multiselect)
 *   - toCustomFieldArray() 形态
 *   - select defaultValue 不在 options 抛异常
 *   - multiselect 自由模式(options=null) + 固定模式(options 非空)
 *
 * 运行:php app/code/community/XFE/Carrier/Test/Domain/CustomAttributeTest.php
 */

spl_autoload_register(function ($class) {
    if (strpos($class, 'XFE_Carrier_Domain_') !== 0) {
        return;
    }
    $parts = explode('_', $class);
    array_shift($parts); // XFE
    array_shift($parts); // Carrier
    array_shift($parts); // Domain
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

// ---------- 1. 常量 -------------------------------------------------------
assertEq(
    array('carrier', 'account', 'ftp_account', 'logo'),
    XFE_Carrier_Domain_CustomAttribute::ALLOWED_ENTITY_TYPES,
    '4 分类常量'
);

// ---------- 2. 合法构造 → getter ----------------------------------------
$a = new XFE_Carrier_Domain_CustomAttribute(
    7,                                  // id
    'account',                          // entity_type
    'warehouse_code',                   // field_key
    '仓库代码',                          // label
    'text',                             // field_type
    null,                               // options
    'WH-001',                           // default_value
    true,                               // is_required
    true,                               // is_active
    10,                                 // sort_order
    '仓库四字码'                          // description
);
assertEq(7, $a->getId(), 'getId');
assertEq('account', $a->getEntityType(), 'getEntityType');
assertEq('warehouse_code', $a->getFieldKey(), 'getFieldKey');
assertEq('仓库代码', $a->getLabel(), 'getLabel');
assertEq('text', $a->getFieldType(), 'getFieldType');
assertEq(null, $a->getOptions(), 'text options=null');
assertEq('WH-001', $a->getDefaultValue(), 'text default value');
assertEq(true, $a->isRequired(), 'isRequired true');
assertEq(true, $a->isActive(), 'isActive true');
assertEq(10, $a->getSortOrder(), 'sortOrder');
assertEq('仓库四字码', $a->getDescription(), 'description');

// null id / null description 也合法
$b = new XFE_Carrier_Domain_CustomAttribute(
    null, 'logo', 'note', '备注', 'text', null, 'hello', false, true, 0, null
);
assertEq(null, $b->getId(), 'null id 保留 null');
assertEq(null, $b->getDescription(), 'null description');

// ---------- 3. entity_type 非法 -----------------------------------------
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomAttribute(null, 'BOGUS', 'k', 'L', 'text');
}, '非法 entity_type 抛异常');

assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomAttribute(null, '', 'k', 'L', 'text');
}, '空 entity_type 抛异常');

// ---------- 4. field_key 非法 -------------------------------------------
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomAttribute(null, 'account', 'BadKey', 'L', 'text');
}, 'field_key 含大写抛异常');

assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomAttribute(null, 'account', '', 'L', 'text');
}, 'field_key 空抛异常');

assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomAttribute(
        null, 'account', str_repeat('a', 65), 'L', 'text'
    );
}, 'field_key > 64 字符抛异常');

assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomAttribute(null, 'account', 'with-dash', 'L', 'text');
}, 'field_key 含短横线抛异常');

// ---------- 5. label 非法 -------------------------------------------------
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomAttribute(null, 'account', 'k', '', 'text');
}, 'label 空抛异常');

assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomAttribute(
        null, 'account', 'k', str_repeat('字', 65), 'text'
    );
}, 'label > 64 字符抛异常');

// ---------- 6. field_type 非法 ------------------------------------------
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomAttribute(null, 'account', 'k', 'L', 'date');
}, '非法 field_type 抛异常');

// ---------- 7. select 必须有 options --------------------------------------
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomAttribute(
        null, 'account', 'k', 'L', 'select', null, 'x'
    );
}, 'select null options 抛异常');

assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomAttribute(
        null, 'account', 'k', 'L', 'select', array(), 'x'
    );
}, 'select 空 options 抛异常');

// select default 不在 options → 抛
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomAttribute(
        null, 'account', 'k', 'L', 'select',
        array('standard', 'express'), 'overnight'
    );
}, 'select default 不在 options 抛异常');

// select 合构造
$sel = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'svc', '服务', 'select',
    array('standard', 'express', 'economy'), 'express'
);
assertEq(array('standard', 'express', 'economy'), $sel->getOptions(), 'select options 数组');
assertEq('express', $sel->getDefaultValue(), 'select default value');

// select 无默认(null)→ 允许
$selNull = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'svc2', '服务2', 'select',
    array('a', 'b'), null
);
assertEq(null, $selNull->getDefaultValue(), 'select null default → null');

$selEmpty = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'svc3', '服务3', 'select',
    array('a', 'b'), ''
);
assertEq(null, $selEmpty->getDefaultValue(), 'select 空 default → null');

// ---------- 8. text coerce -----------------------------------------------
$textNull = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'text', null, null
);
assertEq('', $textNull->getDefaultValue(), 'text null default → ""');

$textEmpty = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'text', null, ''
);
assertEq('', $textEmpty->getDefaultValue(), 'text 空 default → ""');

$textStr = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'text', null, 42
);
assertEq('42', $textStr->getDefaultValue(), 'text 数字 default → 字符串');

// ---------- 9. number coerce ---------------------------------------------
$numInt = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'number', null, '20'
);
assertEq(20, $numInt->getDefaultValue(), 'number 整数 → int');

$numFloat = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'number', null, '3.14'
);
assertEq(3.14, $numFloat->getDefaultValue(), 'number 小数 → float');

$numNull = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'number', null, ''
);
assertEq(null, $numNull->getDefaultValue(), 'number 空 → null');

$numStr = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'number', null, 'abc'
);
assertEq(null, $numStr->getDefaultValue(), 'number 非数字 → null');

// ---------- 10. boolean coerce -------------------------------------------
$bt = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'boolean', null, '1'
);
assertEq(true, $bt->getDefaultValue(), 'boolean "1" → true');

$bf = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'boolean', null, '0'
);
assertEq(false, $bf->getDefaultValue(), 'boolean "0" → false');

$bempty = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'boolean', null, ''
);
assertEq(false, $bempty->getDefaultValue(), 'boolean "" → false');

// ---------- 11. multiselect 自由模式(options=null) -----------------------
$msFree = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'multiselect', null,
    '华东, 华南 , 华东'
);
assertEq(array(), $msFree->getOptions(), 'multiselect 自由模式 options=[]');
assertEq(array('华东', '华南'), $msFree->getDefaultValue(),
    'multiselect 自由模式 default 去重 + trim');

$msFreeArr = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'multiselect', null,
    array('华东', '华北')
);
assertEq(array('华东', '华北'), $msFreeArr->getDefaultValue(),
    'multiselect 自由模式 array default');

$msFreeEmpty = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'multiselect', null, ''
);
assertEq(array(), $msFreeEmpty->getDefaultValue(),
    'multiselect 自由模式 空 default → []');

$msFreeNull = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'multiselect', null, null
);
assertEq(array(), $msFreeNull->getDefaultValue(),
    'multiselect 自由模式 null default → []');

// ---------- 12. multiselect 固定模式(options 非空) -----------------------
$msFixed = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'multiselect',
    array('standard', 'express', 'economy'),
    'express,standard'
);
assertEq(
    array('standard', 'express', 'economy'),
    $msFixed->getOptions(),
    'multiselect 固定模式 options 保留'
);
assertEq(array('express', 'standard'), $msFixed->getDefaultValue(),
    'multiselect 固定模式 default 拆分 + 去重');

// 固定模式 default 含 options 外 → 抛
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomAttribute(
        null, 'account', 'k', 'L', 'multiselect',
        array('standard', 'express'), 'overnight'
    );
}, 'multiselect 固定模式 default 不在 options 抛异常');

// ---------- 13. toCustomFieldArray() ------------------------------------
$arr = $sel->toCustomFieldArray();
assertEq('服务', $arr['label'], 'toCustomFieldArray label');
assertEq('select', $arr['type'], 'toCustomFieldArray type');
assertEq('express', $arr['value'], 'toCustomFieldArray value');
assertEq(true, isset($arr['options']), 'toCustomFieldArray 含 options');
assertEq(array('standard', 'express', 'economy'), $arr['options'], 'toCustomFieldArray options');

// text 不含 options
$arrText = (new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'text', null, 'v'
))->toCustomFieldArray();
assertEq(false, isset($arrText['options']), 'text toCustomFieldArray 不含 options');

// multiselect 含 options
$arrMs = $msFixed->toCustomFieldArray();
assertEq('multiselect', $arrMs['type'], 'multiselect toCustomFieldArray type');
assertEq(true, isset($arrMs['options']), 'multiselect toCustomFieldArray 含 options');

// boolean / number 也都不含 options
$arrBool = (new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'boolean', null, '1'
))->toCustomFieldArray();
assertEq(true, $arrBool['value'], 'boolean toCustomFieldArray value');
assertEq(false, isset($arrBool['options']), 'boolean toCustomFieldArray 不含 options');

$arrNum = (new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'number', null, '5'
))->toCustomFieldArray();
assertEq(5, $arrNum['value'], 'number toCustomFieldArray value int');
assertEq(false, isset($arrNum['options']), 'number toCustomFieldArray 不含 options');

// ---------- 14. is_required / is_active / sort_order 边界 ----------------
$booleans = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'text', null, 'v', 'yes', 1, 0
);
assertEq(true, $booleans->isRequired(), 'isRequired 接受真值');
assertEq(true, $booleans->isActive(), 'isActive 接受 1');

$booleansFalse = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'k', 'L', 'text', null, 'v', '', '0', 0
);
assertEq(false, $booleansFalse->isRequired(), 'isRequired 空字符串 → false');
assertEq(false, $booleansFalse->isActive(), 'isActive 0 → false');

echo PHP_EOL . ($failed ? "FAILED: {$failed} assertion(s)" : 'ALL PASS') . PHP_EOL;
exit($failed ? 1 : 0);
