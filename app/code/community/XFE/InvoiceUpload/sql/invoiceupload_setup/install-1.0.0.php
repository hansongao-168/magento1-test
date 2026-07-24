<?php
/**
 * XFE_InvoiceUpload install-1.0.0.php
 *
 * Self-contained: inline table creation. Avoids relying on an
 * external helper class that may not be auto-loaded by Mage setup.
 *
 * Idempotent: if the table already exists, createTable() throws
 * which we swallow via try/catch.
 *
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;
$installer->startSetup();

$tableName = $installer->getTable('xfe_invoiceupload/invoice');
$write = $installer->getConnection();

try {
    $table = $write->newTable($tableName)
        ->addColumn('invoice_id',    Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity'  => true,
            'nullable'  => false,
            'primary'   => true,
            'unsigned'  => true,
        ))
        ->addColumn('customer_id',   Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable'  => false,
            'unsigned'  => true,
        ))
        ->addColumn('order_id',      Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable'  => false,
            'unsigned'  => true,
        ))
        ->addColumn('label',         Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable'  => true,
        ))
        ->addColumn('original_name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable'  => false,
        ))
        ->addColumn('path',          Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable'  => false,
        ))
        ->addColumn('mime_type',     Varien_Db_Ddl_Table::TYPE_VARCHAR, 64, array(
            'nullable'  => true,
            'default'   => null,
        ))
        ->addColumn('size_bytes',    Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable'  => false,
            'default'   => 0,
            'unsigned'  => true,
        ))
        ->addColumn('sha1',          Varien_Db_Ddl_Table::TYPE_CHAR, 40, array(
            'nullable'  => true,
            'default'   => null,
        ))
        ->addColumn('created_at',    Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable'  => true,
        ))
        ->addIndex($installer->getIdxName('xfe_invoiceupload/invoice', array('customer_id')),
            array('customer_id'))
        ->addIndex($installer->getIdxName('xfe_invoiceupload/invoice', array('order_id')),
            array('order_id'))
        ->setComment('XFE customer-uploaded invoices');
    $write->createTable($table);
} catch (Exception $e) {
    // Table already exists - this is fine for re-runs.
    if (!preg_match('/already exists/i', $e->getMessage())) {
        throw $e;
    }
}

$installer->endSetup();