<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$tableName = $installer->getTable('xfe_carrier/carrier_rule');
$connection = $installer->getConnection();

// Add module_code column to carrier_rule table
$connection->addColumn($tableName, 'module_code', array(
    'type'    => Varien_Db_Ddl_Table::TYPE_VARCHAR,
    'length'  => 64,
    'nullable' => true,
    'default'  => null,
    'comment'  => '关联模块代码（从 carrier_modules.xml 定义）',
    'after'    => 'carrier_id',
));

$installer->endSetup();
