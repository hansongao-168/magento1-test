<?php
/**
 * Upgrade 1.0.9 -> 1.0.10
 *
 * Add the FTP account management feature: a dedicated
 * xfe_carrier_ftp_account table that parallels xfe_carrier_account, and a
 * nullable ftp_account_id column on xfe_carrier_carrier_rule so that
 * rules can be bound to a specific FTP account (1:N, just like the
 * existing account -> rules linkage that was introduced in 1.0.7).
 *
 * New table:
 *   - xfe_carrier_ftp_account: stores one FTP / SFTP / FTPS endpoint
 *     per (carrier_id) - account_name is used as the display label, the
 *     remaining columns capture connection details.
 *   - protocol ENUM('ftp','sftp','ftps') selects the wire protocol;
 *     port defaults to 21 but can be overridden (22 for SFTP, etc.).
 *   - mode ENUM('passive','active') only applies to plain FTP; we
 *     persist it for completeness even on SFTP/FTPS rows.
 *
 * Schema change on existing rule table:
 *   - ftp_account_id INT UNSIGNED NULL with FK to the new table
 *     (ON DELETE SET NULL so deleting an FTP account does not delete
 *     its rules; the rule simply becomes un-bound).
 *
 * All steps are idempotent / guarded so the upgrade can be re-run.
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

// ---------- 1. New ftp_account table ----------------------------------------
$ftpAccountTable = $installer->getTable('xfe_carrier/carrier_ftp_account');

if (!$connection->isTableExists($ftpAccountTable)) {
    $connection->query(sprintf(
        'CREATE TABLE %s ('
        . 'ftp_account_id INT UNSIGNED NOT NULL AUTO_INCREMENT, '
        . 'carrier_id INT UNSIGNED NOT NULL, '
        . 'account_name VARCHAR(255) NOT NULL, '
        . 'account_no VARCHAR(128) NULL, '
        . "protocol ENUM('ftp','sftp','ftps') NOT NULL DEFAULT 'ftp', "
        . 'host VARCHAR(255) NOT NULL, '
        . 'port INT NOT NULL DEFAULT 21, '
        . 'username VARCHAR(128) NULL, '
        . 'password VARCHAR(255) NULL, '
        . 'remote_path VARCHAR(512) NULL, '
        . "mode ENUM('passive','active') NOT NULL DEFAULT 'passive', "
        . "encoding VARCHAR(16) NOT NULL DEFAULT 'UTF-8', "
        . 'status TINYINT(1) NOT NULL DEFAULT 1, '
        . 'sort_order INT NOT NULL DEFAULT 0, '
        . 'note TEXT NULL, '
        . 'created_at DATETIME NULL DEFAULT NULL, '
        . 'updated_at DATETIME NULL DEFAULT NULL, '
        . 'PRIMARY KEY (ftp_account_id), '
        . 'INDEX %s (carrier_id), '
        . 'INDEX %s (carrier_id, status, updated_at), '
        . 'CONSTRAINT %s FOREIGN KEY (carrier_id) '
        . 'REFERENCES %s (entity_id) ON DELETE CASCADE ON UPDATE CASCADE'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT="FTP账号管理"',
        $ftpAccountTable,
        $installer->getIdxName('xfe_carrier/carrier_ftp_account', array('carrier_id')),
        $installer->getIdxName('xfe_carrier/carrier_ftp_account', array('carrier_id', 'status', 'updated_at')),
        $installer->getFkName('xfe_carrier/carrier_ftp_account', 'carrier_id', 'xfe_carrier/carrier', 'entity_id'),
        $installer->getTable('xfe_carrier/carrier')
    ));
}

// ---------- 2. ftp_account_id column on carrier_rule ------------------------
$ruleTable = $installer->getTable('xfe_carrier/carrier_rule');

if (!$connection->tableColumnExists($ruleTable, 'ftp_account_id')) {
    $connection->query(sprintf(
        'ALTER TABLE %s ADD COLUMN ftp_account_id INT UNSIGNED NULL DEFAULT NULL '
        . 'COMMENT \'Optional: bind this rule to a specific FTP account (1:N from FTP account to rules)\' '
        . 'AFTER account_id',
        $ruleTable
    ));

    $idxName = $installer->getIdxName('xfe_carrier/carrier_rule', array('ftp_account_id'));
    if (!$hasIndex($connection, $ruleTable, $idxName)) {
        $connection->addIndex($ruleTable, $idxName, array('ftp_account_id'));
    }

    $fkName = $installer->getFkName(
        'xfe_carrier/carrier_rule',
        'ftp_account_id',
        'xfe_carrier/carrier_ftp_account',
        'ftp_account_id'
    );
    // addForeignKey only emits an ALTER TABLE if the FK does not exist; it
    // does not throw when re-run, so we can call it unconditionally.
    $connection->addForeignKey(
        $fkName,
        $ruleTable,
        'ftp_account_id',
        $ftpAccountTable,
        'ftp_account_id',
        'SET NULL',
        'CASCADE'
    );
}

$installer->endSetup();