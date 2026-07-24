<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

// Add `is_cancel_on_failure` to xfe_carrier_carrier_rule so the
// Rule Edit form's "失败时取消" field has somewhere to land, and so the
// new inline rule editor on the Account Edit page can persist it.
$tableName  = $installer->getTable('xfe_carrier/carrier_rule');
$connection = $installer->getConnection();

if (!$connection->tableColumnExists($tableName, 'is_cancel_on_failure')) {
    $connection->query(sprintf(
        'ALTER TABLE %s ADD COLUMN is_cancel_on_failure TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'Whether matching failure cancels further rule attempts\' AFTER status',
        $tableName
    ));
}

$installer->endSetup();