<?php

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

