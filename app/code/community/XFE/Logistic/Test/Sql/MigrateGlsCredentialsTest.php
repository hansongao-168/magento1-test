<?php
/**
 * GLS API 凭据迁移脚本单元测试。
 *
 * 验证 (ADR 0020):
 *   1. 读 4 条 config rows (base_url / username / password / parcelpod_resource)
 *   2. password 解密 (mock encryption->decrypt)
 *   3. dry-run 模式不写 DB
 *   4. Carrier 主档不存在时自动 seed
 *   5. Carrier account 已存在 (username+endpoint_url 匹配) 时跳过
 *   6. 缺 base_url/username/password 时报 error
 *   7. Resource null 时报 error
 *
 * 不验证:
 *   - 真实 Mage 引导 (Mage::getSingleton('core/resource') 等)
 *   - 真实 Varien_Db 调用 (用 mock stdClass 替代)
 *
 * 运行: php app/code/community/XFE/Logistic/Test/Sql/MigrateGlsCredentialsTest.php
 * 退出码: 0 = 通过, 非 0 = 失败
 *
 * 关联文档: docs/architecture/migrate-gls-api-credentials.md §3.3
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

require_once __DIR__ . '/../../sql/xfe_logistic_setup/data-upgrade/migrate-gls-api-credentials.php';

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

class MockSelect {
    public $whereClauses = array();
    public $from = null;
    public function __construct() {}
    public function from($table, $cols = null) { $this->from = $table; return $this; }
    public function where($cond, $val = null) {
        $this->whereClauses[] = array($cond, $val);
        return $this;
    }
}

class MockConnection {
    public $configRows = array();
    public $carrierRows = array();
    public $accountRows = array();
    public $inserted = array();
    public $tableExists = true;
    public $lastInsertId = null;

    public $lastSelect = null;
    public $lastFetchAllSelect = null;

    public function select() { return new MockSelect(); }
    public function isTableExists($table) { return $this->tableExists; }
    public function fetchAll($select) {
        $this->lastFetchAllSelect = $select;
        // 根据 where 决定返回哪批
        if ($select->from && strpos($select->from, 'core_config_data') !== false) {
            return $this->configRows;
        }
        return array();
    }
    public function fetchRow($select) {
        $this->lastSelect = $select;
        if ($select->from && strpos($select->from, 'xfe_carrier/carrier') !== false
            && strpos($select->from, 'account') === false) {
            foreach ($this->carrierRows as $r) {
                foreach ($select->whereClauses as $wc) {
                    if ($wc[0] === 'code = ?' && isset($r['code']) && $r['code'] === $wc[1]) {
                        return $r;
                    }
                }
            }
            return null;
        }
        if ($select->from && strpos($select->from, 'carrier_account') !== false) {
            $filters = array();
            foreach ($select->whereClauses as $wc) { $filters[$wc[0]] = $wc[1]; }
            foreach ($this->accountRows as $r) {
                $match = true;
                if (isset($filters['carrier_id = ?']) && $r['carrier_id'] != $filters['carrier_id = ?']) $match = false;
                if (isset($filters['username = ?']) && $r['username'] != $filters['username = ?']) $match = false;
                if (isset($filters['endpoint_url = ?']) && $r['endpoint_url'] != $filters['endpoint_url = ?']) $match = false;
                if ($match) return $r;
            }
            return null;
        }
        return null;
    }
    public function insert($table, $data) {
        $this->inserted[] = array('table' => $table, 'data' => $data);
        if (strpos($table, 'carrier_account') !== false && isset($this->lastInsertId)) {
            return 1;
        }
        return 1;
    }
    public function lastInsertId($table) {
        return $this->lastInsertId ?? 42;
    }
}

class MockResource {
    public $conn;
    public function __construct(MockConnection $c) { $this->conn = $c; }
    public function getConnection($name) { return $this->conn; }
    public function getTableName($alias) {
        // 返回原 alias (不去掉斜杠),便于 mock fetchRow 用 strpos 识别
        return $alias;
    }
    public function getBaseDir($type) { return sys_get_temp_dir(); }
}

class MockEncryption {
    public $decryptedValue = 'plain_gls_pass';
    public function decrypt($value) { return $this->decryptedValue; }
}

function buildLogger(&$logs) {
    return function ($level, $msg) use (&$logs) {
        $logs[] = array($level, $msg);
    };
}

// ── 公共 fixture ──────────────────────────────────────

function buildBaseConfigRows() {
    return array(
        array('path' => 'xfe_logistic/gls_api/base_url',           'value' => 'https://api.gls.fr/v1'),
        array('path' => 'xfe_logistic/gls_api/username',           'value' => 'gls_user'),
        array('path' => 'xfe_logistic/gls_api/password',           'value' => '0:2:ENC=base64ciphertext=='),
        array('path' => 'xfe_logistic/gls_api/parcelpod_resource', 'value' => 'parcelpod'),
    );
}

function buildMigrator(MockConnection $conn, $enc = null) {
    $resource = new MockResource($conn);
    $encFactory = function () use ($enc) { return $enc ?: new MockEncryption(); };
    $dateNow = function () { return '2026-09-17 10:00:00'; };
    return new XFE_Logistic_Sql_Migrator_GlsCredentials($resource, $encFactory, $dateNow);
}

echo "\n=== Unit Test: GLS API credential migration (ADR 0020) ===\n\n";

// ── Test 1: 读 4 条 config rows ────────────────────────
echo "--- Test 1: reads 4 config rows ---\n";
$conn = new MockConnection();
$conn->configRows = buildBaseConfigRows();
$conn->lastInsertId = 100;
$migrator = buildMigrator($conn);
$logs = array();
$report = $migrator->migrate(false, buildLogger($logs));
ok(count($conn->inserted) === 2, "INSERT count = 2 (carrier seed + account insert)");
ok($report['inserted'] === 1, "report.inserted = 1");
ok($report['skipped'] === 0, "report.skipped = 0");
ok($report['seeded_carrier'] === true, "report.seeded_carrier = true");
ok(count($report['errors']) === 0, "no errors, errors: " . count($report['errors']));

// 检查 INSERT 的两条数据
$accountInsert = null;
$carrierInsert = null;
foreach ($conn->inserted as $i) {
    if (strpos($i['table'], 'carrier_account') !== false) $accountInsert = $i;
    if (strpos($i['table'], 'carrier') !== false && strpos($i['table'], 'account') === false) $carrierInsert = $i;
}
ok($carrierInsert !== null, "carrier seed INSERT happened");
ok($accountInsert !== null, "carrier_account INSERT happened");

if ($accountInsert) {
    $d = $accountInsert['data'];
    ok($d['username'] === 'gls_user', "username = gls_user");
    ok($d['endpoint_url'] === 'https://api.gls.fr/v1', "endpoint_url = https://api.gls.fr/v1");
    ok($d['password'] === 'plain_gls_pass', "password decrypted to plain");
    ok($d['status'] === 1, "status = 1");
    ok(strpos($d['note'], 'Migrated from xfe_logistic/gls_api') !== false, "note has migration marker");
    // custom_fields_json 包含 parcelpod_resource
    $cf = json_decode($d['custom_fields_json'], true);
    ok(is_array($cf) && isset($cf['parcelpod_resource']), "custom_fields_json has parcelpod_resource");
    ok($cf['parcelpod_resource'] === 'parcelpod', "parcelpod_resource = parcelpod");
    ok(isset($cf['_migration_source']), "custom_fields_json has _migration_source marker");
    ok($cf['_migration_source'] === 'xfe_logistic/gls_api', "_migration_source = xfe_logistic/gls_api");
}

// ── Test 2: 幂等检查 (Carrier account 已存在 username+endpoint_url) ──
echo "\n--- Test 2: idempotency (account exists) ---\n";
$conn = new MockConnection();
$conn->configRows = buildBaseConfigRows();
$conn->carrierRows = array(array('entity_id' => 7, 'code' => 'gls', 'name' => 'GLS'));
$conn->accountRows = array(array(
    'account_id'   => 99,
    'carrier_id'   => 7,
    'username'     => 'gls_user',
    'endpoint_url' => 'https://api.gls.fr/v1',
));
$migrator = buildMigrator($conn);
$logs = array();
$report = $migrator->migrate(false, buildLogger($logs));
ok(count($conn->inserted) === 0, "INSERT count = 0 (idempotent)");
ok($report['inserted'] === 0, "report.inserted = 0");
ok($report['skipped'] === 1, "report.skipped = 1");
ok($report['seeded_carrier'] === false, "did not seed carrier (already exists)");

// ── Test 3: dry-run 不写 DB ──────────────────────────
echo "\n--- Test 3: dry-run mode ---\n";
$conn = new MockConnection();
$conn->configRows = buildBaseConfigRows();
$conn->lastInsertId = 200;
$migrator = buildMigrator($conn);
$logs = array();
$report = $migrator->migrate(true, buildLogger($logs));
ok(count($conn->inserted) === 0, "dry-run: INSERT count = 0");
ok($report['inserted'] === 0, "dry-run: report.inserted = 0 (not counted)");
ok($report['seeded_carrier'] === false, "dry-run: did not actually seed");

// 验证 logger 收到了 DRY-RUN 日志
$dryRunLogged = false;
foreach ($logs as $l) {
    if ($l[0] === 'DRY-RUN' && strpos($l[1], 'Will INSERT') !== false) { $dryRunLogged = true; break; }
}
ok($dryRunLogged, "logger received DRY-RUN messages");

// ── Test 4: Carrier 主档已存在 → 不 seed ──────────────
echo "\n--- Test 4: carrier already exists, no seed ---\n";
$conn = new MockConnection();
$conn->configRows = buildBaseConfigRows();
$conn->carrierRows = array(array('entity_id' => 42, 'code' => 'gls', 'name' => 'GLS'));
$conn->lastInsertId = 300;
$migrator = buildMigrator($conn);
$logs = array();
$report = $migrator->migrate(false, buildLogger($logs));
ok($report['seeded_carrier'] === false, "did not seed (carrier exists)");
ok($report['inserted'] === 1, "inserted account");
// 应该只有 1 个 INSERT (account),不应该有 carrier
$carrierInsert = null;
foreach ($conn->inserted as $i) {
    if (strpos($i['table'], 'carrier_account') === false) { $carrierInsert = $i; break; }
}
ok($carrierInsert === null, "no carrier INSERT (already exists)");

// ── Test 5: 缺 base_url → error ──────────────────────
echo "\n--- Test 5: missing base_url returns error ---\n";
$conn = new MockConnection();
$conn->configRows = array(
    array('path' => 'xfe_logistic/gls_api/username', 'value' => 'u'),
    array('path' => 'xfe_logistic/gls_api/password', 'value' => 'p'),
    array('path' => 'xfe_logistic/gls_api/parcelpod_resource', 'value' => 'parcelpod'),
);
$migrator = buildMigrator($conn);
$logs = array();
$report = $migrator->migrate(false, buildLogger($logs));
ok(count($conn->inserted) === 0, "no INSERT on missing base_url");
ok(count($report['errors']) === 1, "1 error reported");
ok(strpos($report['errors'][0], 'base_url') !== false, "error mentions base_url");

// ── Test 6: decryption failure → error ──────────────
echo "\n--- Test 6: decryption failure returns error ---\n";
$conn = new MockConnection();
$conn->configRows = buildBaseConfigRows();
class FailingEncryption {
    public function decrypt($v) { throw new Exception('bad cipher'); }
}
$migrator = buildMigrator($conn, new FailingEncryption());
$logs = array();
$report = $migrator->migrate(false, buildLogger($logs));
ok(count($conn->inserted) === 0, "no INSERT on decryption failure");
ok(count($report['errors']) === 1, "1 error reported");
ok(strpos($report['errors'][0], 'Decryption failed') !== false, "error mentions Decryption failed");

// ── Test 7: Resource null → error ────────────────────
echo "\n--- Test 7: resource null returns error ---\n";
$migrator = new XFE_Logistic_Sql_Migrator_GlsCredentials(null);
$logs = array();
$report = $migrator->migrate(false, buildLogger($logs));
ok(count($report['errors']) === 1, "1 error reported when resource null");
ok(strpos($report['errors'][0], 'Resource not available') !== false, "error mentions Resource not available");

// ── Test 8: empty config rows → no-op ────────────────
echo "\n--- Test 8: empty config rows ---\n";
$conn = new MockConnection();
$conn->configRows = array();
$migrator = buildMigrator($conn);
$logs = array();
$report = $migrator->migrate(false, buildLogger($logs));
ok(count($conn->inserted) === 0, "no INSERT on empty config");
ok($report['inserted'] === 0, "report.inserted = 0");
ok($report['skipped'] === 0, "report.skipped = 0");
ok(count($report['errors']) === 0, "no errors");

// ── Test 9: logReport 不抛异常 ────────────────────────
echo "\n--- Test 9: logReport is safe ---\n";
$conn = new MockConnection();
$conn->configRows = buildBaseConfigRows();
$migrator = buildMigrator($conn);
$report = array('inserted' => 1, 'skipped' => 0, 'seeded_carrier' => true, 'errors' => array());
$logException = false;
try {
    $migrator->logReport($report, false);
} catch (Exception $e) {
    $logException = true;
}
ok(!$logException, "logReport does not throw exception");

echo "\n========================================\n";
echo "Total: {$assertions} assertions, {$failures} failures\n";
echo "========================================\n";

exit($failures > 0 ? 1 : 0);
