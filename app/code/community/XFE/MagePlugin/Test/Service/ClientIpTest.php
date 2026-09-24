<?php

/**
 * XFE_MagePlugin_Model_Service_ClientIp::resolve() 测试。
 *
 * 运行：php app/code/community/XFE/MagePlugin/Test/Service/ClientIpTest.php
 *
 * 退出码 0 = 全部通过；1 = 有失败。
 */

spl_autoload_register(function ($class) {
    if (strpos($class, 'XFE_MagePlugin_Model_Service_') !== 0) {
        return;
    }
    $parts = explode('_', $class);
    array_shift($parts); // XFE
    array_shift($parts); // MagePlugin
    array_shift($parts); // Model
    array_shift($parts); // Service
    $path = implode('/', $parts) . '.php';
    $candidate = __DIR__ . '/../../Model/Service/' . $path;
    if (file_exists($candidate)) {
        require_once $candidate;
    }
});

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

$s = new XFE_MagePlugin_Model_Service_ClientIp();

// 基础：X-Forwarded-For 多 IP（调用方已按逗号拆分），取第一个公网 IP
assertEq('1.2.3.4', $s->resolve(array('1.2.3.4', '5.6.7.8', '10.0.0.1')), 'xff 多IP取第一个公网');
assertEq('1.2.3.4', $s->resolve(array('1.2.3.4', '5.6.7.8')), '候选取第一个公网');

// XFF 第一个是内网，跳过取下一个公网
assertEq('5.6.7.8', $s->resolve(array('10.0.0.1', '5.6.7.8')), 'xff 跳过内网取公网');

// 全部内网：回退第一个有效 IP
assertEq('10.0.0.1', $s->resolve(array('10.0.0.1', '192.168.1.2')), '全内网回退第一个');

// 带端口
assertEq('1.2.3.4', $s->resolve(array('1.2.3.4:8080')), '去除端口');

// IPv4-mapped IPv6
assertEq('1.2.3.4', $s->resolve(array('::ffff:1.2.3.4')), 'IPv4-mapped IPv6');

// 无效 IP 被跳过
assertEq('8.8.8.8', $s->resolve(array('not-an-ip', '8.8.8.8')), '跳过无效IP');

// 空候选返回空串
assertEq('', $s->resolve(array()), '空候选返回空');

// X-Real-IP 优先于 XFF（候选顺序即优先级）
assertEq('1.2.3.4', $s->resolve(array('1.2.3.4', '5.6.7.8,9.9.9.9')), 'X-Real-IP 优先');

// 稳定性：同一候选多次解析一致
$cands = array('10.0.0.5', '203.0.113.9', '172.16.0.1');
assertEq($s->resolve($cands), $s->resolve($cands), '同请求多次解析一致');

if ($failed === 0) {
    echo "\nALL PASS\n";
    exit(0);
} else {
    echo "\n{$failed} FAILED\n";
    exit(1);
}
