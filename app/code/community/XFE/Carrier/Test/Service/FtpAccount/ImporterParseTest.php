<?php

/**
 * FtpAccountImporterParseTest
 *
 * 验证 XFE_Carrier_Model_Service_FtpAccount_Importer 的纯解析逻辑（_toInt），
 * 不依赖 Magento 环境。CSV 上传与 DB 交互不在本测试范围。
 *
 * 从 CLI 运行：
 *   php app/code/community/XFE/Carrier/Test/Service/FtpAccount/ImporterParseTest.php
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

// 通过子类暴露受保护的 _toInt() 与 _extractCustomFieldsJson() 以便独立测试。
class FtpAccountImporterToIntProbe extends XFE_Carrier_Model_Service_FtpAccount_Importer
{
    public function toInt(array $row, $key, $default)
    {
        return $this->_toInt($row, $key, $default);
    }
    public function extractCustomFieldsJson(array $row)
    {
        return $this->_extractCustomFieldsJson($row);
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

$probe = new FtpAccountImporterToIntProbe();

// 1. 正常数值
assertEq(22, $probe->toInt(array('port' => '22'), 'port', 21), '合法数值正常转换');
// 2. 空字符串 → 默认值
assertEq(21, $probe->toInt(array('port' => ''), 'port', 21), '空字符串回退默认值');
// 3. 缺失键 → 默认值
assertEq(1, $probe->toInt(array(), 'status', 1), '缺失键回退默认值');
// 4. 非数值字符串 → 默认值
assertEq(0, $probe->toInt(array('status' => 'abc'), 'status', 0), '非数值字符串回退默认值');
// 5. 带前导/尾随空格的数值
assertEq(5, $probe->toInt(array('sort_order' => ' 5 '), 'sort_order', 0), '带空格的数值正常转换');

// 6. _extractCustomFieldsJson: 缺失列 → null
assertEq(null, $probe->extractCustomFieldsJson(array('carrier_code' => 'sf')),
    '缺 custom_fields_json 列 → null');

// 7. _extractCustomFieldsJson: 空字符串 → null
assertEq(null, $probe->extractCustomFieldsJson(array('custom_fields_json' => '')),
    '空字符串 → null');

// 8. _extractCustomFieldsJson: 合法 multiselect JSON 节点(自由模式)
$msJson = '{"supported_dirs":{"label":"支持目录","type":"multiselect",'
    . '"value":["/in","/out"],"options":[]}}';
$out = $probe->extractCustomFieldsJson(array('custom_fields_json' => $msJson));
assertEq(true, is_string($out), 'multiselect JSON 返回 string');
$decoded = json_decode($out, true);
assertEq(true, is_array($decoded), 'multiselect JSON 解析回 assoc');
assertEq('multiselect', $decoded['supported_dirs']['type'], 'multiselect type 保留');
assertEq(array('/in', '/out'), $decoded['supported_dirs']['value'],
    'multiselect value 数组保留');

// 9. _extractCustomFieldsJson: 固定 options 的 multiselect JSON
$msFixedJson = '{"modes":{"label":"传输模式","type":"multiselect",'
    . '"value":["binary"],'
    . '"options":["binary","ascii","auto"]}}';
$out2 = $probe->extractCustomFieldsJson(array('custom_fields_json' => $msFixedJson));
$decoded2 = json_decode($out2, true);
assertEq(array('binary', 'ascii', 'auto'),
    $decoded2['modes']['options'],
    'multiselect 固定 options 保留');

// 10. _extractCustomFieldsJson: 非法 JSON → null(不污染 DB)
assertEq(null, $probe->extractCustomFieldsJson(array('custom_fields_json' => 'not json')),
    '非法 JSON → null');

// 11. _extractCustomFieldsJson: list 形态([])→ null
assertEq(null, $probe->extractCustomFieldsJson(array('custom_fields_json' => '[]')),
    'list 形态 → null');

echo PHP_EOL . ($failed ? "FAILED: {$failed} assertion(s)" : 'ALL PASS') . PHP_EOL;
exit($failed ? 1 : 0);
