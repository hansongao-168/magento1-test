<?php
/**
 * Invoice Adjustment Setup
 *
 * @category   Community
 * @package    XFE_InvoiceAdjustment
 */

$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */

$installer->startSetup();

/**
 * Create invoice_adjustment table
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('invoiceadjustment/adjustment'))
    ->addColumn('adjustment_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), 'Adjustment ID')
    ->addColumn('adjustment_name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable'  => false,
    ), 'Adjustment Name/Title')
    ->addColumn('adjusted_ht', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', array(
        'nullable'  => false,
        'default'   => '0.0000',
    ), 'Adjusted HT Amount')
    ->addColumn('adjusted_tva', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', array(
        'nullable'  => false,
        'default'   => '0.0000',
    ), 'Adjusted TVA Amount')
    ->addColumn('adjusted_ttc', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', array(
        'nullable'  => false,
        'default'   => '0.0000',
    ), 'Adjusted TTC Amount')
    ->addColumn('customer_pay', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', array(
        'nullable'  => false,
        'default'   => '0.0000',
    ), 'Customer Pay Amount')
    ->addColumn('customer_refund', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', array(
        'nullable'  => false,
        'default'   => '0.0000',
    ), 'Customer Refund Amount')
    ->addColumn('status', Varien_Db_Ddl_Table::TYPE_VARCHAR, 20, array(
        'nullable'  => false,
        'default'   => 'pending',
    ), 'Status')
    ->addColumn('reason', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable'  => true,
    ), 'Reason')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
    ), 'Created At')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT_UPDATE,
    ), 'Updated At')
    ->setComment('Invoice Adjustment Table');

$installer->getConnection()->createTable($table);

/**
 * Create invoice_adjustment_order table (one-to-many relation)
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('invoiceadjustment/adjustment_order'))
    ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), 'Relation ID')
    ->addColumn('adjustment_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => false,
        'unsigned'  => true,
    ), 'Adjustment ID')
    ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => false,
        'unsigned'  => true,
    ), 'Order ID')
    ->addColumn('order_number', Varien_Db_Ddl_Table::TYPE_VARCHAR, 50, array(
        'nullable'  => false,
    ), 'Order Increment ID')
    ->addColumn('original_ht', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', array(
        'nullable'  => false,
        'default'   => '0.0000',
    ), 'Original HT Amount')
    ->addColumn('original_tva', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', array(
        'nullable'  => false,
        'default'   => '0.0000',
    ), 'Original TVA Amount')
    ->addColumn('original_ttc', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', array(
        'nullable'  => false,
        'default'   => '0.0000',
    ), 'Original TTC Amount')
    ->addColumn('adjusted_ht', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', array(
        'nullable'  => false,
        'default'   => '0.0000',
    ), 'Per-Order Adjusted HT')
    ->addColumn('adjusted_tva', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', array(
        'nullable'  => false,
        'default'   => '0.0000',
    ), 'Per-Order Adjusted TVA')
    ->addColumn('adjusted_ttc', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', array(
        'nullable'  => false,
        'default'   => '0.0000',
    ), 'Per-Order Adjusted TTC')
    ->addColumn('customer_pay', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', array(
        'nullable'  => false,
        'default'   => '0.0000',
    ), 'Customer Pay')
    ->addColumn('customer_refund', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', array(
        'nullable'  => false,
        'default'   => '0.0000',
    ), 'Customer Refund')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
    ), 'Created At')
    ->addForeignKey(
        $installer->getFkName('invoiceadjustment/adjustment_order', 'adjustment_id', 'invoiceadjustment/adjustment', 'adjustment_id'),
        'adjustment_id',
        $installer->getTable('invoiceadjustment/adjustment'),
        'adjustment_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->addForeignKey(
        $installer->getFkName('invoiceadjustment/adjustment_order', 'order_id', 'sales/order', 'entity_id'),
        'order_id',
        $installer->getTable('sales/order'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_NO_ACTION
    )
    ->addIndex(
        $installer->getIdxName('invoiceadjustment/adjustment_order', array('adjustment_id', 'order_id')),
        array('adjustment_id', 'order_id'),
        Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
    )
    ->setComment('Invoice Adjustment to Order Relation Table');

$installer->getConnection()->createTable($table);

$installer->endSetup();
