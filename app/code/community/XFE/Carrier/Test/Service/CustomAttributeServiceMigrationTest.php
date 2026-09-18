<?php
/**
 * CustomAttributeService options_csv 迁移测试(小改 C,2026-09-17)
 *
 * 覆盖:
 *   - _detectOptionsMigration 各种情形:
 *     - options 未变 / type 不是 select|multiselect → 快速返回 ok
 *     - options 变 + default 仍合法 → ok
 *     - options 变 + select default 不在 options → incompatible
 *     - options 变 + multiselect 含非法项 → incompatible(列出所有非法)
 *     - options 变 + default 为 null/空 → ok
 *
 *   - _applyMigrationStrategy 三种策略:
 *     - reject + select → Mage_Core_Exception,列出非法值
 *     - reject + multiselect → Mage_Core_Exception
 *     - auto_clean + select → default 变 null
 *     - auto_clean + multiselect(部分合法)→ 只保留合法项
 *     - auto_clean + multiselect(全部非法)→ 变 []
 *     - set_null + select → default 变 null
 *     - set_null + multiselect → default 变 []
 *     - 非法 strategy → 退回 reject(防御)
 *
 * 设计:
 *   - Mage 桩 + autoloader 与 CustomAttributeApplierStrictTest 保持一致
 *   - Service 的 protected 方法通过 Probe 子类暴露
 *   - 跑法: php app/code/community/XFE/Carrier/Test/Service/CustomAttributeServiceMigrationTest.php
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
        /**
         * 兼容 Magento __() 翻译 API 的最小桩:
         * 当 $args 非空时,用 vsprintf 做 %s / %d 占位符替换
         * (Magento 真实实现是 i18n + 占位符,这里只关注占位符部分)。
         */
        public function __()
        {
            $args = func_get_args();
            $s = (string) array_shift($args);
            if (empty($args)) { return $s; }
            // vsprintf 对中文 + %s 一般 OK,这里做兜底
            $result = @vsprintf($s, $args);
            return $result === false ? $s : $result;
        }
    }
}

// Domain 类实际不需要(因为 _detectOptionsMigration 接收的 field_type 是字符串,
// 我们只用字符串 == 比较)。但为了 Probe 子类能继承 Service,需要先把 Domain 常量准备好。
// 这里手写最小版的 Domain 常量类以避免 autoload 完整 Domain。
if (!class_exists('XFE_Carrier_Domain_CustomField', false)) {
    class XFE_Carrier_Domain_CustomField
    {
        const TYPE_TEXT        = 'text';
        const TYPE_NUMBER      = 'number';
        const TYPE_SELECT      = 'select';
        const TYPE_MULTISELECT = 'multiselect';
        const TYPE_BOOLEAN     = 'boolean';
    }
}

// ---- autoloader ------------------------------------------------------
// ---- autoloader(命名函数避免嵌套闭包边界 case)----
spl_autoload_register('_migration_test_autoload');
function _migration_test_autoload($class) {
    static $prefixes = array(
        'XFE_Carrier_Domain_'        => array('/../../Domain/',        3),
        'XFE_Carrier_Model_Service_' => array('/../../Model/Service/', 4),
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
        }
    }
}

class CustomAttributeServiceProbe extends XFE_Carrier_Model_Service_CustomAttributeService
{
    public function detectOptionsMigration($oldCsv, $newCsv, $type, $defaultValue)
    {
        return $this->_detectOptionsMigration($oldCsv, $newCsv, $type, $defaultValue);
    }

    public function applyMigrationStrategy(array $normalized, array $incompatible, $strategy, $type)
    {
        return $this->_applyMigrationStrategy($normalized, $incompatible, $strategy, $type);
    }
}

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
function assertThrowsMage($label, callable $fn, $msgContains = null) {
    global $assertions, $failures;
    $assertions++;
    try {
        $fn();
        $failures++;
        echo "[FAIL] $label - no exception thrown\n";
    } catch (Mage_Core_Exception $e) {
        if ($msgContains === null || strpos($e->getMessage(), $msgContains) !== false) {
            echo "[PASS] $label - " . $e->getMessage() . "\n";
        } else {
            $failures++;
            echo "[FAIL] $label - message mismatch\n";
            echo "       expected contains: $msgContains\n";
            echo "       actual: " . $e->getMessage() . "\n";
        }
    } catch (Throwable $e) {
        $failures++;
        echo "[FAIL] $label - wrong exception type: " . get_class($e) . ': ' . $e->getMessage() . "\n";
    }
}

$probe = new CustomAttributeServiceProbe();

// ============================================================
// _detectOptionsMigration
// ============================================================
echo "=== _detectOptionsMigration ===\n";

// 1. options 未变(调用者层会跳过,但方法本身仍能正确判定 ok)
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b,c', 'a,b,c', 'select', 'a'
);
assertEq('ok', $status, 'D1 options 未变 select default 仍合法 → ok');
assertEq(array(), $bad, 'D1 incompatible 空');
assertEq(array('a','b','c'), $old, 'D1 oldOptions 正确');

// 2. type=text → 快速返回 ok(options_csv 无关)
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b,c', 'x,y,z', 'text', 'whatever'
);
assertEq('ok', $status, 'D2 type=text → ok(跳过 options 校验)');
assertEq(array(), $bad, 'D2 type=text incompatible 空');

// 3. type=boolean + 仅 1 行 \u9009\u9879 \u2192 \u4e0d\u517c\u5bb9(\u5c0f\u6539 K \u52a0 boolean \u5206\u652f)
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b', 'x', 'boolean', '1'
);
assertEq('incompatible', $status, 'D3 type=boolean \u9009\u9879\u4ec5 1 \u884c \u2192 incompatible');
assertEq(array('options_count=1'), $bad, 'D3 bad = options_count=1');

// D3.1 boolean + 仅 1 行 → incompatible options_count=1(小改 K 加 boolean 分支)
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b', 'x', 'boolean', '1'
);
assertEq('incompatible', $status, 'D3.1 boolean 仅 1 行 → incompatible');
assertEq(array('options_count=1'), $bad, 'D3.1 bad = options_count=1');

// D3.2 boolean + 3 行 → incompatible options_count=3
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b', '0|否,1|是,2|其他', 'boolean', '1'
);
assertEq('incompatible', $status, 'D3.2 boolean 3 行 → incompatible');
assertEq(array('options_count=3'), $bad, 'D3.2 bad = options_count=3');

// D3.3 boolean + key 不在 {0,1} → incompatible options_keys=[...]
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b', 'a|否,b|是', 'boolean', 'a'
);
assertEq('incompatible', $status, 'D3.3 boolean key 不在 {0,1} → incompatible');
assertEq(array('options_keys=[a,b]'), $bad, 'D3.3 bad = options_keys=[a,b]');

// D3.4 boolean + default=2 → incompatible default_value=2
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b', '0|否,1|是', 'boolean', '2'
);
assertEq('incompatible', $status, 'D3.4 boolean default=2 → incompatible');
assertEq(array('default_value=2'), $bad, 'D3.4 bad = default_value=2');

// D3.5 boolean + 合法 2 行 + default=1 → ok
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b', '0|否,1|是', 'boolean', '1'
);
assertEq('ok', $status, 'D3.5 boolean 合法 2 行 + default=1 → ok');
assertEq(array(), $bad, 'D3.5 incompatible 空');

// D3.6 boolean + 仅改 label(0|否→0|关闭)→ ok(label 文案修改允许)
list($status, $bad, $old) = $probe->detectOptionsMigration(
    '0|否,1|是', '0|关闭,1|开启', 'boolean', '1'
);
assertEq('ok', $status, 'D3.6 boolean 仅改 label → ok');

// D3.7 boolean + null default → ok
list($status, $bad, $old) = $probe->detectOptionsMigration(
    '0|否,1|是', '0|否,1|是', 'boolean', null
);
assertEq('ok', $status, 'D3.7 boolean default=null → ok');

// 4. type=number → 同样快速返回 ok
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b', 'x', 'number', 42
);
assertEq('ok', $status, 'D4 type=number → ok');

// 5. options 变 + select default 仍合法 → ok
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b,c', 'a,b,c,d', 'select', 'b'
);
assertEq('ok', $status, 'D5 options 增项 + default 仍合法 → ok');
assertEq(array('a','b','c'), $old, 'D5 oldOptions 正确(增量前)');

// 6. options 变 + select default 不在 options → incompatible
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b,c', 'd,e,f', 'select', 'a'
);
assertEq('incompatible', $status, 'D6 select default 不在新 options → incompatible');
assertEq(array('a'), $bad, 'D6 incompatible = [原 default 值]');

// 7. options 变 + multiselect 含非法项
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b,c', 'a,b', 'multiselect', array('a', 'c', 'b')
);
assertEq('incompatible', $status, 'D7 multiselect 含非法项 → incompatible');
assertEq(array('c'), $bad, 'D7 incompatible 列出所有非法项');

// 8. options 变 + multiselect 全部非法
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b', 'c,d', 'multiselect', array('a', 'b')
);
assertEq('incompatible', $status, 'D8 multiselect 全部非法 → incompatible');
assertEq(array('a','b'), $bad, 'D8 incompatible 包含所有原项');

// 9. options 变 + default 为 null → ok
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b', 'x,y', 'select', null
);
assertEq('ok', $status, 'D9 default=null → ok');

// 10. options 变 + default 为 '' → ok
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b', 'x,y', 'select', ''
);
assertEq('ok', $status, 'D10 default=空串 → ok');

// 11. options 变 + multiselect default 为 [] → ok
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b', 'x,y', 'multiselect', array()
);
assertEq('ok', $status, 'D11 multiselect default=[] → ok');

// 12. options 变 + multiselect default 全部合法(只是 reorder)→ ok
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b,c', 'a,b,c', 'multiselect', array('c','a','b')
);
assertEq('ok', $status, 'D12 multiselect 全部合法 reorder → ok');

// 13. old options_csv 为空字符串(new 非空)→ oldOptions 空数组
list($status, $bad, $old) = $probe->detectOptionsMigration(
    '', 'a,b', 'select', 'a'
);
assertEq(array(), $old, 'D13 old options_csv 空 → oldOptions=[]');
assertEq('ok', $status, 'D13 old 空 + new 非空 + 合法 → ok');

// 14. new options_csv 为空字符串(old 非空)→ select default 必不合法
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b', '', 'select', 'a'
);
assertEq('incompatible', $status, 'D14 new options_csv 空 + select default 非空 → incompatible');
assertEq(array('a'), $bad, 'D14 incompatible = [原 default]');

// 15. multiselect + default 是 string(非数组)→ 防御:当作单值
list($status, $bad, $old) = $probe->detectOptionsMigration(
    'a,b', 'a,b', 'multiselect', 'a'
);
assertEq('ok', $status, 'D15 multiselect default 是合法 string → ok');

// ============================================================
// _applyMigrationStrategy
// ============================================================
echo "\n=== _applyMigrationStrategy ===\n";

// helper:造一个 $normalized 数组
function mkNormalized($type, $default = null, $csv = 'a,b,c')
{
    return array(
        'entity_type'   => 'account',
        'field_key'     => 'svc',
        'label'         => '服务',
        'field_type'    => $type,
        'options_csv'   => $csv,
        'default_value' => $default,
        'is_required'   => false,
        'is_active'     => true,
        'sort_order'    => 0,
        'description'   => null,
    );
}

// A1. reject + select + 非法 default → 抛异常,消息包含 field_key + 非法值
assertThrowsMage(
    'A1 reject + select 抛异常',
    function () use ($probe) {
        $probe->applyMigrationStrategy(
            mkNormalized('select', 'a'),
            array('a'),
            'reject',
            'select'
        );
    },
    'svc'
);
assertThrowsMage(
    'A1 reject + select 异常消息含非法值',
    function () use ($probe) {
        $probe->applyMigrationStrategy(
            mkNormalized('select', 'a'),
            array('a'),
            'reject',
            'select'
        );
    },
    '"a"'
);

// A2. reject + multiselect → 抛异常,列出所有非法项
assertThrowsMage(
    'A2 reject + multiselect 抛异常,含所有非法项',
    function () use ($probe) {
        $probe->applyMigrationStrategy(
            mkNormalized('multiselect', array('a','x','y')),
            array('x','y'),
            'reject',
            'multiselect'
        );
    },
    '"x", "y"'
);

// A3. auto_clean + select → default 变 null
$result = $probe->applyMigrationStrategy(
    mkNormalized('select', 'a'),
    array('a'),
    'auto_clean',
    'select'
);
assertEq(null, $result['default_value'], 'A3 auto_clean + select → default_value = null');

// A4. auto_clean + multiselect(部分合法)→ 只保留合法项
$result = $probe->applyMigrationStrategy(
    mkNormalized('multiselect', array('a','x','b','y')),
    array('x','y'),
    'auto_clean',
    'multiselect'
);
assertEq(array('a','b'), $result['default_value'], 'A4 auto_clean + multiselect 部分合法 → 过滤后保留');

// A5. auto_clean + multiselect(全部非法)→ 变 []
$result = $probe->applyMigrationStrategy(
    mkNormalized('multiselect', array('x','y')),
    array('x','y'),
    'auto_clean',
    'multiselect'
);
assertEq(array(), $result['default_value'], 'A5 auto_clean + multiselect 全部非法 → []');

// A6. set_null + select → default 变 null
$result = $probe->applyMigrationStrategy(
    mkNormalized('select', 'a'),
    array('a'),
    'set_null',
    'select'
);
assertEq(null, $result['default_value'], 'A6 set_null + select → default_value = null');

// A7. set_null + multiselect → default 变 []
$result = $probe->applyMigrationStrategy(
    mkNormalized('multiselect', array('a','b','x')),
    array('x'),
    'set_null',
    'multiselect'
);
assertEq(array(), $result['default_value'], 'A7 set_null + multiselect → default_value = []');

// A8. 非法 strategy(typo)→ 退回 reject 抛异常
assertThrowsMage(
    'A8 非法 strategy 退回 reject',
    function () use ($probe) {
        $probe->applyMigrationStrategy(
            mkNormalized('select', 'a'),
            array('a'),
            'foobar',
            'select'
        );
    },
    'svc'
);

// A9. auto_clean + select + 无关字段保持不动
$in = mkNormalized('select', 'a', 'a,b,c');
$result = $probe->applyMigrationStrategy(
    $in,
    array('a'),
    'auto_clean',
    'select'
);
assertEq('account', $result['entity_type'], 'A9 无关字段 entity_type 保持');
assertEq('svc', $result['field_key'], 'A9 无关字段 field_key 保持');
assertEq('服务', $result['label'], 'A9 无关字段 label 保持');
assertEq(null, $result['default_value'], 'A9 default_value 被置 null');

// A10. auto_clean + select + is_required 等布尔字段保持
$in = mkNormalized('select', 'a', 'a,b,c');
$in['is_required'] = true;
$in['is_active']   = true;
$in['sort_order']  = 5;
$result = $probe->applyMigrationStrategy(
    $in, array('a'), 'auto_clean', 'select'
);
assertEq(true, $result['is_required'], 'A10 is_required 保持');
assertEq(5, $result['sort_order'], 'A10 sort_order 保持');

// ============================================================
// 综合:模拟 updateDef 调用流程(不通过完整 Magento)
// 仅验证 _detectOptionsMigration + _applyMigrationStrategy 协作正确
// ============================================================
echo "\n=== 集成:detect + apply 协作 ===\n";

// 综合 1:select 扩范围 + default 仍合法 → 整条 ok 路径
$oldCsv = 'a,b';
$newCsv = 'a,b,c,d';
$default = 'b';
list($status, $bad, $old) = $probe->detectOptionsMigration($oldCsv, $newCsv, 'select', $default);
assertEq('ok', $status, 'I1 select 扩范围 + default 仍合法 → ok');

// 综合 2:multiselect 缩范围 + auto_clean 策略(模拟 updateDef 走 auto_clean 路径)
$oldCsv = 'gold,silver,platinum';
$newCsv = 'gold,silver';
$default = array('gold','platinum','silver');
list($status, $bad, $old) = $probe->detectOptionsMigration($oldCsv, $newCsv, 'multiselect', $default);
assertEq('incompatible', $status, 'I2 multiselect 缩范围 → incompatible');
assertEq(array('platinum'), $bad, 'I2 incompatible = [platinum]');
$result = $probe->applyMigrationStrategy(
    mkNormalized('multiselect', $default, $newCsv), $bad, 'auto_clean', 'multiselect'
);
assertEq(array('gold','silver'), $result['default_value'], 'I2 auto_clean → 过滤保留 [gold,silver]');

// 综合 3:multiselect 全非法 + set_null 策略
$oldCsv = 'x,y';
$newCsv = 'a,b';
$default = array('x','y');
list($status, $bad, $old) = $probe->detectOptionsMigration($oldCsv, $newCsv, 'multiselect', $default);
assertEq('incompatible', $status, 'I3 multiselect 全非法 → incompatible');
$result = $probe->applyMigrationStrategy(
    mkNormalized('multiselect', $default, $newCsv), $bad, 'set_null', 'multiselect'
);
assertEq(array(), $result['default_value'], 'I3 set_null + multiselect → []');

// 综合 4:text 类型 + options 改(无关,直接 ok,不抛)
$oldCsv = 'a,b,c';
$newCsv = 'x,y,z';
$default = '任何值';
list($status, $bad, $old) = $probe->detectOptionsMigration($oldCsv, $newCsv, 'text', $default);
assertEq('ok', $status, 'I4 text 类型 + options 改 + 任意 default → ok(无影响)');

// 综合 5:select 缩范围 + reject 抛异常(用 try/catch 验证可达)
$oldCsv = 'a,b,c';
$newCsv = 'd,e,f';
$default = 'a';
list($status, $bad, $old) = $probe->detectOptionsMigration($oldCsv, $newCsv, 'select', $default);
assertEq('incompatible', $status, 'I5 detect 路径:select 缩范围 → incompatible');
$rejected = false;
try {
    $probe->applyMigrationStrategy(
        mkNormalized('select', $default, $newCsv), $bad, 'reject', 'select'
    );
} catch (Mage_Core_Exception $e) {
    $rejected = true;
}
assertEq(true, $rejected, 'I5 reject + select 缩范围 → 抛 Mage_Core_Exception');

echo PHP_EOL;
echo "Total: {$assertions} assertions, {$failures} failures" . PHP_EOL;
exit($failures > 0 ? 1 : 0);
