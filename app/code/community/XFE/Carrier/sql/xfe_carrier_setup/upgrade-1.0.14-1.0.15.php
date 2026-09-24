<?php
/**
 * Upgrade 1.0.14 -> 1.0.15
 *
 * 1) Add `xfe_carrier_custom_attribute` (global attribute-definition
 *    table) for central management of custom fields across 4 entity
 *    types (carrier / account / ftp_account / logo).
 *
 * 2) Add `custom_fields_json` to BOTH `xfe_carrier_carrier` and
 *    `xfe_carrier_carrier_logo` so these 2 entities can also persist
 *    custom field values. (carrier_account and carrier_ftp_account
 *    already got this column in 1.0.14.)
 *
 * Design:
 *   - entity_type ∈ { carrier, account, ftp_account, logo }
 *   - unique key (entity_type, field_key, is_active) — must include
 *     is_active so a soft-deleted row + a freshly-inserted row with
 *     the same (entity_type, field_key) do not collide.
 *   - secondary index (entity_type, is_active) for hot read path
 *     "active defs by category".
 *   - No backfill: existing rows are NULL/empty, treated as "no custom
 *     fields" by the application.
 *
 * Idempotent: re-running this script on a database that already has the
 * table/columns is a no-op.
 *
 * Reference: docs/architecture/carrier-global-custom-field-defs.md
 *            docs/architecture/decisions/0006-custom-attribute-management.md
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

// ---------------------------------------------------------------------------
// 1) Create xfe_carrier_custom_attribute
// ---------------------------------------------------------------------------
$defTable = $installer->getTable('xfe_carrier/custom_attribute');

if (!$connection->isTableExists($defTable)) {
    $connection->query(sprintf(
        'CREATE TABLE %s (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT
                                COMMENT "PK",
            entity_type     VARCHAR(16)  NOT NULL
                                COMMENT "carrier | account | ftp_account | logo",
            field_key       VARCHAR(64)  NOT NULL
                                COMMENT "field key, snake_case 1~64 chars",
            label           VARCHAR(64)  NOT NULL
                                COMMENT "display label",
            field_type      VARCHAR(16)  NOT NULL
                                COMMENT "text | number | select | multiselect | boolean",
            options_csv     TEXT NULL DEFAULT NULL
                                COMMENT "options, comma-separated, for select/multiselect",
            default_value   TEXT NULL DEFAULT NULL
                                COMMENT "default value, JSON-serialized",
            is_required     TINYINT(1)   NOT NULL DEFAULT 0
                                COMMENT "required flag, save-time enforced",
            is_active       TINYINT(1)   NOT NULL DEFAULT 1
                                COMMENT "soft-delete flag, 0=inactive",
            sort_order      INT NOT NULL DEFAULT 0
                                COMMENT "display order in dropdown",
            description     TEXT NULL DEFAULT NULL
                                COMMENT "field description / hint",
            created_at      DATETIME NULL DEFAULT NULL,
            updated_at      DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_entity_type_field_key_active
                (entity_type, field_key, is_active),
            KEY idx_entity_type_active (entity_type, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
          COMMENT="Custom attribute global definitions (4 entity types)"',
        $defTable
    ));
}

// ---------------------------------------------------------------------------
// 2) Add custom_fields_json to xfe_carrier_carrier
// ---------------------------------------------------------------------------
$carrierTable = $installer->getTable('xfe_carrier/carrier');
if (!$connection->tableColumnExists($carrierTable, 'custom_fields_json')) {
    $connection->query(sprintf(
        'ALTER TABLE %s '
        . 'ADD COLUMN custom_fields_json TEXT NULL DEFAULT NULL '
        . "COMMENT 'Custom field values, JSON object {key:{label,type,value,options?},...}' "
        . 'AFTER note',
        $carrierTable
    ));
}

// ---------------------------------------------------------------------------
// 3) Add custom_fields_json to xfe_carrier_carrier_logo
// ---------------------------------------------------------------------------
$logoTable = $installer->getTable('xfe_carrier/carrier_logo');
if (!$connection->tableColumnExists($logoTable, 'custom_fields_json')) {
    $connection->query(sprintf(
        'ALTER TABLE %s '
        . 'ADD COLUMN custom_fields_json TEXT NULL DEFAULT NULL '
        . "COMMENT 'Custom field values, JSON object {key:{label,type,value,options?},...}' "
        . 'AFTER sort_order',
        $logoTable
    ));
}

$installer->endSetup();
