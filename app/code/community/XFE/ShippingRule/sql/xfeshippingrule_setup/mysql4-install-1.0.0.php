<?php
/**
 * XFE ShippingRule Setup
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */

$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */

$installer->startSetup();

/**
 * Create xfeshipping_type table
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('xfeshippingrule/type'))
    ->addColumn('type_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), 'Type ID')
    ->addColumn('calculation_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, array(
        'nullable'  => false,
    ), 'Calculation Type (fixed/percent/weight)')
    ->addColumn('nature', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, array(
        'nullable'  => false,
    ), 'Nature (normal/promo/special)')
    ->addColumn('description', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable'  => true,
    ), 'Type Description')
    ->addColumn('status', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
        'nullable'  => false,
        'default'   => '1',
    ), 'Status (1=Enabled, 0=Disabled)')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
    ), 'Created At')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT_UPDATE,
    ), 'Updated At')
    ->setComment('XFE Shipping Rule Type Table');

$installer->getConnection()->createTable($table);

/**
 * Create xfeshipping_rule table
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('xfeshippingrule/rule'))
    ->addColumn('rule_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), 'Rule ID')
    ->addColumn('type_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => false,
        'unsigned'  => true,
    ), 'Type ID')
    ->addColumn('billing_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 16, array(
        'nullable'  => false,
    ), 'Billing Type (per_order/per_package)')
    ->addColumn('shipping_fee', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', array(
        'nullable'  => false,
        'default'   => '0.0000',
    ), 'Shipping Fee')
    ->addColumn('package_min', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => false,
        'default'   => '1',
    ), 'Package Count Min')
    ->addColumn('package_max', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => true,
    ), 'Package Count Max (NULL=unlimited)')
    ->addColumn('description', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable'  => true,
    ), 'Rule Description')
    ->addColumn('status', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
        'nullable'  => false,
        'default'   => '1',
    ), 'Status (1=Enabled, 0=Disabled)')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
    ), 'Created At')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT_UPDATE,
    ), 'Updated At')
    ->addForeignKey(
        $installer->getFkName('xfeshippingrule/rule', 'type_id', 'xfeshippingrule/type', 'type_id'),
        'type_id',
        $installer->getTable('xfeshippingrule/type'),
        'type_id',
        Varien_Db_Ddl_Table::ACTION_RESTRICT,
        Varien_Db_Ddl_Table::ACTION_RESTRICT
    )
    ->setComment('XFE Shipping Rule Main Table');

$installer->getConnection()->createTable($table);

/**
 * Create xfeshipping_condition_group table
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('xfeshippingrule/condition_group'))
    ->addColumn('group_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), 'Group ID')
    ->addColumn('rule_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => false,
        'unsigned'  => true,
    ), 'Rule ID')
    ->addColumn('sort_order', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => false,
        'default'   => '0',
    ), 'Sort Order')
    ->addColumn('aggregator', Varien_Db_Ddl_Table::TYPE_VARCHAR, 4, array(
        'nullable'  => false,
        'default'   => 'all',
    ), 'Aggregator (all=AND, any=OR)')
    ->addForeignKey(
        $installer->getFkName('xfeshippingrule/condition_group', 'rule_id', 'xfeshippingrule/rule', 'rule_id'),
        'rule_id',
        $installer->getTable('xfeshippingrule/rule'),
        'rule_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('XFE Shipping Rule Condition Group Table');

$installer->getConnection()->createTable($table);

/**
 * Create xfeshipping_condition table
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('xfeshippingrule/condition'))
    ->addColumn('condition_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), 'Condition ID')
    ->addColumn('group_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => false,
        'unsigned'  => true,
    ), 'Group ID')
    ->addColumn('sort_order', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => false,
        'default'   => '0',
    ), 'Sort Order')
    ->addColumn('attribute', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, array(
        'nullable'  => false,
    ), 'Condition Attribute')
    ->addColumn('operator', Varien_Db_Ddl_Table::TYPE_VARCHAR, 16, array(
        'nullable'  => false,
    ), 'Comparison Operator')
    ->addColumn('value', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable'  => false,
    ), 'Comparison Value')
    ->addForeignKey(
        $installer->getFkName('xfeshippingrule/condition', 'group_id', 'xfeshippingrule/condition_group', 'group_id'),
        'group_id',
        $installer->getTable('xfeshippingrule/condition_group'),
        'group_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('XFE Shipping Rule Condition Item Table');

$installer->getConnection()->createTable($table);

/**
 * Insert preset type data
 */
$installer->getConnection()->insertMultiple(
    $installer->getTable('xfeshippingrule/type'),
    array(
        array(
            'calculation_type' => 'fixed',
            'nature'           => 'normal',
            'description'      => '固定运费-普通规则',
            'status'           => 1,
        ),
        array(
            'calculation_type' => 'fixed',
            'nature'           => 'promo',
            'description'      => '固定运费-促销规则',
            'status'           => 1,
        ),
        array(
            'calculation_type' => 'percent',
            'nature'           => 'normal',
            'description'      => '百分比运费-普通规则',
            'status'           => 1,
        ),
        array(
            'calculation_type' => 'weight',
            'nature'           => 'normal',
            'description'      => '按重量运费-普通规则',
            'status'           => 1,
        ),
    )
);

$installer->endSetup();
