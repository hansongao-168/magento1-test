<?php
/**
 * Consolidated XFE Carrier schema (target version: 1.0.10)
 *
 * This file is a READ-ONLY mirror of the cumulative schema produced by:
 *   install-1.0.0.php
 *   upgrade-1.0.0-1.0.1.php
 *   upgrade-1.0.1-1.0.2.php
 *   upgrade-1.0.2-1.0.3.php
 *   upgrade-1.0.3-1.0.4.php
 *   upgrade-1.0.4-1.0.5.php
 *   upgrade-1.0.5-1.0.6.php
 *   upgrade-1.0.6-1.0.7.php
 *   upgrade-1.0.7-1.0.8.php
 *   upgrade-1.0.8-1.0.9.php
 *   upgrade-1.0.9-1.0.10.php
 *
 * It exists to give a single-glance view of every table the module ships
 * after running the full upgrade chain on a fresh database. Do NOT use
 * it as a setup script:
 *
 *   - Mage_Core_Model_Resource_Setup::_installUpgradeDbData() matches
 *     files whose name is "install-*" or "upgrade-*" (case insensitive),
 *     and walks subdirectories. This file is intentionally named
 *     "schema-*.php" (not "install-*" / "upgrade-*") and lives under
 *     "data-upgrade/" so Magento will skip it.
 *   - The original install/upgrade files are still the authoritative
 *     migration chain on production databases. Removing or editing them
 *     would break Mage_Core_Model_Resource_Setup::applyUpdates() on
 *     already-installed environments.
 *
 * Tables (8 total at 1.0.10):
 *   xfe_carrier_carrier                -- main entity
 *   xfe_carrier_carrier_logo           -- carrier logos (1:N)
 *   xfe_carrier_carrier_account        -- API accounts (1:N)
 *   xfe_carrier_carrier_rule           -- routing rules (1:N)
 *   xfe_carrier_rule_condition_group   -- nested AND/OR groups
 *   xfe_carrier_rule_condition         -- leaf conditions
 *   xfe_carrier_carrier_translation    -- per-store name/note
 *   xfe_carrier_carrier_ftp_account    -- FTP / SFTP / FTPS accounts
 */

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

// =========================================================================
// xfe_carrier_carrier  (introduced 1.0.0; unchanged since)
// =========================================================================
$installer->getConnection()->createTable(
    $installer->getConnection()->newTable($installer->getTable('xfe_carrier/carrier'))
        ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
        ), '主键')
        ->addColumn('name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable' => false,
        ), '承运商名称')
        ->addColumn('code', Varien_Db_Ddl_Table::TYPE_VARCHAR, 64, array(
            'nullable' => false,
        ), '承运商标识/代码')
        ->addColumn('shipping_company_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable' => true,
            'default'  => null,
        ), '线路公司ID')
        ->addColumn('status', Varien_Db_Ddl_Table::TYPE_TINYINT, 1, array(
            'nullable' => false,
            'default'  => 1,
        ), '状态：1启用/0禁用')
        ->addColumn('sort_order', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable' => false,
            'default'  => 0,
        ), '排序')
        ->addColumn('note', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable' => true,
            'default'  => null,
        ), '备注')
        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable' => true,
        ), '创建时间')
        ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable' => true,
        ), '更新时间')
        ->addIndex($installer->getIdxName('xfe_carrier/carrier', array('shipping_company_id')),
            array('shipping_company_id'))
        ->addIndex($installer->getIdxName('xfe_carrier/carrier', array('status')),
            array('status'))
        ->setComment('XFE 承运商管理')
);

// =========================================================================
// xfe_carrier_carrier_logo  (1.0.0 -> 1.0.2 -> 1.0.5 -> 1.0.9)
// =========================================================================
// Final columns:
//   logo_id, carrier_id, label, logo_type, sort_order, rule_id, size_type,
//   path, width, height, created_at, updated_at
//   (size_type was created in 1.0.0; the UNIQUE constraint on
//   (carrier_id, size_type) was dropped in 1.0.2 but the column itself
//   was kept for backwards compatibility with existing rows.)
// Final indices:
//   INDEX (carrier_id)            -- non-unique, for joins (replaces the
//                                    UNIQUE(carrier_id, size_type) dropped in 1.0.2)
//   INDEX (carrier_id, logo_type) -- for type filtering
//   INDEX (rule_id)               -- added in 1.0.5
//   INDEX (carrier_id, updated_at)-- added in 1.0.9
// FKs:
//   (carrier_id) -> xfe_carrier_carrier.entity_id   ON DELETE CASCADE
//   (rule_id)    -> xfe_carrier_carrier_rule.rule_id ON DELETE SET NULL
$installer->getConnection()->createTable(
    $installer->getConnection()->newTable($installer->getTable('xfe_carrier/carrier_logo'))
        ->addColumn('logo_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
        ), 'Logo主键')
        ->addColumn('carrier_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned' => true,
            'nullable' => false,
        ), '关联承运商ID')
        ->addColumn('label', Varien_Db_Ddl_Table::TYPE_VARCHAR, 128, array(
            'nullable' => true,
            'default'  => null,
        ), 'Logo name/label')
        ->addColumn('logo_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, array(
            'nullable' => true,
            'default'  => 'main',
        ), 'Logo type: main/mobile/alt')
        ->addColumn('sort_order', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable' => false,
            'default'  => 0,
        ), 'Sort order')
        ->addColumn('rule_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned' => true,
            'nullable' => true,
            'default'  => null,
        ), 'Optional rule that selects this logo')
        ->addColumn('size_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 20, array(
            'nullable' => false,
            'default'  => 'original',
        ), '尺寸标识：original/small/medium/large (legacy)')
        ->addColumn('path', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable' => false,
        ), '文件相对路径')
        ->addColumn('width', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
            'unsigned' => true,
            'nullable' => true,
            'default'  => null,
        ), '图片宽度')
        ->addColumn('height', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
            'unsigned' => true,
            'nullable' => true,
            'default'  => null,
        ), '图片高度')
        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable' => true,
        ), '创建时间')
        ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable' => true,
        ), 'Last update timestamp')
        ->addIndex($installer->getIdxName('xfe_carrier/carrier_logo', array('carrier_id')),
            array('carrier_id'))
        ->addIndex($installer->getIdxName('xfe_carrier/carrier_logo', array('carrier_id', 'logo_type')),
            array('carrier_id', 'logo_type'))
        ->addIndex($installer->getIdxName('xfe_carrier/carrier_logo', array('rule_id')),
            array('rule_id'))
        ->addIndex($installer->getIdxName('xfe_carrier/carrier_logo', array('carrier_id', 'updated_at')),
            array('carrier_id', 'updated_at'))
        ->addForeignKey(
            $installer->getFkName('xfe_carrier/carrier_logo', 'carrier_id', 'xfe_carrier/carrier', 'entity_id'),
            'carrier_id',
            $installer->getTable('xfe_carrier/carrier'),
            'entity_id',
            Varien_Db_Ddl_Table::ACTION_CASCADE,
            Varien_Db_Ddl_Table::ACTION_CASCADE
        )
        ->addForeignKey(
            $installer->getFkName('xfe_carrier/carrier_logo', 'rule_id', 'xfe_carrier/carrier_rule', 'rule_id'),
            'rule_id',
            $installer->getTable('xfe_carrier/carrier_rule'),
            'rule_id',
            Varien_Db_Ddl_Table::ACTION_SET_NULL,
            Varien_Db_Ddl_Table::ACTION_CASCADE
        )
        ->setComment('XFE 承运商 Logo')
);

// =========================================================================
// xfe_carrier_carrier_account  (1.0.0 -> 1.0.4 -> 1.0.7 -> 1.0.9 index)
// =========================================================================
// 1.0.7 dropped account.rule_id and replaced it with rule.account_id (1:N).
// Final columns:
//   account_id, carrier_id, account_name, account_no, api_key, api_secret,
//   username, password, endpoint_url, status, sort_order, note,
//   created_at, updated_at
// Final indices:
//   INDEX (carrier_id)                             -- from 1.0.0
//   INDEX (carrier_id, status, updated_at)         -- from 1.0.9
// FKs:
//   (carrier_id) -> xfe_carrier_carrier.entity_id  ON DELETE CASCADE
$installer->getConnection()->createTable(
    $installer->getConnection()->newTable($installer->getTable('xfe_carrier/carrier_account'))
        ->addColumn('account_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
        ), '账号主键')
        ->addColumn('carrier_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned' => true,
            'nullable' => false,
        ), '关联承运商ID')
        ->addColumn('account_name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable' => false,
        ), '账号名称')
        ->addColumn('account_no', Varien_Db_Ddl_Table::TYPE_VARCHAR, 128, array(
            'nullable' => true,
        ), '账号编号')
        ->addColumn('api_key', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable' => true,
        ), 'API Key')
        ->addColumn('api_secret', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable' => true,
        ), 'API Secret')
        ->addColumn('username', Varien_Db_Ddl_Table::TYPE_VARCHAR, 128, array(
            'nullable' => true,
        ), '登录用户名')
        ->addColumn('password', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable' => true,
        ), '登录密码')
        ->addColumn('endpoint_url', Varien_Db_Ddl_Table::TYPE_VARCHAR, 512, array(
            'nullable' => true,
        ), 'API端点URL')
        ->addColumn('status', Varien_Db_Ddl_Table::TYPE_TINYINT, 1, array(
            'nullable' => false,
            'default'  => 1,
        ), '状态：1启用/0禁用')
        ->addColumn('sort_order', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable' => false,
            'default'  => 0,
        ), '排序')
        ->addColumn('note', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable' => true,
        ), '备注')
        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable' => true,
        ), '创建时间')
        ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable' => true,
        ), '更新时间')
        ->addIndex($installer->getIdxName('xfe_carrier/carrier_account', array('carrier_id')),
            array('carrier_id'))
        ->addIndex($installer->getIdxName('xfe_carrier/carrier_account', array('carrier_id', 'status', 'updated_at')),
            array('carrier_id', 'status', 'updated_at'))
        ->addForeignKey(
            $installer->getFkName('xfe_carrier/carrier_account', 'carrier_id', 'xfe_carrier/carrier', 'entity_id'),
            'carrier_id',
            $installer->getTable('xfe_carrier/carrier'),
            'entity_id',
            Varien_Db_Ddl_Table::ACTION_CASCADE,
            Varien_Db_Ddl_Table::ACTION_CASCADE
        )
        ->setComment('XFE 承运商账号管理')
);

// =========================================================================
// xfe_carrier_carrier_rule  (1.0.0 -> 1.0.3 -> 1.0.7 -> 1.0.9 -> 1.0.10)
// =========================================================================
// Final columns:
//   rule_id, carrier_id, account_id, module_code, ftp_account_id, name,
//   description, status, is_cancel_on_failure, sort_order, priority,
//   created_at, updated_at
// Final indices:
//   INDEX (carrier_id)
//   INDEX (account_id)
//   INDEX (ftp_account_id)
//   INDEX (carrier_id, status, module_code, priority, updated_at)
// FKs:
//   (carrier_id)     -> xfe_carrier_carrier.entity_id         ON DELETE CASCADE
//   (account_id)     -> xfe_carrier_carrier_account.account_id ON DELETE SET NULL
//   (ftp_account_id) -> xfe_carrier_carrier_ftp_account.ftp_account_id ON DELETE SET NULL
$installer->getConnection()->createTable(
    $installer->getConnection()->newTable($installer->getTable('xfe_carrier/carrier_rule'))
        ->addColumn('rule_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
        ), '规则主键')
        ->addColumn('carrier_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned' => true,
            'nullable' => false,
        ), '关联承运商ID')
        ->addColumn('account_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned' => true,
            'nullable' => true,
            'default'  => null,
        ), 'Optional: bind this rule to a specific account (1:N from account to rules)')
        ->addColumn('module_code', Varien_Db_Ddl_Table::TYPE_VARCHAR, 64, array(
            'nullable' => true,
            'default'  => null,
        ), '关联模块代码（从 carrier_modules.xml 定义）')
        ->addColumn('ftp_account_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned' => true,
            'nullable' => true,
            'default'  => null,
        ), 'Optional: bind this rule to a specific FTP account (1:N from FTP account to rules)')
        ->addColumn('name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable' => false,
        ), '规则名称')
        ->addColumn('description', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable' => true,
        ), '规则描述')
        ->addColumn('status', Varien_Db_Ddl_Table::TYPE_TINYINT, 1, array(
            'nullable' => false,
            'default'  => 1,
        ), '状态：1启用/0禁用')
        ->addColumn('is_cancel_on_failure', Varien_Db_Ddl_Table::TYPE_TINYINT, 1, array(
            'nullable' => false,
            'default'  => 0,
        ), 'Whether matching failure cancels further rule attempts')
        ->addColumn('sort_order', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable' => false,
            'default'  => 0,
        ), '排序')
        ->addColumn('priority', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable' => false,
            'default'  => 0,
        ), 'Resolver priority, higher wins')
        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable' => true,
        ), '创建时间')
        ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable' => true,
        ), '更新时间')
        ->addIndex($installer->getIdxName('xfe_carrier/carrier_rule', array('carrier_id')),
            array('carrier_id'))
        ->addIndex($installer->getIdxName('xfe_carrier/carrier_rule', array('account_id')),
            array('account_id'))
        ->addIndex($installer->getIdxName('xfe_carrier/carrier_rule', array('ftp_account_id')),
            array('ftp_account_id'))
        ->addIndex(
            $installer->getIdxName(
                'xfe_carrier/carrier_rule',
                array('carrier_id', 'status', 'module_code', 'priority', 'updated_at')
            ),
            array('carrier_id', 'status', 'module_code', 'priority', 'updated_at')
        )
        ->addForeignKey(
            $installer->getFkName('xfe_carrier/carrier_rule', 'carrier_id', 'xfe_carrier/carrier', 'entity_id'),
            'carrier_id',
            $installer->getTable('xfe_carrier/carrier'),
            'entity_id',
            Varien_Db_Ddl_Table::ACTION_CASCADE,
            Varien_Db_Ddl_Table::ACTION_CASCADE
        )
        ->addForeignKey(
            $installer->getFkName('xfe_carrier/carrier_rule', 'account_id', 'xfe_carrier/carrier_account', 'account_id'),
            'account_id',
            $installer->getTable('xfe_carrier/carrier_account'),
            'account_id',
            Varien_Db_Ddl_Table::ACTION_SET_NULL,
            Varien_Db_Ddl_Table::ACTION_CASCADE
        )
        ->addForeignKey(
            $installer->getFkName('xfe_carrier/carrier_rule', 'ftp_account_id', 'xfe_carrier/carrier_ftp_account', 'ftp_account_id'),
            'ftp_account_id',
            $installer->getTable('xfe_carrier/carrier_ftp_account'),
            'ftp_account_id',
            Varien_Db_Ddl_Table::ACTION_SET_NULL,
            Varien_Db_Ddl_Table::ACTION_CASCADE
        )
        ->setComment('XFE 承运商规则管理')
);

// =========================================================================
// xfe_carrier_rule_condition_group  (1.0.0->1.0.1, unchanged since)
// =========================================================================
$installer->getConnection()->createTable(
    $installer->getConnection()->newTable($installer->getTable('xfe_carrier/rule_condition_group'))
        ->addColumn('group_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
        ), '条件组主键')
        ->addColumn('rule_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned' => true,
            'nullable' => false,
        ), '关联规则ID')
        ->addColumn('sort_order', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable' => false,
            'default'  => 0,
        ), '排序')
        ->addColumn('aggregator', Varien_Db_Ddl_Table::TYPE_VARCHAR, 4, array(
            'nullable' => false,
            'default'  => 'all',
        ), '聚合方式：all=AND / any=OR')
        ->addColumn('parent_group_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned' => true,
            'nullable' => true,
            'default'  => null,
        ), '父级条件组ID')
        ->addForeignKey(
            $installer->getFkName('xfe_carrier/rule_condition_group', 'rule_id', 'xfe_carrier/carrier_rule', 'rule_id'),
            'rule_id',
            $installer->getTable('xfe_carrier/carrier_rule'),
            'rule_id',
            Varien_Db_Ddl_Table::ACTION_CASCADE,
            Varien_Db_Ddl_Table::ACTION_CASCADE
        )
        ->setComment('XFE 承运商规则条件组')
);

// =========================================================================
// xfe_carrier_rule_condition  (1.0.0->1.0.1, unchanged since)
// =========================================================================
$installer->getConnection()->createTable(
    $installer->getConnection()->newTable($installer->getTable('xfe_carrier/rule_condition'))
        ->addColumn('condition_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
        ), '条件主键')
        ->addColumn('group_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned' => true,
            'nullable' => false,
        ), '关联条件组ID')
        ->addColumn('sort_order', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable' => false,
            'default'  => 0,
        ), '排序')
        ->addColumn('attribute', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, array(
            'nullable' => false,
        ), '条件属性')
        ->addColumn('operator', Varien_Db_Ddl_Table::TYPE_VARCHAR, 16, array(
            'nullable' => false,
        ), '比较运算符')
        ->addColumn('value', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable' => false,
        ), '比较值')
        ->addForeignKey(
            $installer->getFkName('xfe_carrier/rule_condition', 'group_id', 'xfe_carrier/rule_condition_group', 'group_id'),
            'group_id',
            $installer->getTable('xfe_carrier/rule_condition_group'),
            'group_id',
            Varien_Db_Ddl_Table::ACTION_CASCADE,
            Varien_Db_Ddl_Table::ACTION_CASCADE
        )
        ->setComment('XFE 承运商规则条件明细')
);

// =========================================================================
// xfe_carrier_carrier_translation  (introduced 1.0.7->1.0.8)
// =========================================================================
$installer->getConnection()->createTable(
    $installer->getConnection()->newTable($installer->getTable('xfe_carrier/carrier_translation'))
        ->addColumn('translation_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
        ), 'Translation Primary Key')
        ->addColumn('carrier_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned' => true,
            'nullable' => false,
        ), 'Carrier ID')
        ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
            'unsigned' => true,
            'nullable' => false,
            'default'   => 0,
        ), 'Store ID (0 = admin)')
        ->addColumn('name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable' => true,
        ), 'Carrier Name (per-store)')
        ->addColumn('note', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable' => true,
        ), 'Note (per-store)')
        ->addIndex(
            $installer->getIdxName('xfe_carrier/carrier_translation', array('carrier_id')),
            array('carrier_id')
        )
        ->addIndex(
            $installer->getIdxName('xfe_carrier/carrier_translation', array('store_id')),
            array('store_id')
        )
        ->addIndex(
            $installer->getIdxName(
                'xfe_carrier/carrier_translation',
                array('carrier_id', 'store_id'),
                Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
            ),
            array('carrier_id', 'store_id'),
            array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE)
        )
        ->addForeignKey(
            $installer->getFkName(
                'xfe_carrier/carrier_translation',
                'carrier_id',
                'xfe_carrier/carrier',
                'entity_id'
            ),
            'carrier_id',
            $installer->getTable('xfe_carrier/carrier'),
            'entity_id',
            Varien_Db_Ddl_Table::ACTION_CASCADE,
            Varien_Db_Ddl_Table::ACTION_CASCADE
        )
        ->setComment('XFE Carrier Per-Store Translations')
);

// =========================================================================
// xfe_carrier_carrier_ftp_account  (introduced 1.0.9->1.0.10)
// =========================================================================
$installer->getConnection()->createTable(
    $installer->getConnection()->newTable($installer->getTable('xfe_carrier/carrier_ftp_account'))
        ->addColumn('ftp_account_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
        ), 'FTP账号主键')
        ->addColumn('carrier_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned' => true,
            'nullable' => false,
        ), '关联承运商ID')
        ->addColumn('account_name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable' => false,
        ), '账号名称')
        ->addColumn('account_no', Varien_Db_Ddl_Table::TYPE_VARCHAR, 128, array(
            'nullable' => true,
        ), '账号编号')
        ->addColumn('protocol', Varien_Db_Ddl_Table::TYPE_VARCHAR, 8, array(
            'nullable' => false,
            'default'  => 'ftp',
        ), "Protocol: 'ftp'/'sftp'/'ftps'")
        ->addColumn('host', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable' => false,
        ), 'FTP host')
        ->addColumn('port', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned' => true,
            'nullable' => false,
            'default'  => 21,
        ), 'FTP port')
        ->addColumn('username', Varien_Db_Ddl_Table::TYPE_VARCHAR, 128, array(
            'nullable' => true,
        ), '登录用户名')
        ->addColumn('password', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable' => true,
        ), '登录密码')
        ->addColumn('remote_path', Varien_Db_Ddl_Table::TYPE_VARCHAR, 512, array(
            'nullable' => true,
        ), '远端路径')
        ->addColumn('mode', Varien_Db_Ddl_Table::TYPE_VARCHAR, 8, array(
            'nullable' => false,
            'default'  => 'passive',
        ), "FTP mode: 'passive'/'active'")
        ->addColumn('encoding', Varien_Db_Ddl_Table::TYPE_VARCHAR, 16, array(
            'nullable' => false,
            'default'  => 'UTF-8',
        ), 'FTP encoding')
        ->addColumn('status', Varien_Db_Ddl_Table::TYPE_TINYINT, 1, array(
            'nullable' => false,
            'default'  => 1,
        ), '状态：1启用/0禁用')
        ->addColumn('sort_order', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable' => false,
            'default'  => 0,
        ), '排序')
        ->addColumn('note', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable' => true,
        ), '备注')
        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable' => true,
        ), '创建时间')
        ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable' => true,
        ), '更新时间')
        ->addIndex($installer->getIdxName('xfe_carrier/carrier_ftp_account', array('carrier_id')),
            array('carrier_id'))
        ->addIndex($installer->getIdxName('xfe_carrier/carrier_ftp_account', array('carrier_id', 'status', 'updated_at')),
            array('carrier_id', 'status', 'updated_at'))
        ->addForeignKey(
            $installer->getFkName('xfe_carrier/carrier_ftp_account', 'carrier_id', 'xfe_carrier/carrier', 'entity_id'),
            'carrier_id',
            $installer->getTable('xfe_carrier/carrier'),
            'entity_id',
            Varien_Db_Ddl_Table::ACTION_CASCADE,
            Varien_Db_Ddl_Table::ACTION_CASCADE
        )
        ->setComment('FTP账号管理')
);

$installer->endSetup();