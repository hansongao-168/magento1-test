<?php
/**
 * Upgrade 1.0.13 -> 1.0.14
 *
 * Add `custom_fields_json` to both carrier-account tables so admins can
 * attach carrier-specific key/value pairs (similar to EAV, but stored as
 * one JSON object per row).
 *
 * Design:
 *   - custom_fields_json: TEXT NULL DEFAULT NULL
 *     Holds a JSON object `{key:{label,type,value,options?}, ...}`. The
 *     application layer (XFE_Carrier_Domain_CustomFieldCodec) is the
 *     single source of truth for shape; the DB is intentionally
 *     schema-less.
 *   - No index on this column: it is read in full by the application
 *     after loading the row, never used in a WHERE / ORDER BY clause.
 *     Indexing JSON content would require MySQL 5.7+ generated columns
 *     and is out of scope.
 *   - No backfill: existing rows are NULL, treated as "no custom fields"
 *     by the application.
 *
 * Idempotent: re-running this script on a database that already has the
 * columns is a no-op.
 *
 * Reference: docs/architecture/carrier-account-custom-fields.md,
 *            docs/architecture/decisions/0004-...md
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

$tables = array(
    $installer->getTable('xfe_carrier/carrier_account'),
    $installer->getTable('xfe_carrier/carrier_ftp_account'),
);

foreach ($tables as $table) {
    if (!$connection->tableColumnExists($table, 'custom_fields_json')) {
        $connection->query(sprintf(
            'ALTER TABLE %s '
            . 'ADD COLUMN custom_fields_json TEXT NULL DEFAULT NULL '
            . "COMMENT 'Key-value custom fields, JSON object {key:{label,type,value,options?},...}' "
            . 'AFTER note',
            $table
        ));
    }
}

$installer->endSetup();
