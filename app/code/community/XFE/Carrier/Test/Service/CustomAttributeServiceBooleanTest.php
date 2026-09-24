<?php
/**
 * CustomAttributeService boolean label 自定义测试(小改 K,2026-09-18,ADR 0023)
 *
 * 覆盖:
 *   - Domain 层 boolean 默认 options(空 → {0:否,1:是})
 *   - Domain 层 boolean 自定义 label(开启/关闭 等)
 *   - Domain 层 boolean 校验失败(行数 != 2 / key != {0,1})
 *   - Domain 层 parseOptionsCsvToPairs / serializeOptionsPairsToCsv
 *   - Service 层 getBooleanLabels() 与 Domain 一致
 *   - 旧数据兼容:options_csv="0|否,1|是" / "0,1" 都能解析
 *   - 缺记录回退:options=null 时 getBooleanLabels 返回 否/是
 *
 * 设计:
 *   - Mage 桩 + autoloader 与 CustomAttributeServiceMigrationTest 保持一致
 *   - 跑法: php app/code/community/XFE/Carrier/Test/Service/CustomAttributeServiceBooleanTest.php
 *   - 输出末尾必须形如 "Total: N assertions, M failures" 以被 tests/php/run-tests.php 解析
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
        public function __()
        {
            $args = func_get_args();
            $s = (string) array_shift($args);
            if (empty($args)) { return $s; }
            $result = @vsprintf($s, $args);
            return $result === false ? $s : $result;
        }
    }
}

// Domain CustomField 常量桩
if (!class_exists('XFE_Carrier_Domain_CustomField', false)) {
    class XFE_Carrier_Domain_CustomField
    {
        const TYPE_TEXT        = 'text';
        const TYPE_NUMBER      = 'number';
        const TYPE_SELECT      = 'select';
        const TYPE_MULTISELECT = 'multiselect';
        const TYPE_BOOLEAN     = 'boolean';
        const ALLOWED_TYPES    = array(self::TYPE_TEXT, self::TYPE_NUMBER, self::TYPE_SELECT, self::TYPE_MULTISELECT, self::TYPE_BOOLEAN);
    }
}

// ---- autoloader ------------------------------------------------------
spl_autoload_register(function ($class) {
    $prefixes = array(
        'XFE_Carrier_Domain_'             => array('/../../Domain/',             3),
        'XFE_Carrier_Model_Service_'      => array('/../../Model/Service/',      4),
    );
    foreach ($prefixes as $p => $cfg) {
        if (strpos($class, $p) === 0) {
            list($rel, $shift) = $cfg;
            $parts = explode('_', $class);
            for ($i = 0; $i < $shift; $i++) { array_shift($parts); }
            $path = implode('/', $parts) . '.php';
            $candidate = __DIR__ . $rel . $path;
            if (file_exists($candidate)) {
                require_once $candidate;
            }
            return;
        }
    }
});

// ---- assert helpers --------------------------------------------------
$assertions = 0;
$failures   = 0;
function assertEq($expected, $actual, $label) {
    global $assertions, $failures;
    $assertions++;
    $ok = $expected === $actual;
    if ($ok) {
        echo "[PASS] $label\n";
    } else {
        $failures++;
        echo "[FAIL] $label\n";
        echo "       expected: " . var_export($expected, true) . "\n";
        echo "       actual:   " . var_export($actual, true) . "\n";
    }
}
function assertThrows($class, callable $fn, $label) {
    global $assertions, $failures;
    $assertions++;
    try {
        $fn();
        $failures++;
        echo "[FAIL] $label - no exception thrown\n";
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            echo "[PASS] $label\n";
        } else {
            $failures++;
            echo "[FAIL] $label - wrong exception type: " . get_class($e) . ': ' . $e->getMessage() . "\n";
        }
    }
}

echo "=== Domain boolean label 自定义(小改 K,ADR 0023) ===\n";

// ============================================================
// 1. boolean 默认 options(空 → {0:否,1:是})
// ============================================================
echo "\n--- boolean 默认 options ---\n";
$b1 = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'urgent', '是否紧急', 'boolean'
);
assertEq(
    array(array('key' => '0', 'label' => '否'), array('key' => '1', 'label' => '是')),
    $b1->getOptions(),
    'B1 boolean 空 options → 默认 2 行{0:否,1:是}'
);
assertEq(
    array('0' => '否', '1' => '是'),
    $b1->getBooleanLabels(),
    'B2 boolean 默认 getBooleanLabels → 否/是'
);
assertEq(
    array('0', '1'),
    $b1->getOptionKeys(),
    'B3 boolean 默认 getOptionKeys → [0,1]'
);

// ============================================================
// 2. boolean 自定义 label(开启/关闭)
// ============================================================
echo "\n--- boolean 自定义 label ---\n";
$b2 = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'sw', '启用开关', 'boolean',
    array(
        array('key' => '0', 'label' => '关闭'),
        array('key' => '1', 'label' => '开启'),
    )
);
assertEq(
    array(array('key' => '0', 'label' => '关闭'), array('key' => '1', 'label' => '开启')),
    $b2->getOptions(),
    'B4 boolean 自定义 options 结构'
);
assertEq(
    array('0' => '关闭', '1' => '开启'),
    $b2->getBooleanLabels(),
    'B5 boolean 自定义 getBooleanLabels → 关闭/开启'
);
assertEq(
    array('0', '1'),
    $b2->getOptionKeys(),
    'B6 boolean 自定义 getOptionKeys → [0,1](仅 keys)'
);

// 男/女 场景
$bMale = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'sex', '性别', 'boolean',
    array(
        array('key' => '0', 'label' => '女'),
        array('key' => '1', 'label' => '男'),
    )
);
assertEq(
    array('0' => '女', '1' => '男'),
    $bMale->getBooleanLabels(),
    'B7 boolean 男/女场景'
);

// ============================================================
// 3. boolean string[] 入口(向后兼容小改 G 之前)
// ============================================================
echo "\n--- boolean string[] 入口 ---\n";
$b3 = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'sw2', '开关2', 'boolean',
    array('0', '1')
);
assertEq(
    array(array('key' => '0', 'label' => '0'), array('key' => '1', 'label' => '1')),
    $b3->getOptions(),
    'B8 boolean string[] 入口归一化为结构化(key=label=原值)'
);
assertEq(
    array('0' => '0', '1' => '1'),
    $b3->getBooleanLabels(),
    'B9 boolean string[] getBooleanLabels → 用 key 当 label'
);

// ============================================================
// 4. boolean 校验失败:行数 != 2
// ============================================================
echo "\n--- boolean 校验失败:行数 ---\n";
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomAttribute(
        null, 'account', 'k', 'L', 'boolean',
        array(array('key' => '0', 'label' => '否'))
    );
}, 'B10 boolean options 只有 1 行 → 抛 InvalidArgumentException');

assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomAttribute(
        null, 'account', 'k', 'L', 'boolean',
        array(
            array('key' => '0', 'label' => '否'),
            array('key' => '1', 'label' => '是'),
            array('key' => 'x', 'label' => 'X'),
        )
    );
}, 'B11 boolean options 3 行 → 抛 InvalidArgumentException');

// ============================================================
// 5. boolean 校验失败:key 不是 0/1
// ============================================================
echo "\n--- boolean 校验失败:key 非法 ---\n";
assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomAttribute(
        null, 'account', 'k', 'L', 'boolean',
        array(
            array('key' => '1', 'label' => '是'),
            array('key' => '2', 'label' => '二'),
        )
    );
}, 'B12 boolean options key=2 → 抛 InvalidArgumentException');

assertThrows('InvalidArgumentException', function () {
    new XFE_Carrier_Domain_CustomAttribute(
        null, 'account', 'k', 'L', 'boolean',
        array(
            array('key' => 'a', 'label' => 'a'),
            array('key' => 'b', 'label' => 'b'),
        )
    );
}, 'B13 boolean options key=ab → 抛 InvalidArgumentException');

// key 顺序不影响(0/1 集合相等即可)
assertEq(
    array('0' => '否', '1' => '是'),
    (new XFE_Carrier_Domain_CustomAttribute(
        null, 'account', 'k', 'L', 'boolean',
        array(
            array('key' => '1', 'label' => '是'),
            array('key' => '0', 'label' => '否'),
        )
    ))->getBooleanLabels(),
    'B14 boolean options key 顺序 1/0 → getBooleanLabels 仍正确'
);

// ============================================================
// 6. parseOptionsCsvToPairs
// ============================================================
echo "\n--- parseOptionsCsvToPairs ---\n";
assertEq(array(),
    XFE_Carrier_Domain_CustomAttribute::parseOptionsCsvToPairs(''),
    'B15 parseOptionsCsvToPairs 空 → []');
assertEq(array(),
    XFE_Carrier_Domain_CustomAttribute::parseOptionsCsvToPairs(null),
    'B16 parseOptionsCsvToPairs null → []');
assertEq(
    array(array('key' => 'red', 'label' => 'red'), array('key' => 'blue', 'label' => 'blue')),
    XFE_Carrier_Domain_CustomAttribute::parseOptionsCsvToPairs('red,blue'),
    'B17 parseOptionsCsvToPairs 普通(无 |)'
);
assertEq(
    array(array('key' => 'red', 'label' => '红'), array('key' => 'blue', 'label' => '蓝')),
    XFE_Carrier_Domain_CustomAttribute::parseOptionsCsvToPairs('red|红,blue|蓝'),
    'B18 parseOptionsCsvToPairs key|label'
);
assertEq(
    array(array('key' => '0', 'label' => '否'), array('key' => '1', 'label' => '是')),
    XFE_Carrier_Domain_CustomAttribute::parseOptionsCsvToPairs('0|否,1|是'),
    'B19 parseOptionsCsvToPairs boolean key|label'
);
assertEq(
    array(array('key' => '0', 'label' => '0'), array('key' => '1', 'label' => '1')),
    XFE_Carrier_Domain_CustomAttribute::parseOptionsCsvToPairs('0,1'),
    'B20 parseOptionsCsvToPairs boolean 旧格式(无 |)'
);
assertEq(
    array(array('key' => 'a', 'label' => 'a/b')),
    XFE_Carrier_Domain_CustomAttribute::parseOptionsCsvToPairs('a|a/b'),
    'B21 parseOptionsCsvToPairs label 含 |(只 split 第一段)'
);
// ============================================================
// 7. serializeOptionsPairsToCsv
// ============================================================
// 7. serializeOptionsPairsToCsv
assertEq('',
    XFE_Carrier_Domain_CustomAttribute::serializeOptionsPairsToCsv(null),
    'B22 serializeOptionsPairsToCsv null → ""');
assertEq('',
    XFE_Carrier_Domain_CustomAttribute::serializeOptionsPairsToCsv(array()),
    'B23 serializeOptionsPairsToCsv 空 → ""');
assertEq('red,blue',
    XFE_Carrier_Domain_CustomAttribute::serializeOptionsPairsToCsv(
        array(
            array('key' => 'red', 'label' => 'red'),
            array('key' => 'blue', 'label' => 'blue'),
        )
    ),
    'B24 serializeOptionsPairsToCsv label==key → 只输出 key'
);
assertEq('0|否,1|是',
    XFE_Carrier_Domain_CustomAttribute::serializeOptionsPairsToCsv(
        array(
            array('key' => '0', 'label' => '否'),
            array('key' => '1', 'label' => '是'),
        )
    ),
    'B25 serializeOptionsPairsToCsv key|label'
);
// ============================================================
// 8. roundtrip:serialize → parse
// ============================================================
echo "\n--- roundtrip:serialize → parse ---\n";
$src = array(
    array('key' => '0', 'label' => '关闭'),
    array('key' => '1', 'label' => '开启'),
);
$csv = XFE_Carrier_Domain_CustomAttribute::serializeOptionsPairsToCsv($src);
assertEq('0|关闭,1|开启', $csv, 'B26 serialize 关闭/开启');
$back = XFE_Carrier_Domain_CustomAttribute::parseOptionsCsvToPairs($csv);
assertEq($src, $back, 'B27 roundtrip serialize → parse 还原');

// ============================================================
// 9. toCustomFieldArray 输出 keys(给 CustomField 喂 string[])
// ============================================================
echo "\n--- toCustomFieldArray 输出 keys ---\n";
$bCustom = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'urgent', '是否紧急', 'boolean'
);
$arr = $bCustom->toCustomFieldArray();
assertEq(true, isset($arr['options']), 'B28 boolean toCustomFieldArray 含 options 字段');
assertEq(array('0', '1'), $arr['options'], 'B29 boolean toCustomFieldArray options 仅 keys');

// ============================================================
// 10. select/multiselect 同样走结构化
// ============================================================
echo "\n--- select/multiselect 兼容 ---\n";
$sel = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'svc', '服务', 'select',
    array('red|红', 'blue|蓝')
);
assertEq(
    array(
        array('key' => 'red', 'label' => '红'),
        array('key' => 'blue', 'label' => '蓝'),
    ),
    $sel->getOptions(),
    'B30 select key|label 入口结构化'
);
assertEq(array('red', 'blue'), $sel->getOptionKeys(), 'B31 select getOptionKeys');

$ms = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'areas', '区域', 'multiselect',
    array('CN|中国', 'US|美国')
);
assertEq(
    array(
        array('key' => 'CN', 'label' => '中国'),
        array('key' => 'US', 'label' => '美国'),
    ),
    $ms->getOptions(),
    'B32 multiselect key|label 入口结构化'
);

echo "\n========================================\n";
printf("Total: %d assertions, %d failures\n", $assertions, $failures);
echo "========================================\n";
exit($failures === 0 ? 0 : 1);
