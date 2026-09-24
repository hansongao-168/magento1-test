<?php

/**
 * FtpAccountExporterCsvTest
 *
 * 验证 XFE_Carrier_Model_Service_FtpAccount_Exporter 的 CSV 序列化核心逻辑
 * （不含 DB 读取），重点是 UTF-8 BOM、列头、多列字段。
 *
 * 从 CLI 运行：
 *   php app/code/community/XFE/Carrier/Test/Service/FtpAccount/ExporterCsvTest.php
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
class FtpAccountExporterCsvProbe extends XFE_Carrier_Model_Service_FtpAccount_Exporter
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

$probe = new FtpAccountExporterCsvProbe();

// 1. BOM + 列头 + 一行数据
$csv = $probe->csv(array(
    array('carrier_code', 'account_name', 'protocol', 'host', 'port'),
    array('sf_test', '示例FTP', 'sftp', 'sftp.example.com', '22'),
));
assertTrue(strpos($csv, "\xEF\xBB\xBF") === 0, '输出以 UTF-8 BOM 开头');
assertTrue(strpos($csv, 'carrier_code,account_name,protocol') !== false, '包含 FTP账号列头');
assertTrue(strpos($csv, 'sftp.example.com') !== false, '主机内容正常写入');

// 2. 含逗号字段（如 remote_path）应被正确引用，round-trip 后可还原
$path = '/upload,dir with "quote"';
$csv2 = $probe->csv(array(
    array('remote_path'),
    array($path),
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
$h2Header = $readRow($h2);   // 跳过表头
$h2Row = $readRow($h2);      // 数据行
fclose($h2);
assertEq('remote_path', preg_replace('/^\xEF\xBB\xBF/', '', $h2Header[0]), 'round-trip 表头还原');
assertEq($path, $h2Row[0], '含逗号与双引号的远程路径 round-trip 后可还原');

// 3. 空数组 → 仅 BOM
$csv3 = $probe->csv(array());
assertEq("\xEF\xBB\xBF", $csv3, '空数组仅输出 BOM');

// 4. 常规多列数值
$csv4 = $probe->csv(array(
    array('port', 'status', 'sort_order'),
    array('21', '1', '10'),
));
assertTrue(strpos($csv4, '21,1,10') !== false, '多列数值行正确');

// 5. custom_fields_json 含 multiselect 节点(自由标签模式)
$msJson = '{"supported_dirs":{"label":"支持目录","type":"multiselect",'
    . '"value":["/in","/out","/archive"],"options":[]}}';
$csv5 = $probe->csv(array(
    array('account_name', 'custom_fields_json'),
    array('FTP多选', $msJson),
));
assertTrue(strpos($csv5, 'FTP多选') !== false, 'multiselect 自由标签行账号名写入');
assertTrue(strpos($csv5, 'supported_dirs') !== false, 'multiselect 自由标签 key 写入');
assertTrue(strpos($csv5, '/archive') !== false, 'multiselect 自由标签 value 项写入');

// 6. custom_fields_json 含 multiselect 节点(固定 options 模式)round-trip
$msFixedJson = '{"modes":{"label":"传输模式","type":"multiselect",'
    . '"value":["binary","ascii"],'
    . '"options":["binary","ascii","auto"]}}';
$csv6 = $probe->csv(array(
    array('account_name', 'custom_fields_json'),
    array('FTP固定多选', $msFixedJson),
));
assertTrue(strpos($csv6, 'FTP固定多选') !== false, 'multiselect 固定 options 行账号名写入');
$readRow = function ($h) {
    if (PHP_VERSION_ID >= 80400) {
        return fgetcsv($h, 0, ',', '"', '');
    }
    return fgetcsv($h);
};
$h6 = fopen('php://temp', 'r+');
fwrite($h6, $csv6);
rewind($h6);
$readRow($h6); // skip header
$row6 = $readRow($h6);
fclose($h6);
$decoded6 = json_decode($row6[1], true);
assertEq(true, is_array($decoded6), 'multiselect 固定 options round-trip JSON 解析');
assertEq(array('binary', 'ascii', 'auto'),
    $decoded6['modes']['options'] ?? null,
    'multiselect 固定 options round-trip options 保留');
assertEq(array('binary', 'ascii'),
    $decoded6['modes']['value'] ?? null,
    'multiselect 固定 options round-trip value 保留');

echo PHP_EOL . ($failed ? "FAILED: {$failed} assertion(s)" : 'ALL PASS') . PHP_EOL;
exit($failed ? 1 : 0);
