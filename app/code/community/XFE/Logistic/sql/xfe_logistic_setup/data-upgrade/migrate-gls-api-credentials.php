<?php
/**
 * GLS API 凭据迁移脚本 (Class 风格,可测试)。
 *
 * 把 Logistic 模块 system config 中的 GLS API 凭据(`xfe_logistic/gls_api/*`)
 * 迁移到 `xfe_carrier_account` 表,让管理员只在 Carrier 后台配置 GLS 账号。
 *
 * **设计原则**:
 * - 文件名 `migrate-gls-api-credentials.php`(非 `install-*` / `upgrade-*`),
 *   不会被 Mage_Core_Model_Resource_Setup 自动扫描运行。
 * - 管理员必须通过 CLI 入口(`shell/migrate-gls-api-credentials.php`)显式触发。
 * - 幂等: 检查 username+endpoint_url 双键匹配,已存在则跳过。
 * - dry-run: 默认行为可切换,不写 DB。
 * - 不动 Logistic system config: 保留作 fallback 安全网。
 *
 * Class 风格而非 function 风格: 依赖通过构造函数注入,便于单元测试 mock。
 *
 * 关联文档:
 *   - docs/architecture/decisions/0020-migrate-gls-api-credentials.md
 *   - docs/architecture/migrate-gls-api-credentials.md
 */

class XFE_Logistic_Sql_Migrator_GlsCredentials
{
    /** @var mixed */ private $_resource;
    /** @var callable */ private $_encryptionFactory;
    /** @var callable */ private $_dateNow;

    public function __construct(
        $resource = null,
        ?callable $encryptionFactory = null,
        ?callable $dateNow = null
    ) {
        // 默认从 Mage 取
        $this->_resource = $resource;
        $this->_encryptionFactory = $encryptionFactory ?: function () {
            return Mage::getModel('core/encryption');
        };
        $this->_dateNow = $dateNow ?: function () {
            return Varien_Date::now();
        };
    }

    /**
     * 执行迁移核心逻辑。
     *
     * @param bool $dryRun true = 仅打印计划不写 DB
     * @param callable|null $logger 接受 (string $level, string $msg) 的回调
     * @return array 迁移报告
     */
    public function migrate($dryRun = false, $logger = null)
    {
        if ($logger === null) {
            $logger = function ($level, $msg) {
                fwrite(STDOUT, "[{$level}] {$msg}\n");
            };
        }

        $report = array(
            'inserted'         => 0,
            'skipped'          => 0,
            'seeded_carrier'   => false,
            'errors'           => array(),
        );

        // 前置条件 1: Resource 可用
        if ($this->_resource === null) {
            $report['errors'][] = 'Resource not available';
            $logger('ERROR', $report['errors'][0]);
            return $report;
        }

        $write = $this->_resource->getConnection('core_write');
        $carrierTable = $this->_resource->getTableName('xfe_carrier/carrier');
        $accountTable = $this->_resource->getTableName('xfe_carrier/carrier_account');
        $configTable  = $this->_resource->getTableName('core_config_data');

        if (!$write->isTableExists($carrierTable)) {
            $report['errors'][] = "Carrier table not exists: {$carrierTable}";
            $logger('ERROR', $report['errors'][0]);
            return $report;
        }

        // ── 1. 读 Logistic system config ─────────────────
        $rows = $this->_fetchConfigRows($write, $configTable);
        $logger('INFO', 'Reading core_config_data: xfe_logistic/gls_api/*');
        $logger('INFO', 'Found ' . count($rows) . ' config rows');

        if (count($rows) === 0) {
            $logger('INFO', 'No config rows found; nothing to migrate.');
            return $report;
        }

        $config = array();
        foreach ($rows as $row) {
            $key = substr($row['path'], strlen('xfe_logistic/gls_api/'));
            $config[$key] = $row['value'];
        }

        $endpointUrl = isset($config['base_url']) ? (string)$config['base_url'] : '';
        $username    = isset($config['username']) ? (string)$config['username'] : '';
        $passwordEnc = isset($config['password']) ? (string)$config['password'] : '';
        $parcelPod   = isset($config['parcelpod_resource']) ? (string)$config['parcelpod_resource'] : '';

        if ($endpointUrl === '' || $username === '' || $passwordEnc === '') {
            $report['errors'][] = 'Missing required config: base_url / username / password';
            $logger('ERROR', $report['errors'][0]);
            return $report;
        }

        // ── 2. 解密 password ─────────────────
        try {
            $encryptionFactory = $this->_encryptionFactory;
            $encryption = $encryptionFactory();
            $passwordPlain = (string)$encryption->decrypt($passwordEnc);
            $logger('INFO', 'Password decrypted (length: ' . strlen($passwordPlain) . ' chars)');
        } catch (Exception $e) {
            $report['errors'][] = 'Decryption failed: ' . $e->getMessage();
            $logger('ERROR', $report['errors'][0]);
            return $report;
        }

        // ── 3. 检查 / seed Carrier 主档 ─────────────────
        $carrierId = $this->_resolveOrSeedCarrier($write, $carrierTable, $dryRun, $logger, $report);
        if ($carrierId === 0 && !$dryRun) {
            // resolveOrSeedCarrier 已经记录 error
            return $report;
        }

        // ── 4. 幂等检查 ─────────────────
        if (!$dryRun) {
            $existing = $this->_findExistingAccount($write, $accountTable, $carrierId, $username, $endpointUrl);
            if ($existing) {
                $report['skipped']++;
                $logger('INFO', "Skipped: account already exists (account_id=" . $existing['account_id'] . ")");
                return $report;
            }
        }

        // ── 5. INSERT Carrier account ─────────────────
        $customFieldsJson = json_encode(array(
            'parcelpod_resource' => $parcelPod,
            '_migration_source' => 'xfe_logistic/gls_api',
            '_migration_date'   => date('Y-m-d H:i:s'),
        ), JSON_UNESCAPED_SLASHES);

        $accountData = array(
            'carrier_id'   => $carrierId,
            'account_name' => 'GLS API (migrated)',
            'username'     => $username,
            'password'     => $passwordPlain,
            'endpoint_url' => $endpointUrl,
            'status'       => 1,
            'sort_order'   => 10,
            'note'         => 'Migrated from xfe_logistic/gls_api/* system config (ADR 0020)',
            'custom_fields_json' => $customFieldsJson,
            'created_at'   => ($this->_dateNow)(),
            'updated_at'   => ($this->_dateNow)(),
        );

        if ($dryRun) {
            $logger('DRY-RUN', 'Will INSERT xfe_carrier_account:');
            $logger('DRY-RUN', "  username       = {$username}");
            $logger('DRY-RUN', "  endpoint_url   = {$endpointUrl}");
            $logger('DRY-RUN', '  password       = ******** (length: ' . strlen($passwordPlain) . ')');
            $logger('DRY-RUN', "  custom_fields  = {$customFieldsJson}");
            $report['inserted'] = 0;
        } else {
            try {
                $write->insert($accountTable, $accountData);
                $newId = (int)$write->lastInsertId($accountTable);
                $report['inserted']++;
                $logger('INFO', "Inserted xfe_carrier_account: account_id={$newId}");
            } catch (Exception $e) {
                $report['errors'][] = 'Insert failed: ' . $e->getMessage();
                $logger('ERROR', $report['errors'][0]);
                return $report;
            }
        }

        return $report;
    }

    /**
     * 写报告到 var/log。
     */
    public function logReport(array $report, $dryRun)
    {
        $logDir = $this->_resource ? $this->_resource->getBaseDir('log') : null;
        if (!$logDir || !is_dir($logDir)) {
            return;
        }
        try {
            $logFile = $logDir . '/migrate-gls-api-credentials.log';
            $line = sprintf(
                "[%s] dry-run=%s inserted=%d skipped=%d seeded=%s errors=%d\n",
                date('Y-m-d H:i:s'),
                $dryRun ? 'yes' : 'no',
                $report['inserted'],
                $report['skipped'],
                $report['seeded_carrier'] ? 'yes' : 'no',
                count($report['errors'])
            );
            file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            // log 失败不影响主流程
        }
    }

    // ── Protected helpers (overridable in tests) ─────────

    protected function _fetchConfigRows($write, $configTable)
    {
        $select = $write->select()
            ->from($configTable, array('path', 'value'))
            ->where('path LIKE ?', 'xfe_logistic/gls_api/%');
        return $write->fetchAll($select);
    }

    protected function _resolveOrSeedCarrier($write, $carrierTable, $dryRun, $logger, array &$report)
    {
        $carrierRow = $write->fetchRow(
            $write->select()->from($carrierTable)->where('code = ?', 'gls')
        );

        if ($carrierRow) {
            $carrierId = (int)$carrierRow['entity_id'];
            $logger('INFO', "Found existing carrier: code='gls', entity_id={$carrierId}");
            return $carrierId;
        }

        if ($dryRun) {
            $logger('DRY-RUN', "Will INSERT xfe_carrier: code='gls', name='GLS'");
            return 0;
        }

        try {
            $write->insert($carrierTable, array(
                'name'       => 'GLS',
                'code'       => 'gls',
                'status'     => 1,
                'sort_order' => 10,
                'note'       => 'Seeded by GLS API credential migration (ADR 0020)',
                'created_at' => ($this->_dateNow)(),
                'updated_at' => ($this->_dateNow)(),
            ));
            $carrierId = (int)$write->lastInsertId($carrierTable);
            $report['seeded_carrier'] = true;
            $logger('INFO', "Seeded carrier: code='gls', entity_id={$carrierId}");
            return $carrierId;
        } catch (Exception $e) {
            $report['errors'][] = 'Carrier seed failed: ' . $e->getMessage();
            $logger('ERROR', $report['errors'][0]);
            return 0;
        }
    }

    protected function _findExistingAccount($write, $accountTable, $carrierId, $username, $endpointUrl)
    {
        return $write->fetchRow(
            $write->select()->from($accountTable)
                ->where('carrier_id = ?', $carrierId)
                ->where('username = ?', $username)
                ->where('endpoint_url = ?', $endpointUrl)
        );
    }
}

// 兼容旧的函数式 API (CLI 入口用)
function migrate_gls_api_credentials($dryRun = false, $logger = null)
{
    if (!class_exists('Mage', false)) {
        fwrite(STDERR, "[ERROR] Mage class not available\n");
        return array(
            'inserted' => 0, 'skipped' => 0, 'seeded_carrier' => false,
            'errors' => array('Mage not available (run via shell/migrate-gls-api-credentials.php)'),
        );
    }
    $resource = Mage::getSingleton('core/resource');
    $migrator = new XFE_Logistic_Sql_Migrator_GlsCredentials($resource);
    return $migrator->migrate($dryRun, $logger);
}

function migrate_gls_api_credentials_log_report(array $report, $dryRun)
{
    if (!class_exists('Mage', false)) {
        return;
    }
    $resource = Mage::getSingleton('core/resource');
    $migrator = new XFE_Logistic_Sql_Migrator_GlsCredentials($resource);
    $migrator->logReport($report, $dryRun);
}
