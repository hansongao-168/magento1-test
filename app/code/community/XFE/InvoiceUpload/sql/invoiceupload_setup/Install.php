<?php

/**
 * Idempotent install helpers. Each method is callable either by the
 * usual setup-script include or by a standalone test outside Magento's
 * setup pipeline.
 */
class XFE_InvoiceUpload_Sql_Install
{
    /**
     * Create the xfe_invoiceupload_invoice table.
     *
     * Idempotent: bails out if the table already exists.
     *
     * @param Mage_Core_Model_Resource_Setup|null $installer
     */
    public static function createInvoiceTable($installer = null)
    {
        $write = $installer instanceof Mage_Core_Model_Resource_Setup
            ? $installer->getConnection()
            : Mage::getSingleton('core/resource')->getConnection('core_write');

        $tableName = $installer instanceof Mage_Core_Model_Resource_Setup
            ? $installer->getTable('xfe_invoiceupload/invoice')
            : Mage::getSingleton('core/resource')->getTableName('xfe_invoiceupload/invoice');

        $read = $installer instanceof Mage_Core_Model_Resource_Setup
            ? $installer->getConnection()
            : Mage::getSingleton('core/resource')->getConnection('core_read');

        if ($read->fetchOne('SHOW TABLES LIKE ' . $read->quote($tableName))) {
            return false;
        }

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
            ->addIndex('IDX_INVOICE_CUSTOMER', array('customer_id'))
            ->addIndex('IDX_INVOICE_ORDER',    array('order_id'));
        $write->createTable($table);
        return true;
    }
}