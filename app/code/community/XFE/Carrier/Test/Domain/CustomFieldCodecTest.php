<?php
/**
 * CustomFieldCodec 单元测试(L1 Domain)
 *
 * 覆盖:encode / decode 往返、空 / 非法 JSON → 空集合、list 形态 → 过滤。
 *
 * 运行:php app/code/community/XFE/Carrier/Test/Domain/CustomFieldCodecTest.php
 */

spl_autoload_register(function ($class) {
    if (strpos($class, 'XFE_Carrier_Domain_') !== 0) {
        return;
    }
    $parts = explode('_', $class);
    array_shift($parts); array_shift($parts); array_shift($parts); // XFE_Carrier_Domain
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

// 1. 空 / null → 空集合
assertEq(0, XFE_Carrier_Domain_CustomFieldCodec::decode(null)->count(), 'null → 空集合');
assertEq(0, XFE_Carrier_Domain_CustomFieldCodec::decode('')->count(), '空串 → 空集合');
assertEq(0, XFE_Carrier_Domain_CustomFieldCodec::decode('not json')->count(), '非法 JSON → 空集合');
assertEq(0, XFE_Carrier_Domain_CustomFieldCodec::decode('[]')->count(), 'list 形态 → 空集合');
assertEq(0, XFE_Carrier_Domain_CustomFieldCodec::decode('123')->count(), '标量 → 空集合');

// 2. 正常对象 → 集合
$json = '{"a":{"label":"A","type":"text","value":"x"},"b":{"label":"B","type":"number","value":2}}';
$coll = XFE_Carrier_Domain_CustomFieldCodec::decode($json);
assertEq(2, $coll->count(), '两个字段');
assertEq('x', $coll->get('a')->getValue(), 'a.value');
assertEq(2, $coll->get('b')->getValue(), 'b.value 是 int');

// 3. encode → decode 往返
$round = XFE_Carrier_Domain_CustomFieldCodec::decode(
    XFE_Carrier_Domain_CustomFieldCodec::encode($coll)
);
assertEq($coll->count(), $round->count(), 'encode→decode count 相同');
assertEq(
    $coll->get('a')->getValue(),
    $round->get('a')->getValue(),
    'encode→decode a.value 相同'
);

// 4. encode 空集合 → "{}"
assertEq('{}', XFE_Carrier_Domain_CustomFieldCodec::encode(
    new XFE_Carrier_Domain_CustomFieldCollection()
), '空集合 → "{}"');

// 5. encode 不会转义中文
$cn = new XFE_Carrier_Domain_CustomFieldCollection();
$cn->add(new XFE_Carrier_Domain_CustomField('k', '仓库代码', 'text', 'WH-001'));
$encoded = XFE_Carrier_Domain_CustomFieldCodec::encode($cn);
assertEq(true, strpos($encoded, '仓库代码') !== false, '中文不被转义');
assertEq(true, strpos($encoded, 'WH-001') !== false, 'value 原样');

// 6. multiselect 节点 roundtrip:自由标签模式(options 空)
$msJson = '{"areas":{"label":"支持区域","type":"multiselect","value":["华东","华南","华北"],"options":[]}}';
$msColl = XFE_Carrier_Domain_CustomFieldCodec::decode($msJson);
assertEq(1, $msColl->count(), 'multiselect decode 一个字段');
assertEq('multiselect', $msColl->get('areas')->getType(), 'multiselect type 保留');
assertEq(array('华东', '华南', '华北'), $msColl->get('areas')->getValue(),
    'multiselect 自由模式 value 数组');
assertEq(array(), $msColl->get('areas')->getOptions(), 'multiselect 自由模式 options 空');

$msRound = XFE_Carrier_Domain_CustomFieldCodec::decode(
    XFE_Carrier_Domain_CustomFieldCodec::encode($msColl)
);
assertEq(array('华东', '华南', '华北'), $msRound->get('areas')->getValue(),
    'multiselect 自由模式 roundtrip value');

// 7. multiselect 节点 roundtrip:固定 options 模式
$msFixedJson = '{"levels":{"label":"服务等级","type":"multiselect",'
    . '"value":["express","economy"],'
    . '"options":["standard","express","economy"]}}';
$msFixedColl = XFE_Carrier_Domain_CustomFieldCodec::decode($msFixedJson);
assertEq(array('express', 'economy'), $msFixedColl->get('levels')->getValue(),
    'multiselect 固定模式 value');
assertEq(array('standard', 'express', 'economy'),
    $msFixedColl->get('levels')->getOptions(),
    'multiselect 固定模式 options');

$msFixedRound = XFE_Carrier_Domain_CustomFieldCodec::decode(
    XFE_Carrier_Domain_CustomFieldCodec::encode($msFixedColl)
);
assertEq(array('express', 'economy'),
    $msFixedRound->get('levels')->getValue(),
    'multiselect 固定模式 roundtrip value');
assertEq(array('standard', 'express', 'economy'),
    $msFixedRound->get('levels')->getOptions(),
    'multiselect 固定模式 roundtrip options');

// 8. mixed: text + number + boolean + select + multiselect 五类型同存
$mixed = new XFE_Carrier_Domain_CustomFieldCollection();
$mixed->add(new XFE_Carrier_Domain_CustomField('wh', '仓库', 'text', 'WH-1'));
$mixed->add(new XFE_Carrier_Domain_CustomField('cap', '上限', 'number', 100));
$mixed->add(new XFE_Carrier_Domain_CustomField('retry', '重试', 'boolean', true));
$mixed->add(new XFE_Carrier_Domain_CustomField(
    'svc', '单选服务', 'select', 'express', array('express', 'standard')
));
$mixed->add(new XFE_Carrier_Domain_CustomField(
    'areas', '区域', 'multiselect', array('华东', '华南')
));
$mixedRound = XFE_Carrier_Domain_CustomFieldCodec::decode(
    XFE_Carrier_Domain_CustomFieldCodec::encode($mixed)
);
assertEq(5, $mixedRound->count(), '5 类型同存 count');
assertEq(array('华东', '华南'), $mixedRound->get('areas')->getValue(),
    '5 类型同存 multiselect value');

echo PHP_EOL . ($failed ? "FAILED: {$failed} assertion(s)" : 'ALL PASS') . PHP_EOL;
exit($failed ? 1 : 0);
