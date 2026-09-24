<?php
/**
 * CLI 入口：清理 GLS API 凭据残留（ADR 0021 终结态）。
 *
 * 用法：
 *   php shell/cleanup-gls-api-system-config.php              # 真实执行
 *   php shell/cleanup-gls-api-system-config.php --dry-run    # 仅打印计划（无需 Mage）
 *
 * 退出码：
 *   0 = 成功
 *   1 = 失败（详情见输出）
 *
 * 关联文档：
 *   - docs/architecture/decisions/0021-finalize-podservice-injection.md
 *   - docs/architecture/finalize-podservice-injection.md §5.2
 */

// 解析参数
$dryRun = in_array('--dry-run', $argv, true);

echo "==========================================\n";
echo "Cleanup GLS API system config (ADR 0021)\n";
echo "Mode: " . ($dryRun ? 'DRY-RUN' : 'REAL') . "\n";
echo "==========================================\n\n";

if ($dryRun) {
    // dry-run 不引导 Magento（PHP 8.5 下 Mage::bootstrap 不必要）
    echo "[DRY-RUN] Plan:\n";
    echo "  DELETE FROM core_config_data WHERE path LIKE 'xfe_logistic/gls_api/%'\n";
    echo "  Expected: 4 rows (base_url / username / password / parcelpod_resource)\n";
    echo "[DRY-RUN] No DB writes performed. Re-run without --dry-run to execute.\n";
    exit(0);
}

// 真实执行：引导 Magento
$mageBaseDir = dirname(__DIR__);
require_once $mageBaseDir . '/app/Mage.php';
Mage::app('admin');

// 引入 Class
require_once $mageBaseDir
    . '/app/code/community/XFE/Logistic/sql/xfe_logistic_setup/data-upgrade/cleanup-gls-api-system-config.php';

// 简单 stdout logger
$logger = function ($level, $msg) {
    fwrite(STDOUT, "[{$level}] {$msg}\n");
};

// 执行清理
try {
    $resource = Mage::getSingleton('core/resource');
    $cleanup = new XFE_Logistic_Sql_Cleanup_GlsApiSystemConfig($resource);
    $report = $cleanup->cleanup(false, $logger);
} catch (Throwable $e) {
    fwrite(STDERR, '[FATAL] ' . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}

// 汇总
echo "\n==========================================\n";
echo "SUMMARY\n";
echo "==========================================\n";
echo "  deleted_rows: {$report['deleted_rows']}\n";
echo "  dry_run:      " . ($report['dry_run'] ? 'yes' : 'no') . "\n";
echo "  errors:       " . count($report['errors']) . "\n";

if (count($report['errors']) > 0) {
    foreach ($report['errors'] as $err) {
        echo "    - {$err}\n";
    }
    exit(1);
}

exit(0);