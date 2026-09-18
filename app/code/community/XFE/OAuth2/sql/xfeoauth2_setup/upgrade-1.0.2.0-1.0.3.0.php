<?php
/**
 * XFE_OAuth2 upgrade 1.0.2 -> 1.0.3
 *
 * Adds a client_secret expiration timestamp + a last-rotated-at audit column
 * to xfe_oauth2_client, then back-fills existing rows.
 *
 * Background (see ADR 0007):
 *   - Prior to 1.0.3, xfe_oauth2_client.client_secret was effectively immortal:
 *     either bcrypt (non-recoverable) or AES (recoverable via "Show Secret").
 *     There was no expiration, no rotation, no audit.
 *   - 1.0.3 introduces:
 *       client_secret_expires_at        DATETIME  NULL  when this secret stops being valid
 *       client_secret_last_rotated_at   DATETIME  NULL  when the secret was last rotated
 *     plus a system-configurable TTL (default 90 days, see Helper::getDefaultSecretTtlDays()).
 *
 * Back-fill strategy (non-destructive):
 *   - Rows where client_secret_expires_at IS NULL get:
 *       client_secret_expires_at      = DATE_ADD(IFNULL(created_at, NOW()), INTERVAL 90 DAY)
 *       client_secret_last_rotated_at = IFNULL(created_at, NOW())
 *   - The existing client_secret / client_secret_encrypted columns are NOT touched.
 *
 * Idempotency:
 *   - Both ALTER TABLE statements are guarded by information_schema.COLUMNS so the
 *     script can be re-run on an already-upgraded database with no effect.
 *
 * @category   Community
 * @package    XFE_OAuth2
 * @version    1.0.3
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

/**
 * Helper: does a column exist on a table? Uses information_schema directly to
 * avoid relying on describeTable() which is cached in the DDL cache primed at
 * startSetup().
 */
$columnExists = function ($tableName, $columnName) use ($connection) {
    $sql = sprintf(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = '%s'
           AND COLUMN_NAME  = '%s'",
        addslashes($tableName),
        addslashes($columnName)
    );
    return (bool)$connection->fetchOne($sql);
};

$clientTable = $installer->getTable('xfeoauth2/client');

/* 1. ADD COLUMN client_secret_expires_at */
if (!$columnExists($clientTable, 'client_secret_expires_at')) {
    // ALTER on an existing table: use raw DDL to avoid addColumn() issues on
    // older Magento 1.9.
    $connection->raw_query(sprintf(
        'ALTER TABLE %s ADD COLUMN `client_secret_expires_at` DATETIME NULL COMMENT %s',
        $connection->quoteIdentifier($clientTable),
        $connection->quote('When the current client_secret expires (UTC)')
    ));
}

/* 2. ADD COLUMN client_secret_last_rotated_at */
if (!$columnExists($clientTable, 'client_secret_last_rotated_at')) {
    $connection->raw_query(sprintf(
        'ALTER TABLE %s ADD COLUMN `client_secret_last_rotated_at` DATETIME NULL COMMENT %s',
        $connection->quoteIdentifier($clientTable),
        $connection->quote('When client_secret was last regenerated (UTC, audit)')
    ));
}

/* 3. Back-fill: existing NULL rows get expires_at = created_at + 90d,
 *                last_rotated_at = created_at.
 *    IFNULL(created_at, NOW()) is a defensive fallback: created_at is NOT NULL
 *    in the original DDL, but if a legacy manual INSERT bypassed that, we
 *    still want a sensible value instead of a NULL TTL.
 *
 *    The UPDATE is also idempotent: it only touches rows where expires_at
 *    IS NULL, so re-running this script on a fresh install (where expires_at
 *    was just populated) is a no-op.
 */
$connection->raw_query(sprintf(
    'UPDATE %s
        SET client_secret_expires_at      = DATE_ADD(IFNULL(created_at, NOW()), INTERVAL 90 DAY),
            client_secret_last_rotated_at = IFNULL(created_at, NOW())
      WHERE client_secret_expires_at IS NULL',
    $connection->quoteIdentifier($clientTable)
));

$installer->endSetup();
