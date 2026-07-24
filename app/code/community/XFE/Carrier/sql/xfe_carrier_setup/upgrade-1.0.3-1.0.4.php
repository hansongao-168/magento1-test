<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$tableName = $installer->getTable('xfe_carrier/carrier_account');
$connection = $installer->getConnection();

// Add rule_id column to carrier_account table (nullable, so allow empty)
$connection->addColumn($tableName, 'rule_id', array(
    'type'     => Varien_Db_Ddl_Table::TYPE_INTEGER,
    'unsigned' => true,
    'nullable' => true,
    'default'  => null,
    'comment'  => '关联规则ID',
    'after'    => 'carrier_id',
));

// Add foreign key to carrier_rule table
$connection->addForeignKey(
    $installer->getFkName(
        'xfe_carrier/carrier_account',
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

$installer->endSetup();
