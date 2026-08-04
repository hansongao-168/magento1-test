<?php
/**
 * Upgrade 1.0.7 -> 1.0.8
 *
 * Add per-store translations for the carrier entity.
 *
 * The carrier row holds the default ("admin scope") values for name and
 * note; per-store translations live in xfe_carrier_translation keyed by
 * (carrier_id, store_id). A row whose name/note are both empty falls back
 * to the default values at read time, mirroring how Magento EAV resolves
 * empty store-scope attribute values.
 *
 * Mirrors the Mage_Core_Model_Resource_Db_Abstract "after save" hook so
 * that controllers can keep using $model->save() unchanged; the resource
 * model picks up store_translations from the model and writes them in a
 * second step.
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$connection      = $installer->getConnection();
$carrierTable    = $installer->getTable('xfe_carrier/carrier');

// ----------------------------------------------------------------------
// 1. Create xfe_carrier_translation
// ----------------------------------------------------------------------
$translationTable = $connection->newTable($installer->getTable('xfe_carrier/carrier_translation'))
    ->addColumn('translation_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), 'Translation Primary Key')
    ->addColumn('carrier_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned'  => true,
        'nullable'  => false,
    ), 'Carrier ID')
    ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
        'unsigned'  => true,
        'nullable'  => false,
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
        $carrierTable,
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('XFE Carrier Per-Store Translations');

$connection->createTable($translationTable);

$installer->endSetup();