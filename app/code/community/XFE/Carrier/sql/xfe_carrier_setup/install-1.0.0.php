<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

// 承运商主表
$table = $installer->getConnection()
    ->newTable($installer->getTable('xfe_carrier/carrier'))
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), '主键')
    ->addColumn('name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => false,
    ), '承运商名称')
    ->addColumn('code', Varien_Db_Ddl_Table::TYPE_VARCHAR, 64, array(
        'nullable' => false,
    ), '承运商标识/代码')
    ->addColumn('shipping_company_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned'  => true,
        'nullable'  => true,
        'default'   => null,
    ), '线路公司ID')
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
        'default'  => null,
    ), '备注')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
        'nullable' => true,
    ), '创建时间')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
        'nullable' => true,
    ), '更新时间')
    ->addIndex($installer->getIdxName('xfe_carrier/carrier', array('shipping_company_id')),
        array('shipping_company_id'))
    ->addIndex($installer->getIdxName('xfe_carrier/carrier', array('status')),
        array('status'))
    ->setComment('XFE 承运商管理');
$installer->getConnection()->createTable($table);

// Logo 子表
$logoTable = $installer->getConnection()
    ->newTable($installer->getTable('xfe_carrier/carrier_logo'))
    ->addColumn('logo_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), 'Logo主键')
    ->addColumn('carrier_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned'  => true,
        'nullable'  => false,
    ), '关联承运商ID')
    ->addColumn('size_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 20, array(
        'nullable' => false,
        'default'  => 'original',
    ), '尺寸标识：original/small/medium/large')
    ->addColumn('path', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => false,
    ), '文件相对路径')
    ->addColumn('width', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
        'unsigned'  => true,
        'nullable'  => true,
        'default'   => null,
    ), '图片宽度')
    ->addColumn('height', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
        'unsigned'  => true,
        'nullable'  => true,
        'default'   => null,
    ), '图片高度')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
        'nullable' => true,
    ), '创建时间')
    ->addIndex($installer->getIdxName('xfe_carrier/carrier_logo', array('carrier_id')),
        array('carrier_id'))
    ->addIndex($installer->getIdxName('xfe_carrier/carrier_logo', array('carrier_id', 'size_type'),
        Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE),
        array('carrier_id', 'size_type'),
        array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE))
    ->setComment('XFE 承运商 Logo');
$installer->getConnection()->createTable($logoTable);

$installer->endSetup();
