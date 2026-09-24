<?php
/**
 * GLS API system config 清理脚本单元测试（ADR 0021 终结态）。
 *
 * 验证：
 *   1. dry-run 模式不调用 delete（不写 DB）
 *   2. real 模式调用 delete 并返回删除行数
 *   3. resource 为 null 时报 ERROR（前置条件失败）
 *   4. DELETE WHERE path LIKE 'xfe_logistic/gls_api/%' SQL 正确
 *   5. 重复执行（real 模式）幂等：报告 deleted_rows 反映每次实际删除数
 *   6. 异常捕获：write->delete 抛异常时记录 errors[]，不退出
 *
 * 不验证：
 *   - 真实 Mage 引导（Mage::getSingleton('core/resource') 等用 mock 替代）
 *   - 真实 MySQL 执行（MockConnection 模拟）
 *
 * 运行：php app/code/community/XFE/Logistic/Test/Sql/CleanupGlsApiSystemConfigTest.php
 * 退出码：0 = 通过，非 0 = 失败
 *
 * 关联文档：
 *   - docs/architecture/decisions/0021-finalize-podservice-injection.md
 *   - docs/architecture/finalize-podservice-injection.md §3.6
 */

spl_autoload_register(function ($class) {
    $projectRoot = realpath(__DIR__ . '/../../../../../../');
    if ($projectRoot === false) return;
    $rel = str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
    foreach (array('community', 'core', 'local') as $pool) {
        $file = $projectRoot . '/app/code/' . $pool . '/' . $rel;
        if (file_exists($file)) { require $file; return; }
    }
});

// 防止 cleanup 脚本内的 CLI 入口块自动执行
if (!defined('CLI_SCRIPT_DISABLED_FOR_TEST')) {
    define('CLI_SCRIPT_DISABLED_FOR_TEST', true);
}

require_once __DIR__ . '/../../sql/xfe_logistic_setup/data-upgrade/cleanup-gls-api-system-config.php';

$assertions = 0;
$failures = 0;
function ok($cond, $msg) {
    global $assertions, $failures;
    $assertions++;
    echo $cond ? "  PASS  " : "  FAIL  ";
    echo $msg . "\n";
    if (!$cond) $failures++;
}

// ── Mock helpers ──────────────────────────────────────

class MockCleanupConnection {
    public $deleteCount = 0;
    public $deleteRows = 0;
    public $deleteArgs = null;
    public $shouldThrow = false;

    public function delete($table, $where) {
        $this->deleteCount++;
        $this->deleteArgs = array('table' => $table, 'where' => $where);
        if ($this->shouldThrow) {
            throw new Exception('Simulated DB error');
        }
        return $this->deleteRows;
    }
}

class MockCleanupResource {
    public $conn;
    public function __construct(MockCleanupConnection $c) { $this->conn = $c; }
    public function getConnection($name) { return $this->conn; }
    public function getTableName($alias) { return $alias; }
}

function buildLogger(&$logs) {
    return function ($level, $msg) use (&$logs) {
        $logs[] = array($level, $msg);
    };
}

echo "\n=== Unit Test: GLS API system config cleanup (ADR 0021) ===\n\n";

// ── Test 1: dry-run 模式不写 DB ────────────────────────
echo "--- Test 1: dry-run mode does not write DB ---\n";
$conn = new MockCleanupConnection();
$resource = new MockCleanupResource($conn);
$cleanup = new XFE_Logistic_Sql_Cleanup_GlsApiSystemConfig($resource);
$logs = array();
$report = $cleanup->cleanup(true, buildLogger($logs));

ok($conn->deleteCount === 0, "delete() called 0 times in dry-run");
ok($report['dry_run'] === true, "report.dry_run = true");
ok($report['deleted_rows'] === 0, "report.deleted_rows = 0 (no actual delete)");
ok(count($report['errors']) === 0, "no errors");
ok(count($logs) === 3, "logger received 3 messages (plan + expected + no-op)");
ok($logs[0][0] === 'DRY-RUN', "first log level = DRY-RUN");
ok(strpos($logs[0][1], 'DELETE FROM') !== false, "first log shows DELETE SQL");
ok(strpos($logs[0][1], 'xfe_logistic/gls_api/%') !== false, "first log has correct WHERE clause");
ok(strpos($logs[1][1], 'Expected: 4 rows') !== false, "second log shows expected count");

// ── Test 2: real 模式删除 4 行 ────────────────────────
echo "\n--- Test 2: real mode deletes 4 rows ---\n";
$conn2 = new MockCleanupConnection();
$conn2->deleteRows = 4;
$resource2 = new MockCleanupResource($conn2);
$cleanup2 = new XFE_Logistic_Sql_Cleanup_GlsApiSystemConfig($resource2);
$logs2 = array();
$report2 = $cleanup2->cleanup(false, buildLogger($logs2));

ok($conn2->deleteCount === 1, "delete() called 1 time in real mode");
ok($report2['deleted_rows'] === 4, "report.deleted_rows = 4");
ok($report2['dry_run'] === false, "report.dry_run = false");
ok(count($report2['errors']) === 0, "no errors");
ok($conn2->deleteArgs['table'] === 'core_config_data', "delete target table = core_config_data");
ok(isset($conn2->deleteArgs['where']['path LIKE ?']), "WHERE has path LIKE placeholder");
ok($conn2->deleteArgs['where']['path LIKE ?'] === 'xfe_logistic/gls_api/%', "WHERE value = 'xfe_logistic/gls_api/%'");
ok(strpos($logs2[0][1], 'Deleted 4 rows') !== false, "log says 'Deleted 4 rows'");

// ── Test 3: resource 为 null 时报 ERROR ────────────────
echo "\n--- Test 3: null resource reports error ---\n";
$cleanup3 = new XFE_Logistic_Sql_Cleanup_GlsApiSystemConfig(null);
$logs3 = array();
$report3 = $cleanup3->cleanup(false, buildLogger($logs3));

ok(count($report3['errors']) === 1, "1 error reported");
ok($report3['errors'][0] === 'Resource not available', "error message = 'Resource not available'");
ok(strpos($logs3[0][1], 'Resource not available') !== false, "logger received error");
ok($report3['deleted_rows'] === 0, "no rows deleted on null resource");

// ── Test 4: 重复执行（幂等）────────────────────────────
echo "\n--- Test 4: idempotency (repeated execution) ---\n";
$conn4 = new MockCleanupConnection();
$conn4->deleteRows = 0; // 第二次执行：已删完
$resource4 = new MockCleanupResource($conn4);
$cleanup4 = new XFE_Logistic_Sql_Cleanup_GlsApiSystemConfig($resource4);
$logs4 = array();
$report4 = $cleanup4->cleanup(false, buildLogger($logs4));

ok($conn4->deleteCount === 1, "delete() called 1 time");
ok($report4['deleted_rows'] === 0, "second run deleted 0 rows (idempotent)");
ok(count($report4['errors']) === 0, "no errors on second run");

// ── Test 5: 异常捕获 ───────────────────────────────────
echo "\n--- Test 5: exception during delete is caught ---\n";
$conn5 = new MockCleanupConnection();
$conn5->shouldThrow = true;
$resource5 = new MockCleanupResource($conn5);
$cleanup5 = new XFE_Logistic_Sql_Cleanup_GlsApiSystemConfig($resource5);
$logs5 = array();
$report5 = $cleanup5->cleanup(false, buildLogger($logs5));

ok(count($report5['errors']) === 1, "1 error reported");
ok(strpos($report5['errors'][0], 'Delete failed') !== false, "error mentions 'Delete failed'");
ok(strpos($report5['errors'][0], 'Simulated DB error') !== false, "error includes original exception message");
ok($report5['deleted_rows'] === 0, "no rows deleted on exception");

// ── Test 6: dry-run 模式下 resource=null 也应报 error ─
echo "\n--- Test 6: dry-run with null resource also errors ---\n";
$cleanup6 = new XFE_Logistic_Sql_Cleanup_GlsApiSystemConfig(null);
$logs6 = array();
$report6 = $cleanup6->cleanup(true, buildLogger($logs6));

ok(count($report6['errors']) === 1, "1 error even in dry-run (resource null check happens before dry-run branch)");
ok($report6['errors'][0] === 'Resource not available', "same error message in dry-run");

echo "\n========================================\n";
echo "Total: {$assertions} assertions, {$failures} failures\n";
echo "========================================\n";

exit($failures > 0 ? 1 : 0);