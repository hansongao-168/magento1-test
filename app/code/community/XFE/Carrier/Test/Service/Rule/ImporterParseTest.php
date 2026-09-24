<?php

/**
 * RuleImporterParseTest
 *
 * 验证 XFE_Carrier_Model_Service_Rule_Importer 的纯解析逻辑（_toInt），
 * 不依赖 Magento 环境。CSV 上传与 DB 交互不在本测试范围。
 *
 * 从 CLI 运行：
 *   php app/code/community/XFE/Carrier/Test/Service/Rule/ImporterParseTest.php
 *
 * 退出码 0 表示全部通过，1 表示有失败。
 */

spl_autoload_register(function ($class) {
    if (strpos($class, 'XFE_Carrier_Model_') !== 0) {
        return;
    }
    $parts = explode('_', $class);
    array_shift($parts); // XFE
    array_shift($parts); // Carrier
    array_shift($parts); // Model
    $path = implode('/', $parts) . '.php';
    $candidate = __DIR__ . '/../../../Model/' . $path;
    if (file_exists($candidate)) {
        require_once $candidate;
    }
});

// 通过子类暴露受保护的 _toInt() 以便独立测试。
class ImporterToIntProbe extends XFE_Carrier_Model_Service_Rule_Importer
{
    public function toInt(array $row, $key, $default)
    {
        return $this->_toInt($row, $key, $default);
    }
}

$failed = 0;

function assertEq($expected, $actual, $label) {
    global $failed;
    $ok = $expected === $actual;
    $msg = ($ok ? '[PASS] ' : '[FAIL] ') . $label
         . ' - expected ' . var_export($expected, true)
         . ', got ' . var_export($actual, true);
    echo $msg . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

$probe = new ImporterToIntProbe();

// 1. 正常数值
assertEq(1, $probe->toInt(array('status' => '1'), 'status', 1), '合法数值正常转换');
// 2. 空字符串 → 默认值
assertEq(5, $probe->toInt(array('priority' => ''), 'priority', 5), '空字符串回退默认值');
// 3. 缺失键 → 默认值
assertEq(0, $probe->toInt(array(), 'sort_order', 0), '缺失键回退默认值');
// 4. 非数值字符串 → 默认值
assertEq(2, $probe->toInt(array('status' => 'abc'), 'status', 2), '非数值字符串回退默认值');
// 5. 带前导/尾随空格的数值
assertEq(3, $probe->toInt(array('status' => ' 3 '), 'status', 0), '带空格的数值正常转换');

echo PHP_EOL . ($failed ? "FAILED: {$failed} assertion(s)" : 'ALL PASS') . PHP_EOL;
exit($failed ? 1 : 0);
