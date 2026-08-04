<?php
/**
 * Upgrade 1.0.8 -> 1.0.9
 *
 * Add a `priority` column to xfe_carrier_carrier_rule so the Resolver
 * can rank matching rules by priority (higher wins) instead of
 * falling back to sort_order alone. This is the change that lets the
 * printed label pick the most recently updated rule (and a most
 * recently updated account/logo when no rule matches) reliably.
 *
 * - priority: INT, default 0. Existing rules start at 0 so the new
 *             ordering is a superset of the old behavior when nothing
 *             has been configured.
 * - updated_at on xfe_carrier_carrier_logo: the logo table didn't
 *             have this column before, but the default-resolver
 *             needs it to pick the most recently saved logo. The
 *             default is NULL for existing rows (falls back to a
 *             sort_order + logo_id tie-breaker).
 * - A composite index on (carrier_id, status, module_code, priority,
 *   updated_at) keeps the resolver's WHERE+ORDER lookup cheap even
 *   when a carrier has hundreds of rules.
 *
 * Also: bump the schema version so this script runs once.
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

/**
 * Varien_Db_Adapter_Pdo_Mysql exposes index introspection only via
 * getIndexList() (keys are UPPERCASE index names); there is no
 * indexExists() method. Mirror the canonical idiom used by
 * Varien_Db_Adapter_Pdo_Mysql::dropKey().
 *
 * @param Varien_Db_Adapter_Pdo_Mysql $conn
 * @param string $tableName
 * @param string $indexName
 * @return bool
 */
$hasIndex = function ($conn, $tableName, $indexName) {
    $indexList = $conn->getIndexList($tableName);
    return isset($indexList[strtoupper($indexName)]);
};

// ---------- 1. priority column on carrier_rule -----------------------------
$ruleTable = $installer->getTable('xfe_carrier/carrier_rule');

if (!$connection->tableColumnExists($ruleTable, 'priority')) {
    $connection->query(sprintf(
        'ALTER TABLE %s ADD COLUMN priority INT NOT NULL DEFAULT 0 '
        . 'COMMENT \'Resolver priority, higher wins\' AFTER sort_order',
        $ruleTable
    ));
}

// NOTE: use addIndex() rather than a raw "ALTER TABLE ... ADD INDEX %s"
// query. getIdxName() returns an MD5 hash of (table, fields) that may start
// with a digit (e.g. 44E79FC3...); emitted unquoted that looks like a
// numeric literal to MySQL and raises SQLSTATE 42000 / 1064. addIndex()
// routes the name through quoteIdentifier() (backtick-quoting), and matches
// the idiom used in upgrade-1.0.4-1.0.5.php.
$idxName = $installer->getIdxName(
    'xfe_carrier/carrier_rule',
    array('carrier_id', 'status', 'module_code', 'priority', 'updated_at')
);
if (!$hasIndex($connection, $ruleTable, $idxName)) {
    $connection->addIndex(
        $ruleTable,
        $idxName,
        array('carrier_id', 'status', 'module_code', 'priority', 'updated_at')
    );
}

// ---------- 2. updated_at on carrier_logo ----------------------------------
$logoTable = $installer->getTable('xfe_carrier/carrier_logo');

if (!$connection->tableColumnExists($logoTable, 'updated_at')) {
    $connection->query(sprintf(
        'ALTER TABLE %s ADD COLUMN updated_at DATETIME NULL DEFAULT NULL '
        . 'COMMENT \'Last update timestamp\' AFTER created_at',
        $logoTable
    ));
}

$logoIdx = $installer->getIdxName(
    'xfe_carrier/carrier_logo',
    array('carrier_id', 'updated_at')
);
if (!$hasIndex($connection, $logoTable, $logoIdx)) {
    $connection->addIndex(
        $logoTable,
        $logoIdx,
        array('carrier_id', 'updated_at')
    );
}

// ---------- 3. updated_at on carrier_account (defensive) -------------------
// The account table already has updated_at from 1.0.1, but ensure an index
// that supports the new resolver lookup ("most recent, status=1").
$accountTable   = $installer->getTable('xfe_carrier/carrier_account');
$accountIdxName = $installer->getIdxName(
    'xfe_carrier/carrier_account',
    array('carrier_id', 'status', 'updated_at')
);
if (!$hasIndex($connection, $accountTable, $accountIdxName)) {
    $connection->addIndex(
        $accountTable,
        $accountIdxName,
        array('carrier_id', 'status', 'updated_at')
    );
}

$installer->endSetup();
