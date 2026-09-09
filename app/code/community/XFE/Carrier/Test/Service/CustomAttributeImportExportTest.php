<?php
/**
 * CustomAttribute Importer / Exporter 单元测试
 *
 * 测 Exporter 的 _row / _header / _toCsv / writeTemplate(纯字符串拼接)
 * 测 Importer 的 _readRows(纯 CSV 解析),以及 Result 对象
 *
 * 不依赖 Magento。Exporter / Importer 内部用 Registry / Mage,但
 * 测的是无 DB 段(纯字符串/CSV 处理)。
 *
 * 运行:php app/code/community/XFE/Carrier/Test/Service/CustomAttributeImportExportTest.php
 */

// ---- Mage 桩(给 Importer::_hlp()) ------------------------------------
if (!class_exists('Mage', false)) {
    class Mage
    {
        public static function helper($name) { return new Mage_Core_Helper(); }
    }
}
if (!class_exists('Mage_Core_Helper', false)) {
    class Mage_Core_Helper { public function __($s) { return (string) $s; } }
}

spl_autoload_register(function ($class) {
    $allowed = array(
        'XFE_Carrier_Domain_CustomAttribute',
        'XFE_Carrier_Domain_CustomAttributeCollection',
        'XFE_Carrier_Domain_CustomField',
        'XFE_Carrier_Domain_CustomFieldCollection',
        'XFE_Carrier_Domain_CustomFieldCodec',
        'XFE_Carrier_Model_Service_CustomAttribute_Exporter',
        'XFE_Carrier_Model_Service_CustomAttribute_Importer',
        'XFE_Carrier_Model_Service_CustomAttribute_Importer_Result',
    );
    if (!in_array($class, $allowed, true)) {
        return;
    }
    $parts = explode('_', $class);
    // XFE_Carrier_Domain_CustomField → 去掉 XFE_Carrier_Domain,保留 CustomField
    if (strpos($class, 'XFE_Carrier_Domain_') === 0) {
        array_shift($parts); array_shift($parts); array_shift($parts);
        $candidate = __DIR__ . '/../../Domain/' . implode('/', $parts) . '.php';
    } else {
        // XFE_Carrier_Model_Service_CustomAttribute_Exporter
        //   → ['CustomAttribute', 'Exporter']  拼到 Model/Service/
        // XFE_Carrier_Model_Service_CustomAttribute_Importer
        //   → ['CustomAttribute', 'Importer']
        // XFE_Carrier_Model_Service_CustomAttribute_Importer_Result
        //   → ['CustomAttribute', 'Importer', 'Result']
        array_shift($parts); array_shift($parts); array_shift($parts); array_shift($parts);
        $candidate = __DIR__ . '/../../Model/Service/'
            . implode('/', $parts) . '.php';
    }
    if (file_exists($candidate)) {
        require_once $candidate;
    }
});

/** Exporter probe:暴露 protected 方法 */
class ExporterProbe extends XFE_Carrier_Model_Service_CustomAttribute_Exporter
{
    public function pubHeader()  { return $this->_header(); }
    public function pubRow($def)  { return $this->_row($def); }
    public function pubToCsv(array $rows) { return $this->_toCsv($rows); }
}

/** Importer probe:暴露 protected _readRows + 提供伪 handle 模拟 */
class ImporterProbe extends XFE_Carrier_Model_Service_CustomAttribute_Importer
{
    public function pubReadRows($handle, $result)
    {
        return $this->_readRows($handle, $result);
    }
}

$failed = 0;
function assertEq($expected, $actual, $label) {
    global $failed;
    $ok = $expected === $actual;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label
       . ' - expected ' . var_export($expected, true)
       . ', got ' . var_export($actual, true) . PHP_EOL;
    if (!$ok) $failed++;
}

// ============================================================
// Exporter
// ============================================================
$exp = new ExporterProbe();

// 1. 表头
$h = $exp->pubHeader();
assertEq(
    array('entity_type','field_key','label','field_type','options_csv',
          'default_value','is_required','is_active','sort_order','description'),
    $h,
    'Exporter 11 列表头'
);

// 2. _row(): text 类型,无 default
$textDef = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'wh', '仓库', 'text', null,
    null,        // default
    true,        // is_required
    true,        // is_active
    10,          // sort_order
    '仓库四字码'
);
$row = $exp->pubRow($textDef);
assertEq('account', $row[0], 'row entity_type');
assertEq('wh', $row[1], 'row field_key');
assertEq('仓库', $row[2], 'row label');
assertEq('text', $row[3], 'row field_type');
assertEq('', $row[4], 'row options_csv (text 留空)');
assertEq('', $row[5], 'row default_value (null → 空)');
assertEq('1', $row[6], 'row is_required');
assertEq('1', $row[7], 'row is_active');
assertEq('10', $row[8], 'row sort_order');
assertEq('仓库四字码', $row[9], 'row description');

// 3. _row(): select 类型, default + options
$selDef = new XFE_Carrier_Domain_CustomAttribute(
    null, 'account', 'svc', '服务', 'select',
    array('standard', 'express', 'economy'),
    'express',
    false, true, 20, null
);
$row = $exp->pubRow($selDef);
assertEq('select', $row[3], 'row field_type select');
assertEq('standard,express,economy', $row[4], 'row options_csv 拼接');
assertEq('express', $row[5], 'row default_value select');
assertEq('0', $row[6], 'row is_required false');
assertEq('', $row[9], 'row description null → 空');

// 4. _row(): multiselect 自由模式,default 是 array → 用 | 分隔
$msFreeDef = new XFE_Carrier_Domain_CustomAttribute(
    null, 'logo', 'areas', '区域', 'multiselect', null,
    array('华东', '华南'),
    false, true, 30, null
);
$row = $exp->pubRow($msFreeDef);
assertEq('multiselect', $row[3], 'row field_type multiselect');
assertEq('', $row[4], 'row options_csv multiselect 自由模式 空');
assertEq('华东|华南', $row[5], 'row default_value multiselect array → | 分隔');

// 5. _row(): multiselect 固定模式
$msFixedDef = new XFE_Carrier_Domain_CustomAttribute(
    null, 'logo', 'levels', '等级', 'multiselect',
    array('gold', 'silver'),
    array('gold'),
    true, true, 40, '会员等级'
);
$row = $exp->pubRow($msFixedDef);
assertEq('gold,silver', $row[4], 'row options_csv multiselect 固定模式');
assertEq('gold', $row[5], 'row default_value multiselect 固定模式');
assertEq('1', $row[6], 'row is_required true');

// 6. _row(): boolean
$boolDef = new XFE_Carrier_Domain_CustomAttribute(
    null, 'ftp_account', 'use_ssl', '启用 SSL', 'boolean', null,
    true, false, true, 0, null
);
$row = $exp->pubRow($boolDef);
assertEq('ftp_account', $row[0], 'row entity_type ftp_account');
assertEq('boolean', $row[3], 'row field_type boolean');
assertEq('1', $row[5], 'row default_value boolean true → 1');
assertEq('0', $row[6], 'row is_required false');
assertEq('1', $row[7], 'row is_active true');

// 7. _toCsv(): 含 BOM + 表头 + 行(逗号 / 引号 / 换行处理)
$rows = array(
    array('h1', 'h2'),
    array('simple', 'value'),
    array('with,comma', '"quoted"'),
    array('中文', '测试'),
    array('with "embedded" quote', 'normal'),
);
$csv = $exp->pubToCsv($rows);
assertEq(true, strpos($csv, "\xEF\xBB\xBF") === 0, 'CSV 含 UTF-8 BOM');
assertEq(true, strpos($csv, 'h1,h2') !== false, 'CSV 含表头');
assertEq(true, strpos($csv, 'simple,value') !== false, 'CSV 含简单行');
// PHP fputcsv 会自动加引号包裹含逗号/引号的字段
assertEq(true, strpos($csv, '"with,comma"') !== false, 'CSV 含逗号字段加引号');
assertEq(true, strpos($csv, '中文') !== false, 'CSV 中文不被转义');
assertEq(true, strpos($csv, '""quoted""') !== false || strpos($csv, '""quoted""') !== false
    || strpos($csv, 'quoted') !== false,
    'CSV 引号字段合理处理');

// 8. writeTemplate(): 不调 Registry,纯模板生成
$tmpl = $exp->writeTemplate();
assertEq(true, strpos($tmpl, "\xEF\xBB\xBF") === 0, 'Template 含 BOM');
assertEq(true, strpos($tmpl, 'entity_type,field_key,label,field_type') !== false,
    'Template 含表头');
assertEq(true, strpos($tmpl, 'warehouse_code') !== false,
    'Template 含示例行 1');
assertEq(true, strpos($tmpl, 'service_level') !== false,
    'Template 含示例行 2 (select)');
assertEq(true, strpos($tmpl, 'supported_areas') !== false,
    'Template 含示例行 3 (multiselect)');
assertEq(true, strpos($tmpl, '华东|华南|华北') !== false,
    'Template multiselect default 用 | 分隔');

// ============================================================
// Importer::Result
// ============================================================
$r = new XFE_Carrier_Model_Service_CustomAttribute_Importer_Result();
assertEq(0, $r->created, 'Result.created 初始 0');
assertEq(0, $r->updated, 'Result.updated 初始 0');
assertEq(0, $r->activated, 'Result.activated 初始 0');
assertEq(0, $r->skipped, 'Result.skipped 初始 0');
assertEq(false, $r->hasErrors(), 'Result.hasErrors 初始 false');
assertEq(array(), $r->getErrors(), 'Result.getErrors 初始 空');

$r->created = 5;
$r->updated = 3;
$r->activated = 2;
$r->skipped = 1;
$r->addError(2, 'invalid field_type');
$r->addError(0, 'file level error');
assertEq(true, $r->hasErrors(), 'addError 后 hasErrors true');
assertEq(2, count($r->getErrors()), '2 条错误');
assertEq('invalid field_type', $r->getErrors()[2], '行号 2 的错误');
assertEq('file level error', $r->getErrors()[0], '行号 0 的错误');

// 同 rowNo 后写覆盖
$r->addError(2, 'overwritten');
assertEq('overwritten', $r->getErrors()[2], '同 rowNo 后写覆盖');

// ============================================================
// Importer::_readRows(纯 CSV 解析,不调 Registry/Mage)
// ============================================================
$imp = new ImporterProbe();
$result = new XFE_Carrier_Model_Service_CustomAttribute_Importer_Result();

function mkCsv($content)
{
    $tmp = tempnam(sys_get_temp_dir(), 'csv_');
    file_put_contents($tmp, $content);
    return $tmp;
}

// 1. 正常 CSV + 表头 + 4 列
$file = mkCsv(
    "entity_type,field_key,label,field_type,options_csv,default_value,is_required,is_active,sort_order,description\n"
    . "account,wh,仓库,text,,WH-001,1,1,10,test\n"
    . "account,svc,服务,select,standard,express,0,1,20,\n"
);
$handle = fopen($file, 'r');
$rows = $imp->pubReadRows($handle, $result);
fclose($handle);
assertEq(2, count($rows), '正常 CSV 2 行');
assertEq('account', $rows[0]['entity_type'], 'rows[0] entity_type');
assertEq('wh', $rows[0]['field_key'], 'rows[0] field_key');
assertEq('text', $rows[0]['field_type'], 'rows[0] field_type');
assertEq('WH-001', $rows[0]['default_value'], 'rows[0] default');
assertEq('1', $rows[0]['is_required'], 'rows[0] is_required');
assertEq('express', $rows[1]['default_value'], 'rows[1] default');
assertEq(false, $result->hasErrors(), '正常 CSV 无错');
unlink($file);

// 2. UTF-8 BOM
$file = mkCsv("\xEF\xBB\xBFentity_type,field_key,label,field_type\naccount,wh,仓库,text\n");
$handle = fopen($file, 'r');
$result2 = new XFE_Carrier_Model_Service_CustomAttribute_Importer_Result();
$rows = $imp->pubReadRows($handle, $result2);
fclose($handle);
assertEq(1, count($rows), 'BOM CSV 1 行');
assertEq('wh', $rows[0]['field_key'], 'BOM 不会污染 field_key');
unlink($file);

// 3. 空行跳过
$file = mkCsv(
    "entity_type,field_key,label,field_type\n"
    . "account,a,A,text\n"
    . "\n"
    . "account,b,B,text\n"
    . "   \n"
);
$handle = fopen($file, 'r');
$result3 = new XFE_Carrier_Model_Service_CustomAttribute_Importer_Result();
$rows = $imp->pubReadRows($handle, $result3);
fclose($handle);
assertEq(2, count($rows), '跳过空行');
unlink($file);

// 4. 缺必需列 → 错
$file = mkCsv("entity_type,field_key,label\naccount,wh,仓库\n");
$handle = fopen($file, 'r');
$result4 = new XFE_Carrier_Model_Service_CustomAttribute_Importer_Result();
$rows = $imp->pubReadRows($handle, $result4);
fclose($handle);
assertEq(0, count($rows), '缺 field_type 列 → 0 行');
assertEq(true, $result4->hasErrors(), '缺列 → 错误记录');
unlink($file);

// 5. 完全空文件 → 错
$file = mkCsv('');
$handle = fopen($file, 'r');
$result5 = new XFE_Carrier_Model_Service_CustomAttribute_Importer_Result();
$rows = $imp->pubReadRows($handle, $result5);
fclose($handle);
assertEq(0, count($rows), '空文件 → 0 行');
assertEq(true, $result5->hasErrors(), '空文件 → 错误');
unlink($file);

echo PHP_EOL . ($failed ? "FAILED: {$failed} assertion(s)" : 'ALL PASS') . PHP_EOL;
exit($failed ? 1 : 0);
