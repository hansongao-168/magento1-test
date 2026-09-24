<?php
/**
 * XFE_OAuth2 upgrade 1.0.0 -> 1.0.1
 *
 * Instead of altering the Magento core `sales_flat_order` table, we create
 * a dedicated association table `xfe_oauth2_order_channel` that links
 * `sales_flat_order.entity_id` to the channel it was placed through.
 *
 * Why a separate table:
 *   - Zero impact on Magento core schema (safe to upgrade Magento itself)
 *   - One row per order (UNIQUE on order_id) — keeps the audit trail simple
 *   - Indexed by channel for fast reports / grid filtering
 *   - The OAuth2 client_id is a nullable FK so non-OAuth orders are fine
 *
 * Schema:
 *   xfe_oauth2_order_channel
 *     id               INT UNSIGNED  PK
 *     order_id         INT UNSIGNED  UNIQUE -> sales_flat_order.entity_id
 *     channel          VARCHAR(32)   'web' | 'api_oauth2' | 'admin' | ...
 *     channel_source   VARCHAR(64)   e.g. oauth2 client_id, or NULL
 *     client_id        VARCHAR(80)   nullable FK -> xfe_oauth2_client
 *     created_by       INT UNSIGNED  customer/admin id
 *     created_by_type  VARCHAR(20)   'customer' | 'admin' | 'system'
 *     notes            TEXT          optional audit notes
 *     created_at       DATETIME
 *     updated_at       DATETIME
 *
 * The script is idempotent: re-running it on an already-upgraded database
 * is a no-op (table check + index check via information_schema).
 *
 * @category   Community
 * @package    XFE_OAuth2
 * @version    1.0.1
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

/**
 * Helper: does a table exist? Uses information_schema directly to avoid
 * relying on describeTable() which is cached in the DDL cache primed at
 * startSetup(). Re-runs after a previous partial apply need a fresh read.
 */
$tableExists = function ($tableName) use ($connection) {
    $sql = sprintf(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '%s'",
        addslashes($tableName)
    );
    return (bool)$connection->fetchOne($sql);
};

/**
 * Helper: does an index exist on a table? Also uses information_schema.
 */
$indexExists = function ($tableName, $indexName) use ($connection) {
    $sql = sprintf(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = '%s'
           AND INDEX_NAME   = '%s'",
        addslashes($tableName),
        addslashes($indexName)
    );
    return (bool)$connection->fetchOne($sql);
};

$channelTable = $installer->getTable('xfeoauth2/order_channel');

// 1. Create the table if missing (uses DDL builder for new-table creation,
//    which IS supported; the addColumn() issue only affects ALTER on existing
//    tables in older Magento 1.9).
if (!$tableExists($channelTable)) {
    $table = $connection->newTable($channelTable)
        ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, 10, array(
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
        ), 'ID')
        ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 10, array(
            'unsigned' => true,
            'nullable' => false,
        ), 'Order ID (sales_flat_order.entity_id)')
        ->addColumn('channel', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, array(
            'nullable' => false,
            'default'  => 'web',
        ), 'Order channel: web / api_oauth2 / admin / ...')
        ->addColumn('channel_source', Varien_Db_Ddl_Table::TYPE_VARCHAR, 64, array(
            'nullable' => true,
        ), 'Channel source detail (e.g. OAuth2 client_id)')
        ->addColumn('client_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 80, array(
            'nullable' => true,
        ), 'OAuth2 client_id (FK -> xfe_oauth2_client, nullable)')
        ->addColumn('created_by', Varien_Db_Ddl_Table::TYPE_INTEGER, 10, array(
            'unsigned' => true,
            'nullable' => true,
        ), 'Creator customer/admin id')
        ->addColumn('created_by_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 20, array(
            'nullable' => true,
            'default'  => 'system',
        ), 'Creator type: customer / admin / system')
        ->addColumn('notes', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable' => true,
        ), 'Audit notes')
        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable' => true,
        ), 'Created At')
        ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable' => true,
        ), 'Updated At')
        ->addIndex(
            $installer->getIdxName(
                'xfeoauth2/order_channel',
                array('order_id'),
                Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
            ),
            array('order_id'),
            array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE)
        )
        ->addIndex(
            $installer->getIdxName(
                'xfeoauth2/order_channel',
                array('channel'),
                Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX
            ),
            array('channel'),
            array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX)
        )
        ->addIndex(
            $installer->getIdxName(
                'xfeoauth2/order_channel',
                array('client_id'),
                Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX
            ),
            array('client_id'),
            array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX)
        )
        ->addForeignKey(
            $installer->getFkName(
                'xfeoauth2/order_channel',
                'client_id',
                'xfeoauth2/client',
                'client_id'
            ),
            'client_id',
            $installer->getTable('xfeoauth2/client'),
            'client_id',
            Varien_Db_Ddl_Table::ACTION_SET_NULL,
            Varien_Db_Ddl_Table::ACTION_CASCADE
        )
        ->setComment('XFE OAuth2 Order Channel (one row per order)');

    $connection->createTable($table);
}

// 2. Ensure the secondary indexes exist (in case the table was created
//    earlier without them, or by an older script variant).
$secondaryIndexes = array(
    array('channel',   'channel_idx'),
    array('client_id', 'client_id_idx'),
);
foreach ($secondaryIndexes as $row) {
    list($column, $suffix) = $row;
    $idxName = $installer->getIdxName(
        'xfeoauth2/order_channel',
        array($column),
        Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX
    );
    // getIdxName() is deterministic, so check by suffix as fallback
    if (!$indexExists($channelTable, $idxName)) {
        $connection->raw_query(sprintf(
            'ALTER TABLE %s ADD INDEX %s (%s)',
            $connection->quoteIdentifier($channelTable),
            $connection->quoteIdentifier($idxName),
            $column
        ));
    }
}

$installer->endSetup();