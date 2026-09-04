<?php

/**
 * AccountExporterCsvTest
 *
 * 验证 XFE_Carrier_Model_Service_Account_Exporter 的 CSV 序列化核心逻辑
 * (不含 DB 读取),重点是 UTF-8 BOM、列头、多列字段、含逗号/双引号字段、
 * 以及 custom_fields_json(含 multiselect) round-trip。
 *
 * 从 CLI 运行:
 *   php app/code/community/XFE/Carrier/Test/Service/Account/ExporterCsvTest.php
 *
 * 退出码 0 表示全部通过,1 表示有失败。
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
class AccountExporterCsvProbe extends XFE_Carrier_Model_Service_Account_Exporter
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

$probe = new AccountExporterCsvProbe();

// 1. BOM + 列头 + 一行数据
$csv = $probe->csv(array(
    array('carrier_code', 'account_name', 'account_no', 'api_key'),
    array('sf_test', '示例账号', 'ACCT0001', 'your_api_key'),
));
assertTrue(strpos($csv, "\xEF\xBB\xBF") === 0, '输出以 UTF-8 BOM 开头');
assertTrue(strpos($csv, 'carrier_code,account_name,account_no') !== false, '包含账号列头');
assertTrue(strpos($csv, 'ACCT0001') !== false, '账号编号正常写入');

// 2. 含逗号 / 双引号字段(round-trip 可还原)
$note = '备注,含"双引号"与逗号';
$csv2 = $probe->csv(array(
    array('note'),
    array($note),
));
$readRow = function ($h) {
    if (PHP_VERSION_ID >= 80400) {
        return fgetcsv($h, 0, ',', '"', '');
    }
    return fgetcsv($h);
};
$h2 = fopen('php://temp', 'r+');
fwrite($h2, $csv2);
rewind($h2);
$h2Header = $readRow($h2);
$h2Row = $readRow($h2);
fclose($h2);
assertEq('note', preg_replace('/^\xEF\xBB\xBF/', '', $h2Header[0]), 'round-trip 表头还原');
assertEq($note, $h2Row[0], '含逗号与双引号的 note round-trip 后可还原');

// 3. 空数组 → 仅 BOM
$csv3 = $probe->csv(array());
assertEq("\xEF\xBB\xBF", $csv3, '空数组仅输出 BOM');

// 4. 常规多列数值
$csv4 = $probe->csv(array(
    array('status', 'sort_order'),
    array('1', '10'),
));
assertTrue(strpos($csv4, '1,10') !== false, '多列数值行正确');

// 5. custom_fields_json 含 multiselect 节点(自由标签模式)
$msJson = '{"supported_areas":{"label":"支持区域","type":"multiselect",'
    . '"value":["华东","华南","华北"],"options":[]}}';
$csv5 = $probe->csv(array(
    array('account_name', 'custom_fields_json'),
    array('多选示例', $msJson),
));
// CSV 会把 JSON 内的双引号转义为 "",因此断言内部子串(不带双引号)
assertTrue(strpos($csv5, '多选示例') !== false, 'multiselect 自由标签行账号名写入');
assertTrue(strpos($csv5, 'supported_areas') !== false, 'multiselect 自由标签 key 写入');
assertTrue(strpos($csv5, '\\u534e\\u4e1c') !== false || strpos($csv5, '华东') !== false,
    'multiselect 自由标签 value 数组项被正确转义后写入');

// 6. custom_fields_json 含 multiselect 节点(固定 options 模式)
$msFixedJson = '{"levels":{"label":"服务等级","type":"multiselect",'
    . '"value":["express","economy"],'
    . '"options":["standard","express","economy"]}}';
$csv6 = $probe->csv(array(
    array('account_name', 'custom_fields_json'),
    array('固定多选', $msFixedJson),
));
assertTrue(strpos($csv6, '固定多选') !== false, 'multiselect 固定 options 行账号名写入');
assertTrue(strpos($csv6, 'levels') !== false, 'multiselect 固定 options key 写入');
assertTrue(strpos($csv6, 'standard') !== false, 'multiselect 固定 options 候选项写入');
// round-trip 后应可解析回 multiselect 节点
$h6 = fopen('php://temp', 'r+');
fwrite($h6, $csv6);
rewind($h6);
$h6Header = $readRow($h6);
$h6Row = $readRow($h6);
fclose($h6);
$decoded6 = json_decode($h6Row[1], true);
assertEq(true, is_array($decoded6), 'multiselect 固定 options round-trip JSON 解析');
assertEq(array('standard', 'express', 'economy'),
    $decoded6['levels']['options'] ?? null,
    'multiselect 固定 options round-trip options 保留');

// 7. multiselect JSON 整体 round-trip
$msMixedJson = '{"areas":{"label":"区域","type":"multiselect",'
    . '"value":["华东","华南"],'
    . '"options":[]},'
    . '"wh":{"label":"仓库","type":"text","value":"WH-1"}}';
$csv7 = $probe->csv(array(
    array('account_name', 'custom_fields_json'),
    array('mixed', $msMixedJson),
));
$h7 = fopen('php://temp', 'r+');
fwrite($h7, $csv7);
rewind($h7);
$h7Header = $readRow($h7);
$h7Row = $readRow($h7);
fclose($h7);
assertEq('account_name,custom_fields_json',
    preg_replace('/^\xEF\xBB\xBF/', '', $h7Header[0] . ',' . $h7Header[1]),
    'round-trip 表头还原');
assertEq('mixed', $h7Row[0], 'round-trip 账号名');
$decoded = json_decode($h7Row[1], true);
assertEq(true, is_array($decoded), 'round-trip JSON 解析为 assoc 数组');
assertEq('multiselect', $decoded['areas']['type'], 'round-trip multiselect type 保留');
assertEq(array('华东', '华南'), $decoded['areas']['value'], 'round-trip multiselect value 保留');
assertEq('WH-1', $decoded['wh']['value'], 'round-trip text value 保留');

echo PHP_EOL . ($failed ? "FAILED: {$failed} assertion(s)" : 'ALL PASS') . PHP_EOL;
exit($failed ? 1 : 0);
