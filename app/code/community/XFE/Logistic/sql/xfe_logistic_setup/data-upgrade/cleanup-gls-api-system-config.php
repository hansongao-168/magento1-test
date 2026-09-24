<?php
/**
 * 清理 GLS API 凭据残留（ADR 0021 终结态）。
 *
 * 把 core_config_data 中 `xfe_logistic/gls_api/*` 4 条记录删除，
 * 配合 system.xml 已下线 gls_api 组，实现 system config 完全清理。
 *
 * 历史：
 *   - 1.0.x ~ 1.2.x：GLS 凭据存在 xfe_logistic/gls_api/{base_url,username,password,parcelpod_resource}
 *   - 1.2.0（ADR 0020）：migrate-gls-api-credentials.php 把上述凭据搬到 xfe_carrier_account 表（INSERT）
 *   - 1.3.0（ADR 0021）：本类删除 core_config_data 中 4 条 xfe_logistic/gls_api/* 残留
 *
 * 设计原则：
 *   - 文件名 `cleanup-gls-api-system-config.php`（非 `install-*` / `upgrade-*`），
 *     不会被 Mage_Core_Model_Resource_Setup 自动扫描运行。
 *   - 管理员必须通过 `shell/cleanup-gls-api-system-config.php [--dry-run]` 显式触发。
 *   - 幂等：DELETE WHERE path LIKE 'xfe_logistic/gls_api/%' 多次执行无副作用。
 *   - dry-run：默认行为可切换，不写 DB。
 *
 * Class 风格而非 function 风格：依赖通过构造函数注入，便于单元测试 mock。
 *
 * 关联文档：
 *   - docs/architecture/decisions/0021-finalize-podservice-injection.md
 *   - docs/architecture/finalize-podservice-injection.md §5.2
 */

class XFE_Logistic_Sql_Cleanup_GlsApiSystemConfig
{
    /** @var mixed */
    private $_resource;

    /**
     * @param mixed $resource Mage::getSingleton('core/resource') 或 mock
     */
    public function __construct($resource = null)
    {
        $this->_resource = $resource;
    }

    /**
     * 执行清理核心逻辑。
     *
     * @param bool $dryRun true = 仅打印计划不写 DB
     * @param callable|null $logger 接受 (string $level, string $msg) 的回调
     * @return array 清理报告
     */
    public function cleanup($dryRun = false, $logger = null)
    {
        if ($logger === null) {
            $logger = function ($level, $msg) {
                fwrite(STDOUT, "[{$level}] {$msg}\n");
            };
        }

        $report = array(
            'deleted_rows' => 0,
            'dry_run'      => $dryRun,
            'errors'       => array(),
        );

        // 前置条件：Resource 可用
        if ($this->_resource === null) {
            $report['errors'][] = 'Resource not available';
            $logger('ERROR', $report['errors'][0]);
            return $report;
        }

        $write = $this->_resource->getConnection('core_write');
        $table = $this->_resource->getTableName('core_config_data');

        $where = array('path LIKE ?' => 'xfe_logistic/gls_api/%');

        if ($dryRun) {
            $logger('DRY-RUN', "DELETE FROM {$table} WHERE path LIKE 'xfe_logistic/gls_api/%'");
            $logger('DRY-RUN', "Expected: 4 rows (base_url / username / password / parcelpod_resource)");
            $logger('DRY-RUN', 'No DB writes performed.');
            return $report;
        }

        try {
            $deleted = $write->delete($table, $where);
            $report['deleted_rows'] = (int) $deleted;
            $logger('INFO', "Deleted {$deleted} rows from {$table} (path LIKE 'xfe_logistic/gls_api/%')");
        } catch (Exception $e) {
            $report['errors'][] = 'Delete failed: ' . $e->getMessage();
            $logger('ERROR', $report['errors'][0]);
        }

        return $report;
    }
}

// 直接 require 此文件时的入口（CLI 模式）。
// 当被 MigrateGlsCredentialsTest 引用时，此入口不会触发（Mage 类已存在即跳过）。
if (PHP_SAPI === 'cli' && basename($_SERVER['SCRIPT_FILENAME']) === 'cleanup-gls-api-system-config.php') {
    if (!class_exists('Mage', false)) {
        require_once dirname(__FILE__) . '/../../../../../../Mage.php';
        Mage::app('admin');
    }
    $resource = Mage::getSingleton('core/resource');
    $cleanup = new XFE_Logistic_Sql_Cleanup_GlsApiSystemConfig($resource);
    $report = $cleanup->cleanup(false);
    echo "Deleted {$report['deleted_rows']} rows\n";
}