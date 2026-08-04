<?php
/**
 * Install script: xfe_label_print
 *
 * One row per "label file" produced for a shipment. The label binary is
 * stored on disk under var/xfe/labelprint/ and the relative path is
 * recorded in path_file. Re-prints overwrite path_file and move the
 * previous value into old_path_file so the history is preserved without
 * growing the row count.
 *
 * additional_data is TEXT but the helper stores a JSON-encoded array
 * there so callers can attach arbitrary context (carrier code, account
 * id, response headers, etc.) without schema changes.
 *
 * order_id / tracking_number_id are required at the DB layer (NOT NULL
 * DEFAULT 0) so a row is always traceable back to an order and a
 * tracking number. 0 means "not associated" and is reserved for rows
 * that pre-date (or fall outside) the normal sales-flow link.
 *
 * Indexes:
 *   - order_id            : look up every label produced for an order
 *   - tracking_number_id  : jump from a tracking number to its label
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$table = $installer->getConnection()
    ->newTable($installer->getTable('xfe_labelprint/print'))
    ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ), 'Primary key')
    ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ), 'Sales flat order entity_id; 0 when not associated')
    ->addColumn('tracking_number_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
        'nullable' => false,
        'default'  => 0,
    ), 'Sales shipment_track entity_id; 0 when not associated')
    ->addColumn('old_path_file', Varien_Db_Ddl_Table::TYPE_VARCHAR, 512, array(
        'nullable' => true,
        'default'  => null,
    ), 'Previous path_file when a label was regenerated for the same tracking number')
    ->addColumn('path_file', Varien_Db_Ddl_Table::TYPE_VARCHAR, 512, array(
        'nullable' => true,
        'default'  => null,
    ), 'Current relative path under var/, e.g. xfe/labelprint/2026/07/x.pdf')
    ->addColumn('additional_data', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => true,
        'default'  => null,
    ), 'JSON blob for caller-defined context (stored as JSON string)')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
        'nullable' => true,
        'default'  => null,
    ), 'Row insert timestamp')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
        'nullable' => true,
        'default'  => null,
    ), 'Row last update timestamp')
    ->addIndex(
        $installer->getIdxName('xfe_labelprint/print', array('order_id')),
        array('order_id')
    )
    ->addIndex(
        $installer->getIdxName('xfe_labelprint/print', array('tracking_number_id')),
        array('tracking_number_id')
    )
    ->setComment('XFE Label Print');

$installer->getConnection()->createTable($table);

$installer->endSetup();

