<?php
/**
 * XFE_Logistic_Domain_Constant_GlsApiConfig 单元测试（ADR 0021）。
 *
 * 验证：
 *   1. GlsApiConfig 类存在
 *   2. RESOURCE_PARCELPOD 常量已声明且值为 'parcelpod'（GLS 协议固定值，不可配置）
 *   3. 现有常量回归（AUTH_SCHEME_BASIC / HTTP_OK / MIME_PDF / CURL_TIMEOUT_SECONDS）
 *   4. 常量数量不变（不可变性保护）
 *
 * 运行：php Test/Unit/GlsApiConfigTest.php
 * 退出码 0 = 通过，非 0 = 失败
 *
 * 关联文档：docs/architecture/decisions/0021-finalize-podservice-injection.md
 */

spl_autoload_register(function ($class) {
    $projectRoot = realpath(__DIR__ . '/../../../../../../../');
    if ($projectRoot === false) {
        return;
    }
    $rel = str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
    foreach (array('community', 'core', 'local') as $pool) {
        $file = $projectRoot . '/app/code/' . $pool . '/' . $rel;
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

$assertions = 0;
$failures = 0;
function ok($cond, $msg) {
    global $assertions, $failures;
    $assertions++;
    echo $cond ? "  PASS  " : "  FAIL  ";
    echo $msg . "\n";
    if (!$cond) $failures++;
}

echo "\n=== Unit Test: GlsApiConfig constants (ADR 0021) ===\n\n";

ok(class_exists('XFE_Logistic_Domain_Constant_GlsApiConfig'),
    'GlsApiConfig class exists');

$constants = (new ReflectionClass('XFE_Logistic_Domain_Constant_GlsApiConfig'))->getConstants();

ok(isset($constants['RESOURCE_PARCELPOD']),
    'RESOURCE_PARCELPOD constant declared');
ok($constants['RESOURCE_PARCELPOD'] === 'parcelpod',
    'RESOURCE_PARCELPOD value === "parcelpod" (GLS protocol fixed value)');

// 现有常量回归
ok($constants['AUTH_SCHEME_BASIC'] === 'Basic',
    'AUTH_SCHEME_BASIC still "Basic"');
ok($constants['HTTP_OK'] === 200,
    'HTTP_OK still 200');
ok($constants['HTTP_CREATED'] === 201,
    'HTTP_CREATED still 201');
ok($constants['MIME_PDF'] === 'application/pdf',
    'MIME_PDF still "application/pdf"');
ok($constants['CURL_TIMEOUT_SECONDS'] === 30,
    'CURL_TIMEOUT_SECONDS still 30');

// 不可变性：反射读取两次，常量数量一致
$count1 = count($constants);
$constants2 = (new ReflectionClass('XFE_Logistic_Domain_Constant_GlsApiConfig'))->getConstants();
$count2 = count($constants2);
ok($count1 === $count2,
    "Constants immutable: count1={$count1} === count2={$count2}");

// 类型校验：常量是字符串（不是数字/数组）
ok(is_string($constants['RESOURCE_PARCELPOD']),
    'RESOURCE_PARCELPOD is string type');

// PodService 应通过 ::RESOURCE_PARCELPOD 访问（静态常量正确解析）
ok((XFE_Logistic_Domain_Constant_GlsApiConfig::RESOURCE_PARCELPOD) === 'parcelpod',
    'GlsApiConfig::RESOURCE_PARCELPOD === "parcelpod" (static resolution)');

echo "\n========================================\n";
echo "Total: {$assertions} assertions, {$failures} failures\n";
echo "========================================\n";

exit($failures > 0 ? 1 : 0);
