<?php
/* @var $installer Mage_Sales_Model_Mysql4_Setup */
$installer = $this;
$installer->startSetup();

$table = $installer->getConnection()
    ->newTable($installer->getTable('xfe_checkouterrors/checkout_error'))
    ->addColumn('error_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Error ID')
    ->addColumn('reference_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, [
        'nullable'  => false,
    ], 'Reference ID (ERR-xxx)')
    ->addColumn('step', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, [
        'nullable'  => false,
    ], 'Checkout step')
    ->addColumn('error_message', Varien_Db_Ddl_Table::TYPE_TEXT, null, [
        'nullable'  => true,
    ], 'Error message')
    ->addColumn('error_trace', Varien_Db_Ddl_Table::TYPE_TEXT, null, [
        'nullable'  => true,
    ], 'Exception stack trace')
    ->addColumn('request_data', Varien_Db_Ddl_Table::TYPE_TEXT, null, [
        'nullable'  => true,
    ], 'Serialized request data')
    ->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => 0,
    ], 'Customer ID')
    ->addColumn('customer_email', Varien_Db_Ddl_Table::TYPE_VARCHAR, 128, [
        'nullable'  => true,
    ], 'Customer email')
    ->addColumn('quote_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => 0,
    ], 'Quote ID')
    ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => 0,
    ], 'Store ID')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
        'nullable'  => false,
    ], 'Created at')
    ->addIndex($installer->getIdxName('xfe_checkouterrors/checkout_error', 'reference_id'),
        'reference_id')
    ->addIndex($installer->getIdxName('xfe_checkouterrors/checkout_error', 'step'),
        'step')
    ->addIndex($installer->getIdxName('xfe_checkouterrors/checkout_error', 'created_at'),
        'created_at')
    ->setComment('XFE Checkout Error Log');

$installer->getConnection()->createTable($table);
$installer->endSetup();
