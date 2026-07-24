<?php
/**
 * XFE ShippingRule Upgrade 1.0.0 -> 1.0.1
 *
 * Adds:
 * - stack_mode column to xfeshipping_rule (0=Not Stackable, 1=Stackable)
 * - parent_group_id column to xfeshipping_condition_group (for nested sub-groups)
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */

$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */

$installer->startSetup();

$connection = $installer->getConnection();
$ruleTable  = $installer->getTable('xfeshippingrule/rule');
$groupTable = $installer->getTable('xfeshippingrule/condition_group');

// ---------- stack_mode column ----------
if (!$connection->tableColumnExists($ruleTable, 'stack_mode')) {
    $connection->addColumn($ruleTable, 'stack_mode', array(
        'type'     => Varien_Db_Ddl_Table::TYPE_SMALLINT,
        'nullable' => false,
        'default'  => '0',
        'comment'  => 'Stack Mode (0=Not Stackable, 1=Stackable)',
    ));
}

// ---------- parent_group_id column ----------
if (!$connection->tableColumnExists($groupTable, 'parent_group_id')) {
    $connection->addColumn($groupTable, 'parent_group_id', array(
        'type'     => Varien_Db_Ddl_Table::TYPE_INTEGER,
        'nullable' => true,
        'unsigned' => false,
        'comment'  => 'Parent Group ID (NULL=root level, non-NULL=nested sub-group)',
    ));
} else {
    // Ensure correct type in case previous attempt used wrong type (e.g. unsigned)
    $connection->modifyColumn($groupTable, 'parent_group_id', array(
        'type'     => Varien_Db_Ddl_Table::TYPE_INTEGER,
        'nullable' => true,
        'unsigned' => false,
    ));
}

// ---------- FK: parent_group_id -> group_id ----------
$fkName = $installer->getFkName(
    'xfeshippingrule/condition_group',
    'parent_group_id',
    'xfeshippingrule/condition_group',
    'group_id'
);

// Drop FK first if it exists (e.g. from a previous partial attempt)
try {
    $connection->dropForeignKey($groupTable, $fkName);
} catch (Exception $e) {
    // FK might not exist, safe to ignore
}

$connection->addForeignKey(
    $fkName,
    $groupTable,
    'parent_group_id',
    $groupTable,
    'group_id',
    Varien_Db_Ddl_Table::ACTION_CASCADE,
    Varien_Db_Ddl_Table::ACTION_CASCADE
);

$installer->endSetup();
