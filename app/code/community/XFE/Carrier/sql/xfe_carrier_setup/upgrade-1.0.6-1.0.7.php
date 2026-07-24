<?php
/**
 * Upgrade 1.0.6 -> 1.0.7
 *
 * Move the account <-> rule linkage from "account.rule_id (1:1)"
 * to "rule.account_id (1:N)", so that the inline rule editor on the
 * Account Edit page can attach MULTIPLE rules to a single account.
 *
 * Steps:
 *   1. Add account_id column to xfe_carrier_carrier_rule (nullable FK).
 *   2. Backfill: for every account that has rule_id set, write the
 *      account_id back onto the rule so the existing 1:1 relationship is
 *      preserved as a 1:1 row (still legal because rule.account_id is
 *      just a single column, no uniqueness constraint).
 *   3. Drop rule_id from xfe_carrier_carrier_account - the column is
 *      now redundant and would only invite drift.
 *
 * All steps are idempotent / guarded so the upgrade can be re-run safely.
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$ruleTable    = $installer->getTable('xfe_carrier/carrier_rule');
$accountTable = $installer->getTable('xfe_carrier/carrier_account');
$connection   = $installer->getConnection();

// ---------- 1. Add account_id on rule -----------------------------------
if (!$connection->tableColumnExists($ruleTable, 'account_id')) {
    $connection->query(sprintf(
        'ALTER TABLE %s ADD COLUMN account_id INT UNSIGNED NULL DEFAULT NULL '
        . 'COMMENT \'Optional: bind this rule to a specific account (1:N from account to rules)\' '
        . 'AFTER carrier_id',
        $ruleTable
    ));
    $connection->query(sprintf(
        'ALTER TABLE %s ADD INDEX %s (account_id)',
        $ruleTable,
        $installer->getIdxName('xfe_carrier/carrier_rule', array('account_id'))
    ));
    $connection->query(sprintf(
        'ALTER TABLE %s ADD FOREIGN KEY %s '
        . '(account_id) REFERENCES %s (account_id) '
        . 'ON DELETE SET NULL ON UPDATE CASCADE',
        $ruleTable,
        $installer->getFkName('xfe_carrier/carrier_rule', 'account_id', 'xfe_carrier/carrier_account', 'account_id'),
        $accountTable
    ));
}

// ---------- 2. Backfill account_id from account.rule_id -----------------
// Only rows whose rule_id is still set and whose rule has account_id=NULL
// are touched; running this twice is a no-op.
$connection->query(sprintf(
    'UPDATE %s r '
    . 'JOIN %s a ON a.rule_id = r.rule_id '
    . 'SET r.account_id = a.account_id '
    . 'WHERE r.account_id IS NULL AND a.rule_id IS NOT NULL',
    $ruleTable,
    $accountTable
));

// ---------- 3. Drop the now-redundant account.rule_id -------------------
if ($connection->tableColumnExists($accountTable, 'rule_id')) {
    // Drop FK if present (the original installer did not add one, but
    // be defensive in case a downstream module added it).
    $fkName = $installer->getFkName('xfe_carrier/carrier_account', 'rule_id', 'xfe_carrier/carrier_rule', 'rule_id');
    try {
        $connection->query(sprintf(
            'ALTER TABLE %s DROP FOREIGN KEY %s',
            $accountTable,
            $fkName
        ));
    } catch (Exception $e) {
        // FK may not exist - swallow.
    }
    $connection->query(sprintf(
        'ALTER TABLE %s DROP INDEX %s',
        $accountTable,
        $installer->getIdxName('xfe_carrier/carrier_account', array('rule_id'))
    ));
    $connection->query(sprintf(
        'ALTER TABLE %s DROP COLUMN rule_id',
        $accountTable
    ));
}

$installer->endSetup();