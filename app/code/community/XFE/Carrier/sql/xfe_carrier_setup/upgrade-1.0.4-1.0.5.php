<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

// Mirror the account->rule_id relationship for logos so rules can select
// which logo a carrier displays in a given context.
$tableName  = $installer->getTable('xfe_carrier/carrier_logo');
$connection = $installer->getConnection();

if (!$connection->tableColumnExists($tableName, 'rule_id')) {
    $connection->addColumn($tableName, 'rule_id', array(
        'type'     => Varien_Db_Ddl_Table::TYPE_INTEGER,
        'unsigned' => true,
        'nullable' => true,
        'default'  => null,
        'comment'  => 'Optional rule that selects this logo',
        'after'    => 'logo_type',
    ));

    $connection->addForeignKey(
        $installer->getFkName(
            'xfe_carrier/carrier_logo',
            'rule_id',
            'xfe_carrier/carrier_rule',
            'rule_id'
        ),
        $tableName,
        'rule_id',
        $installer->getTable('xfe_carrier/carrier_rule'),
        'rule_id',
        Varien_Db_Ddl_Table::ACTION_SET_NULL,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    );

    $idxName = $installer->getIdxName('xfe_carrier/carrier_logo', array('rule_id'));
    $connection->addIndex($tableName, $idxName, array('rule_id'));
}

$installer->endSetup();
