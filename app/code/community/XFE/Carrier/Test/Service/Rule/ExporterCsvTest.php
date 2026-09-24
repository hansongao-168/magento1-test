<?php

/**
 * RuleExporterCsvTest
 *
 * 验证 XFE_Carrier_Model_Service_Rule_Exporter 的 CSV 序列化核心逻辑
 * （不含 DB 读取），重点是 UTF-8 BOM、列头、JSON 字段转义。
 *
 * 从 CLI 运行：
 *   php app/code/community/XFE/Carrier/Test/Service/Rule/ExporterCsvTest.php
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

// 通过子类暴露受保护的 _toCsv() 以便独立测试。
class ExporterCsvProbe extends XFE_Carrier_Model_Service_Rule_Exporter
{
    public function csv(array $rows)
    {
        return $this->_toCsv($rows);
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

function assertTrue($cond, $label) {
    global $failed;
    $ok = (bool)$cond;
    $msg = ($ok ? '[PASS] ' : '[FAIL] ') . $label;
    echo $msg . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

$probe = new ExporterCsvProbe();

// 1. BOM + 列头 + 一行数据
$csv = $probe->csv(array(
    array('carrier_code', 'name', 'conditions_json'),
    array('sf_test', '美国件', '[{"aggregator":"all"}]'),
));
assertTrue(strpos($csv, "\xEF\xBB\xBF") === 0, '输出以 UTF-8 BOM 开头');
assertTrue(strpos($csv, 'carrier_code,name,conditions_json') !== false, '包含列头');
assertTrue(strpos($csv, '美国件') !== false, '中文内容正常写入');

// 2. JSON 字段含逗号 / 双引号时应被 CSV 正确引用：
//    用 fgetcsv 反向解析（round-trip）验证，避免手写转义字符串。
$json = '{"operator":"==","value":"US, CA"}';
$csv2 = $probe->csv(array(
    array('conditions_json'),
    array($json),
));
// 测试内 fgetcsv 同样需兼容 PHP 版本差异（PHP<8.4 传空 escape 会失败）。
$readRow = function ($h) {
    if (PHP_VERSION_ID >= 80400) {
        return fgetcsv($h, 0, ',', '"', '');
    }
    return fgetcsv($h);
};
$h2 = fopen('php://temp', 'r+');
fwrite($h2, $csv2);
rewind($h2);
$h2Header = $readRow($h2);   // 跳过表头
$h2Row = $readRow($h2);      // 数据行
fclose($h2);
// 导出带 BOM，因此第一行首字段含 BOM 前缀；去除 BOM 后应还原。
assertEq('conditions_json', preg_replace('/^\xEF\xBB\xBF/', '', $h2Header[0]), 'round-trip 表头还原');
assertEq($json, $h2Row[0], '含逗号与双引号的 JSON round-trip 后可还原');

// 3. 空数组 → 仅 BOM
$csv3 = $probe->csv(array());
assertEq("\xEF\xBB\xBF", $csv3, '空数组仅输出 BOM');

// 4. 常规多列数值
$csv4 = $probe->csv(array(
    array('status', 'priority'),
    array('1', '10'),
));
assertTrue(strpos($csv4, '1,10') !== false, '多列数值行正确');

echo PHP_EOL . ($failed ? "FAILED: {$failed} assertion(s)" : 'ALL PASS') . PHP_EOL;
exit($failed ? 1 : 0);
