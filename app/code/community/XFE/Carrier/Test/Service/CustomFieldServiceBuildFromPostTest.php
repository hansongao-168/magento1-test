<?php
/**
 * CustomFieldServiceAbstract::buildFromPost 单元测试
 *
 * Service 内部实际工作量大的是 _buildFromPost() (从 POST 段构建集合),
 * 它是 protected,且完全自包含(不需要 Mage/DB/Event)。通过匿名子类暴露
 * 后,可以独立测试,不需要 bootstrap Magento。
 *
 * 覆盖:
 *   - 正常形态 → 集合
 *   - 缺/空 custom_fields 段 → 空集合
 *   - select 类型 options 解析
 *   - 非法 spec 静默跳过
 *
 * 运行:php app/code/community/XFE/Carrier/Test/Service/CustomFieldServiceBuildFromPostTest.php
 */

spl_autoload_register(function ($class) {
    $prefixes = array('XFE_Carrier_Domain_', 'XFE_Carrier_Model_Service_Account_');
    $matched = false;
    foreach ($prefixes as $p) {
        if (strpos($class, $p) === 0) { $matched = true; break; }
    }
    if (!$matched) return;

    $parts = explode('_', $class);
    if (strpos($class, 'XFE_Carrier_Domain_') === 0) {
        // XFE_Carrier_Domain_CustomField → ['CustomField']
        array_shift($parts); array_shift($parts); array_shift($parts);
        $path = implode('/', $parts) . '.php';
        $candidate = __DIR__ . '/../../Domain/' . $path;
    } else {
        // XFE_Carrier_Model_Service_Account_CustomFieldServiceAbstract
        //   → ['Account', 'CustomFieldServiceAbstract']
        // XFE_Carrier_Model_Service_Account_CustomFieldService
        //   → ['Account', 'CustomFieldService']
        array_shift($parts); array_shift($parts); array_shift($parts); array_shift($parts);
        $path = implode('/', $parts) . '.php';
        $candidate = __DIR__ . '/../../Model/Service/' . $path;
    }
    if (file_exists($candidate)) {
        require_once $candidate;
    }
});

class BuildFromPostProbe extends XFE_Carrier_Model_Service_Account_CustomFieldServiceAbstract
{
    protected function _getModelAlias()   { return 'xfe_carrier/carrier_account'; }
    protected function _getEntityIdColumn() { return 'account_id'; }
    protected function _getEntityType()    { return 'account'; }
    public function build(array $post)      { return $this->_buildFromPost($post); }
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

$probe = new BuildFromPostProbe();

// 1. 正常形态
$post = array(
    'custom_fields' => array(
        'warehouse_code' => array('label' => '仓库代码', 'type' => 'text',    'value' => 'WH-001'),
        'max_weight'     => array('label' => '最大承重', 'type' => 'number',  'value' => '20'),
        'service'        => array(
            'label' => '服务', 'type' => 'select', 'value' => 'express',
            'options' => 'standard, express, economy',
        ),
        'auto_retry'     => array('label' => '自动重试', 'type' => 'boolean', 'value' => '1'),
    ),
);
$coll = $probe->build($post);
assertEq(4, $coll->count(), 'build 出 4 个字段');
assertEq('WH-001', $coll->get('warehouse_code')->getValue(), 'text value');
assertEq(20, $coll->get('max_weight')->getValue(), 'number value int');
assertEq('express', $coll->get('service')->getValue(), 'select value');
assertEq(true, $coll->get('auto_retry')->getValue(), 'boolean value');

// select options 解析(逗号分隔 + 空白)
$opts = $coll->get('service')->getOptions();
assertEq(array('standard', 'express', 'economy'), $opts, 'select options 拆分 + 去空白');

// 2. 缺/空段 → 空集合
assertEq(0, $probe->build(array())->count(), 'POST 整段空 → 空集合');
assertEq(0, $probe->build(array('custom_fields' => null))->count(), 'custom_fields=null');
assertEq(0, $probe->build(array('custom_fields' => 'not array'))->count(), 'custom_fields 非数组');

// 3. 非法 spec 静默跳过
$mixed = array(
    'custom_fields' => array(
        'good'     => array('label' => 'G', 'type' => 'text', 'value' => 'x'),
        'bad_type' => array('label' => 'B', 'type' => 'date', 'value' => 'x'),
        'not_spec' => 'string instead of array',
        ''         => array('label' => 'empty_key', 'type' => 'text'),
    ),
);
$coll2 = $probe->build($mixed);
assertEq(1, $coll2->count(), '非法 spec 静默跳过,仅保留 1 条合法');
assertEq(true, $coll2->has('good'), 'good 保留');

// 4. multiselect: 自由标签模式(options 空)
$msPost = array(
    'custom_fields' => array(
        'supported_areas' => array(
            'label' => '支持区域',
            'type'  => 'multiselect',
            'value' => '华东, 华南 , 华北',
            // options 缺失 → 自由输入模式
        ),
    ),
);
$msColl = $probe->build($msPost);
assertEq(1, $msColl->count(), 'multiselect POST 自由模式 build 1 个字段');
assertEq('multiselect', $msColl->get('supported_areas')->getType(), 'multiselect type');
assertEq(array('华东', '华南', '华北'),
    $msColl->get('supported_areas')->getValue(),
    'multiselect POST 逗号串 → trim + 拆数组');
assertEq(array(),
    $msColl->get('supported_areas')->getOptions(),
    'multiselect POST 自由模式 options 为空');

// 5. multiselect: 固定选项模式(options 非空)
$msFixedPost = array(
    'custom_fields' => array(
        'service_levels' => array(
            'label' => '服务等级',
            'type'  => 'multiselect',
            'value' => 'express,standard',
            'options' => 'standard, express, economy',
        ),
    ),
);
$msFixedColl = $probe->build($msFixedPost);
assertEq(array('express', 'standard'),
    $msFixedColl->get('service_levels')->getValue(),
    'multiselect 固定模式 value 拆 + 保留顺序');
assertEq(array('standard', 'express', 'economy'),
    $msFixedColl->get('service_levels')->getOptions(),
    'multiselect 固定模式 options 拆分 + 去空白');

// 6. multiselect: 固定选项模式下,value 含 options 外元素 → 静默跳过
$msBad = array(
    'custom_fields' => array(
        'levels' => array(
            'label' => '等级',
            'type'  => 'multiselect',
            'value' => 'overnight',
            'options' => 'standard, express',
        ),
    ),
);
$msBadColl = $probe->build($msBad);
assertEq(0, $msBadColl->count(),
    'multiselect 固定 options 下 value 不合法 → 整行静默跳过');

echo PHP_EOL . ($failed ? "FAILED: {$failed} assertion(s)" : 'ALL PASS') . PHP_EOL;
exit($failed ? 1 : 0);
