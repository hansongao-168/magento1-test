# XFE Carrier — Database Migrations

This directory holds the schema-migration scripts for the `xfe_carrier_setup`
resource, plus a single consolidated snapshot of the final schema for
human reference.

## Files Magento runs (do not rename)

These files match Magento's `install-*` / `upgrade-*` naming convention and
are loaded by `Mage_Core_Model_Resource_Setup` in version order on every
fresh install / upgrade:

| File | Introduces / Changes |
|------|----------------------|
| `install-1.0.0.php` | `xfe_carrier_carrier`, `xfe_carrier_carrier_logo` |
| `upgrade-1.0.0-1.0.1.php` | `xfe_carrier_carrier_account`, `xfe_carrier_carrier_rule`, `xfe_carrier_rule_condition_group`, `xfe_carrier_rule_condition` |
| `upgrade-1.0.1-1.0.2.php` | Adds `label`/`logo_type`/`sort_order` to logo; drops `UNIQUE(carrier_id, size_type)` |
| `upgrade-1.0.2-1.0.3.php` | Adds `module_code` to rule |
| `upgrade-1.0.3-1.0.4.php` | Adds `rule_id` FK to account (later removed in 1.0.7) |
| `upgrade-1.0.4-1.0.5.php` | Adds `rule_id` FK to logo |
| `upgrade-1.0.5-1.0.6.php` | Adds `is_cancel_on_failure` to rule |
| `upgrade-1.0.6-1.0.7.php` | Moves account <-> rule to 1:N via `rule.account_id`; drops `account.rule_id` |
| `upgrade-1.0.7-1.0.8.php` | New `xfe_carrier_carrier_translation` |
| `upgrade-1.0.8-1.0.9.php` | Adds `priority` to rule; adds `updated_at` to logo; resolver indexes |
| `upgrade-1.0.9-1.0.10.php` | New `xfe_carrier_carrier_ftp_account`; adds `ftp_account_id` FK to rule |
| `upgrade-1.0.13-1.0.14.php` | Adds `custom_fields_json` (TEXT NULL) to both `xfe_carrier_carrier_account` and `xfe_carrier_carrier_ftp_account` for EAV-like key/value extension fields |
| `upgrade-1.0.14-1.0.15.php` | New `xfe_carrier_custom_attribute` table (central definition for 4 entity types: carrier / account / ftp_account / logo); adds `custom_fields_json` to both `xfe_carrier_carrier` and `xfe_carrier_carrier_logo` |

## Files Magento ignores (reference only)

| File | Purpose |
|------|---------|
| `data-upgrade/schema-1.0.10.php` | Single-glance mirror of the cumulative 1.0.10 schema — every table, column, index, and FK that the chain above produces. Not loaded by Magento (the filename and the subdirectory name deliberately avoid the `install-*` / `upgrade-*` glob). |
| `README.md` | This file. |

## Why we keep both

A production database whose `core_resource` row already reads
`xfe_carrier_setup = 1.0.10` relies on the upgrade files in the root of this
directory to be **bit-identical** to what was shipped when those sites
upgraded. Rewriting the upgrade chain (renaming, merging, or deleting
files) would break the diff that Magento uses to decide whether to
re-apply a migration. The `data-upgrade/schema-1.0.10.php` mirror lets us
see the final shape of the database without touching that chain.

## How Magento picks files

`Mage_Core_Model_Resource_Setup::_installUpgradeDbData()` scans the
resource directory and its subdirectories for files whose name matches
the regex `^(install|upgrade)-.*\.php$` (it uses
`Varien_Io::regexToPattern` style matching). Anything else — including
files in a subdirectory named `data-upgrade/` or files starting with
`schema-` — is left alone.