<?php
/**
 * XFE 全部 PHP 测试统一入口(2026-09-17 扩展覆盖 Carrier 套件,小改 E)。
 *
 * 用途:
 *   - 本地开发者:`php tests/php/run-tests.php` 一键跑全部 PHP 测试
 *   - CI:GitLab CI / GitHub Actions 用同一入口,行为一致
 *
 * 测试套件(顺序:Injection 单元 → 集成 → Carrier):
 *   1. InjectionTest.php                          —  71 assertions (Domain / Registry / Runner / XmlReader / Merger)
 *   2. CarrierLogisticTest.php                    —  42 assertions (Carrier ↔ Logistic XML 注入调用链)
 *   3. DocumentUploadTest.php                     —  49 assertions (DocumentUpload ↔ Carrier XML 注入接入)
 *   4. PodServiceMainPathTest.php                 —  37 assertions (PodService 主流路径接 XML 注入)
 *   5. CustomAttributeApplierStrictTest.php       —  37 assertions (Applier 严格模式 / 自由 chips / select/multiselect)
 *   6. CustomFieldServiceBuildFromPostTest.php    —  18 assertions (Field Service 从 POST 构造 Domain)
 *   7. CustomAttributeImportExportTest.php        —  65 assertions (CSV 导入导出 + 异常路径)
 *   8. CustomAttributeServiceMigrationTest.php    —  48 assertions (小改 C:options_csv 变更迁移 3 策略)
 *   9. CustomAttributeServiceBooleanTest.php      -  32 assertions (小改 K:boolean label 自定义 + 结构化 options + parse/serialize helpers)
 *  10. MigrateGlsCredentialsTest.php               -  40 assertions (Logistic GLS 凭据迁移脚本,ADR 0020)
 *   ─────────────────────────────────────────────
 *   合计: 439 assertions(Injection 199 + Carrier 200 + Logistic 40)
 *   - "ALL PASS" + [PASS]/[FAIL] 行格式(老 Carrier 套件,通过 grep 统计 [PASS] 数)
 *
 * 退出码:
 *   0 = 全部通过(允许合并 PR)
 *   1 = 有失败(阻止合并 PR)
 *
 * 不做的事:
 *   - 不引导 Magento(测试文件自带 autoloader,无 Mage 硬依赖)
 *   - 不做覆盖率报告(下一轮按需引入 Xdebug)
 *   - 不做 PHP 版本检查(假设调用方已选好版本)
 *   - 不跑 Magento 强依赖测试(如 OrderRuleResolverTest / QuoteRuleResolverTest,需要 Magento 引导)
 *
 * 关联文档:
 *   - docs/architecture/ci-php-integration.md §3.1
 *   - docs/architecture/xfe-carrier-custom-attribute-form-switcher-js.md §2.12
 */

// 从 __DIR__ 推项目根:tests/php/ → 项目根(2 层)
$projectRoot = realpath(__DIR__ . '/../../');
if ($projectRoot === false) {
    fwrite(STDERR, "FATAL: cannot resolve project root from " . __DIR__ . PHP_EOL);
    exit(2);
}

// 注入一个顶层 PHP error 监听,把 Notice/Warning 升级为可见(但不致命)
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return false; }
    fwrite(STDERR, sprintf("  PHP %s: %s in %s:%d\n",
        ($severity === E_ERROR || $severity === E_USER_ERROR) ? 'Error' : 'Warning',
        $message, $file, $line
    ));
    return true;
});

/**
 * 跑单个测试文件,捕获断言汇总。
 */
function runTestFile($file, $label) {
    global $projectRoot;
    $full = $projectRoot . '/' . $file;
    if (!file_exists($full)) {
        fwrite(STDERR, "  SKIP   {$label}: file not found: {$file}\n");
        return array('label' => $label, 'file' => $file, 'ok' => false, 'skipped' => true, 'assertions' => 0, 'failures' => 0);
    }

    echo "\n=== {$label} ===\n";
    echo "  FILE: {$file}\n";

    // 切到项目根,让测试文件内的 realpath(__DIR__ . '/../../...') 反推逻辑仍正确
    $cwd = getcwd();
    chdir($projectRoot);
    $cmd = 'php ' . escapeshellarg($full) . ' 2>&1';
    $output = shell_exec($cmd);
    chdir($cwd);

    echo $output;

    // 解析测试输出,支持两种格式:
    //   格式 A:"Total: N assertions, M failures"(Injection / Migration 套件)
    //   格式 B:"ALL PASS" + [PASS]/[FAIL] 行(老 Carrier 套件,统计 [PASS] 数)
    $assertions = 0;
    $failures   = 0;
    if (preg_match('/Total:\s+(\d+)\s+assertions,\s+(\d+)\s+failures/', $output, $m)) {
        // 格式 A
        $assertions = (int)$m[1];
        $failures   = (int)$m[2];
    } elseif (strpos($output, 'ALL PASS') !== false) {
        // 格式 B:统计 [PASS] 行数作为 assertions,0 失败
        $assertions = preg_match_all('/\[PASS\]/', $output);
        $failures   = preg_match_all('/\[FAIL\]/', $output);
    } else {
        // 没匹配到任何已知格式:可能是 PHP 致命错误
        $failures = 1;
        fwrite(STDERR, "  ERROR  {$label}: cannot parse test output (no Total line, no ALL PASS)\n");
    }

    return array(
        'label'      => $label,
        'file'       => $file,
        'ok'         => ($failures === 0),
        'skipped'    => false,
        'assertions' => $assertions,
        'failures'   => $failures,
    );
}

// 顺序跑 9 个测试文件(Injection 4 + Carrier 4 + Logistic 1,2026-09-17 扩展 Carrier 覆盖,小改 E)
$suites = array(
    // --- XFE_Injection 套件 ---
    array(
        'file'  => 'app/code/community/XFE/Injection/Test/Unit/InjectionTest.php',
        'label' => 'Unit: InjectionTest',
    ),
    array(
        'file'  => 'app/code/community/XFE/Injection/Test/Integration/CarrierLogisticTest.php',
        'label' => 'Integration: CarrierLogisticTest',
    ),
    array(
        'file'  => 'app/code/community/XFE/Injection/Test/Integration/DocumentUploadTest.php',
        'label' => 'Integration: DocumentUploadTest',
    ),
    array(
        'file'  => 'app/code/community/XFE/Injection/Test/Integration/PodServiceMainPathTest.php',
        'label' => 'Integration: PodServiceMainPathTest',
    ),
    // --- XFE_Carrier 套件(2026-09-17 扩展,小改 E) ---
    array(
        'file'  => 'app/code/community/XFE/Carrier/Test/Service/CustomAttributeApplierStrictTest.php',
        'label' => 'Carrier: CustomAttributeApplierStrict',
    ),
    array(
        'file'  => 'app/code/community/XFE/Carrier/Test/Service/CustomFieldServiceBuildFromPostTest.php',
        'label' => 'Carrier: CustomFieldServiceBuildFromPost',
    ),
    array(
        'file'  => 'app/code/community/XFE/Carrier/Test/Service/CustomAttributeImportExportTest.php',
        'label' => 'Carrier: CustomAttributeImportExport',
    ),
    array(
        'file'  => 'app/code/community/XFE/Carrier/Test/Service/CustomAttributeServiceMigrationTest.php',
        'label' => 'Carrier: CustomAttributeServiceMigration (小改 C)',
    ),
    // --- XFE_Logistic 套件 (ADR 0020 GLS 凭据迁移) ---
    array(
        'file'  => 'app/code/community/XFE/Logistic/Test/Sql/MigrateGlsCredentialsTest.php',
        'label' => 'Unit: MigrateGlsCredentials (ADR 0020)',
    ),
    array(
        'file'  => 'app/code/community/XFE/Carrier/Test/Service/CustomAttributeServiceBooleanTest.php',
        'label' => 'Carrier: CustomAttributeServiceBoolean (小改 K)',
    ),
);

echo "========================================\n";
echo "XFE_Injection PHP test suite runner\n";
echo "Project root: {$projectRoot}\n";
echo "PHP version:  " . PHP_VERSION . "\n";
echo "========================================\n";

$results = array();
foreach ($suites as $s) {
    $results[] = runTestFile($s['file'], $s['label']);
}

// 汇总
$totalAssertions = 0;
$totalFailures   = 0;
$hasFailure      = false;
echo "\n========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
foreach ($results as $r) {
    $totalAssertions += $r['assertions'];
    $totalFailures   += $r['failures'];
    if (!$r['ok']) { $hasFailure = true; }
    $status = $r['skipped'] ? 'SKIP' : ($r['ok'] ? 'PASS' : 'FAIL');
    printf("  %-4s  %-40s  %4d assertions, %d failures\n",
        $status, $r['label'], $r['assertions'], $r['failures']
    );
}

echo "========================================\n";
printf("Total: %d assertions, %d failures\n", $totalAssertions, $totalFailures);
echo "========================================\n";

exit($hasFailure ? 1 : 0);
