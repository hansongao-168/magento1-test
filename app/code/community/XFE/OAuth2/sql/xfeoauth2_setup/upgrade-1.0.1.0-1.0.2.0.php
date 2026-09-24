<?php
/**
 * XFE_OAuth2 upgrade 1.0.1 -> 1.0.2
 *
 * Adds a recoverable encrypted copy of the client secret.
 *
 * Background:
 *   - `client_secret` is stored as a bcrypt hash (one-way). It is used for
 *     credential verification only and can never be recovered as plaintext.
 *   - Customers/admins often need to view the secret they configured. To
 *     support a "Show Secret" button we store a reversible copy encrypted
 *     with Magento's core/encrypt helper (AES, key from app/etc/local.xml).
 *
 * Schema change:
 *   xfe_oauth2_client
 *     client_secret_encrypted  TEXT  NULL  reversible encrypted plaintext
 *
 * The script is idempotent: re-running it on an already-upgraded database is
 * a no-op (column check via information_schema).
 *
 * @category   Community
 * @package    XFE_OAuth2
 * @version    1.0.2
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

if (!$columnExists($clientTable, 'client_secret_encrypted')) {
    // ALTER on an existing table: use raw DDL to avoid addColumn() issues on
    // older Magento 1.9.
    $connection->raw_query(sprintf(
        'ALTER TABLE %s ADD COLUMN `client_secret_encrypted` TEXT NULL COMMENT %s',
        $connection->quoteIdentifier($clientTable),
        $connection->quote('Encrypted plaintext client secret (recoverable)')
    ));
}

$installer->endSetup();
