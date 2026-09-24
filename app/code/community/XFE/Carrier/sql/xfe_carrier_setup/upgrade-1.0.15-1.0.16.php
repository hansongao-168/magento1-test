<?php
/**
 * Upgrade 1.0.15 -> 1.0.16
 *
 * 新增 logo_id 列到 xfe_carrier_carrier_rule，使 logo 级规则可指向具体的 carrier_logo（之前只能按 module_code=logo 过滤，错误地把该 carrier 下所有 logo 级规则都列出）。
 *
 * 与已有的 account_id (1.0.6-1.0.7) + ftp_account_id (1.0.9-1.0.10) 类似：
 *   - logo_id INT UNSIGNED NULL DEFAULT NULL
 *   - INDEX idx_logo_id
 *   - FK 到 xfe_carrier_carrier_logo.logo_id (与 logo 主表一致)
 *
 * 幂等：re-run 在已完成完本次升级的数据库上是空操作。
 *
 * 参考：docs/architecture/decisions/0030-resource-level-rule-add-modal.md
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$ruleTable = $installer->getTable('xfe_carrier/carrier_rule');
$logoTable = $installer->getTable('xfe_carrier/carrier_logo');

if (!$connection->tableColumnExists($ruleTable, 'logo_id')) {
    $connection->addColumn(
        $ruleTable,
        'logo_id',
        array(
            'type'     => Varien_Db_Ddl_Table::TYPE_INTEGER,
            'unsigned' => true,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'Logo ID (外键到 xfe_carrier_carrier_logo)'
        )
    );

    $idxName = $installer->getIdxName('xfe_carrier/carrier_rule', array('logo_id'));
    $connection->addIndex($ruleTable, $idxName, array('logo_id'));

    $fkName = $installer->getFkName(
        'xfe_carrier/carrier_rule',
        'logo_id',
        'xfe_carrier/carrier_logo',
        'logo_id'
    );
    $connection->addForeignKey(
        $fkName,
        $ruleTable,
        'logo_id',
        $logoTable,
        'logo_id',
        Varien_Db_Ddl_Table::ACTION_SET_NULL,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    );
}

$installer->endSetup();
