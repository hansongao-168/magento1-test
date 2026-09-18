<?php
/**
 * CLI 入口：GLS API 凭据迁移。
 *
 * 用法：
 *   php shell/migrate-gls-api-credentials.php              # 真实执行
 *   php shell/migrate-gls-api-credentials.php --dry-run    # 仅打印计划
 *
 * 退出码：
 *   0 = 成功
 *   1 = 失败（详情见输出）
 *
 * 关联文档：
 *   - docs/architecture/decisions/0020-migrate-gls-api-credentials.md
 *   - docs/architecture/migrate-gls-api-credentials.md
 */

// 解析参数
$dryRun = in_array('--dry-run', $argv, true);

echo "==========================================\n";
echo "GLS API credential migration (ADR 0020)\n";
echo "Mode: " . ($dryRun ? 'DRY-RUN' : 'REAL') . "\n";
echo "==========================================\n\n";

// 引导 Magento（与 cron script 相同模式）
$mageBaseDir = dirname(__DIR__);
require_once $mageBaseDir . '/app/Mage.php';
Mage::app('admin');

// 引入迁移逻辑
require_once $mageBaseDir
    . '/app/code/community/XFE/Logistic/sql/xfe_logistic_setup/data-upgrade/migrate-gls-api-credentials.php';

// 简单 stdout logger
$logger = function ($level, $msg) {
    fwrite(STDOUT, "[{$level}] {$msg}\n");
};

// 执行迁移
try {
    $report = migrate_gls_api_credentials($dryRun, $logger);
    migrate_gls_api_credentials_log_report($report, $dryRun);
} catch (Throwable $e) {
    fwrite(STDERR, '[FATAL] ' . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}

// 汇总
echo "\n==========================================\n";
echo "SUMMARY\n";
echo "==========================================\n";
echo "  inserted:       {$report['inserted']}\n";
echo "  skipped:        {$report['skipped']}\n";
echo "  seeded_carrier: " . ($report['seeded_carrier'] ? 'yes' : 'no') . "\n";
echo "  errors:         " . count($report['errors']) . "\n";

if (count($report['errors']) > 0) {
    foreach ($report['errors'] as $err) {
        echo "    - {$err}\n";
    }
    exit(1);
}

exit(0);
