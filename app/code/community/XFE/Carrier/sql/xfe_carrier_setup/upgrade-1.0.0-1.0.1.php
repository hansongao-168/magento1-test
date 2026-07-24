<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

// 承运商账号表
$table = $installer->getConnection()
    ->newTable($installer->getTable('xfe_carrier/carrier_account'))
    ->addColumn('account_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), '账号主键')
    ->addColumn('carrier_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned'  => true,
        'nullable'  => false,
    ), '关联承运商ID')
    ->addColumn('account_name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => false,
    ), '账号名称')
    ->addColumn('account_no', Varien_Db_Ddl_Table::TYPE_VARCHAR, 128, array(
        'nullable' => true,
    ), '账号编号')
    ->addColumn('api_key', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => true,
    ), 'API Key')
    ->addColumn('api_secret', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => true,
    ), 'API Secret')
    ->addColumn('username', Varien_Db_Ddl_Table::TYPE_VARCHAR, 128, array(
        'nullable' => true,
    ), '登录用户名')
    ->addColumn('password', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => true,
    ), '登录密码')
    ->addColumn('endpoint_url', Varien_Db_Ddl_Table::TYPE_VARCHAR, 512, array(
        'nullable' => true,
    ), 'API端点URL')
    ->addColumn('status', Varien_Db_Ddl_Table::TYPE_TINYINT, 1, array(
        'nullable' => false,
        'default'  => 1,
    ), '状态：1启用/0禁用')
    ->addColumn('sort_order', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable' => false,
        'default'  => 0,
    ), '排序')
    ->addColumn('note', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => true,
    ), '备注')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
        'nullable' => true,
    ), '创建时间')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
        'nullable' => true,
    ), '更新时间')
    ->addIndex($installer->getIdxName('xfe_carrier/carrier_account', array('carrier_id')),
        array('carrier_id'))
    ->addForeignKey(
        $installer->getFkName('xfe_carrier/carrier_account', 'carrier_id', 'xfe_carrier/carrier', 'entity_id'),
        'carrier_id',
        $installer->getTable('xfe_carrier/carrier'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('XFE 承运商账号管理');
$installer->getConnection()->createTable($table);

// 承运商规则表
$table = $installer->getConnection()
    ->newTable($installer->getTable('xfe_carrier/carrier_rule'))
    ->addColumn('rule_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), '规则主键')
    ->addColumn('carrier_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned'  => true,
        'nullable'  => false,
    ), '关联承运商ID')
    ->addColumn('name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => false,
    ), '规则名称')
    ->addColumn('description', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => true,
    ), '规则描述')
    ->addColumn('status', Varien_Db_Ddl_Table::TYPE_TINYINT, 1, array(
        'nullable' => false,
        'default'  => 1,
    ), '状态：1启用/0禁用')
    ->addColumn('sort_order', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable' => false,
        'default'  => 0,
    ), '排序')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
        'nullable' => true,
    ), '创建时间')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
        'nullable' => true,
    ), '更新时间')
    ->addIndex($installer->getIdxName('xfe_carrier/carrier_rule', array('carrier_id')),
        array('carrier_id'))
    ->addForeignKey(
        $installer->getFkName('xfe_carrier/carrier_rule', 'carrier_id', 'xfe_carrier/carrier', 'entity_id'),
        'carrier_id',
        $installer->getTable('xfe_carrier/carrier'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('XFE 承运商规则管理');
$installer->getConnection()->createTable($table);

// 承运商规则条件组表
$table = $installer->getConnection()
    ->newTable($installer->getTable('xfe_carrier/rule_condition_group'))
    ->addColumn('group_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), '条件组主键')
    ->addColumn('rule_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned'  => true,
        'nullable'  => false,
    ), '关联规则ID')
    ->addColumn('sort_order', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable' => false,
        'default'  => 0,
    ), '排序')
    ->addColumn('aggregator', Varien_Db_Ddl_Table::TYPE_VARCHAR, 4, array(
        'nullable' => false,
        'default'  => 'all',
    ), '聚合方式：all=AND / any=OR')
    ->addColumn('parent_group_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned'  => true,
        'nullable'  => true,
        'default'   => null,
    ), '父级条件组ID')
    ->addForeignKey(
        $installer->getFkName('xfe_carrier/rule_condition_group', 'rule_id', 'xfe_carrier/carrier_rule', 'rule_id'),
        'rule_id',
        $installer->getTable('xfe_carrier/carrier_rule'),
        'rule_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('XFE 承运商规则条件组');
$installer->getConnection()->createTable($table);

// 承运商规则条件明细表
$table = $installer->getConnection()
    ->newTable($installer->getTable('xfe_carrier/rule_condition'))
    ->addColumn('condition_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), '条件主键')
    ->addColumn('group_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned'  => true,
        'nullable'  => false,
    ), '关联条件组ID')
    ->addColumn('sort_order', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable' => false,
        'default'  => 0,
    ), '排序')
    ->addColumn('attribute', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, array(
        'nullable' => false,
    ), '条件属性')
    ->addColumn('operator', Varien_Db_Ddl_Table::TYPE_VARCHAR, 16, array(
        'nullable' => false,
    ), '比较运算符')
    ->addColumn('value', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => false,
    ), '比较值')
    ->addForeignKey(
        $installer->getFkName('xfe_carrier/rule_condition', 'group_id', 'xfe_carrier/rule_condition_group', 'group_id'),
        'group_id',
        $installer->getTable('xfe_carrier/rule_condition_group'),
        'group_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('XFE 承运商规则条件明细');
$installer->getConnection()->createTable($table);

$installer->endSetup();
