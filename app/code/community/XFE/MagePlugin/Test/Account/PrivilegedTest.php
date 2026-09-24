<?php

/**
 * XFE_MagePlugin_Model_Account_Privileged 测试。
 *
 * 运行：php app/code/community/XFE/MagePlugin/Test/Account/PrivilegedTest.php
 *
 * 退出码 0 = 全部通过；1 = 有失败。
 * 仅加载 XFE_MagePlugin_Model_Account_* 树，无需启动完整 Magento。
 */

spl_autoload_register(function ($class) {
    if (strpos($class, 'XFE_MagePlugin_Model_Account_') !== 0) {
        return;
    }
    $parts = explode('_', $class);
    array_shift($parts); // XFE
    array_shift($parts); // MagePlugin
    array_shift($parts); // Model
    array_shift($parts); // Account
    $path = implode('/', $parts) . '.php';
    $candidate = __DIR__ . '/../../Model/Account/' . $path;
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

$p = new XFE_MagePlugin_Model_Account_Privileged();

// isFullAccess
assertEq(true,  $p->isFullAccess(1), 'user 1 full access');
assertEq(true,  $p->isFullAccess(2), 'user 2 full access');
assertEq(false, $p->isFullAccess(3), 'user 3 NOT full access');
assertEq(true, $p->isFullAccess('1'), 'string "1" cast to int is full access');
assertEq(false, $p->isFullAccess(0), 'user 0 NOT full access');
assertEq(false, $p->isFullAccess(null), 'null NOT full access');

// canAutoAddIp
assertEq(true,  $p->canAutoAddIp(1), 'user 1 can auto-add ip');
assertEq(true,  $p->canAutoAddIp(5), 'user 5 can auto-add ip');
assertEq(false, $p->canAutoAddIp(6), 'user 6 cannot auto-add ip');
assertEq(false, $p->canAutoAddIp(0), 'user 0 cannot auto-add ip');

if ($failed === 0) {
    echo "\nALL PASS\n";
    exit(0);
} else {
    echo "\n{$failed} FAILED\n";
    exit(1);
}
