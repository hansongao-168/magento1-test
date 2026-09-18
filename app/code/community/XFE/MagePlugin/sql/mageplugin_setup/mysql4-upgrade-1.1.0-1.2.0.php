<?php
/**
 * XFE_MagePlugin 数据库升级：1.1.0 → 1.2.0
 *
 * 新增 3 张表：
 *   - xfe_mageplugin_admin_ip_whitelist : 后台用户登录 IP 白名单
 *   - xfe_mageplugin_admin_phone        : 后台用户手机号
 *   - xfe_mageplugin_sms_verification   : 短信验证码会话
 */

/** @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

$installer->getConnection()->dropTable($installer->getTable('xfe_mageplugin/admin_whitelist'));
$table = $installer->getConnection()
    ->newTable($installer->getTable('xfe_mageplugin/admin_whitelist'))
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), 'Entity ID')
    ->addColumn('user_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => false,
        'unsigned'  => true,
    ), 'Admin User ID')
    ->addColumn('ip', Varien_Db_Ddl_Table::TYPE_VARCHAR, 45, array(
        'nullable'  => false,
    ), 'Allowed IP')
    ->addColumn('is_auto_added', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
        'nullable'  => false,
        'default'   => 0,
    ), 'Added by SMS verification (1) or manual (0)')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
    ), 'Created At')
    ->addIndex(
        $installer->getIdxName('xfe_mageplugin/admin_whitelist', array('user_id', 'ip'), Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE),
        array('user_id', 'ip'),
        Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
    )
    ->setComment('XFE MagePlugin Admin IP Whitelist');

$installer->getConnection()->createTable($table);

$installer->getConnection()->dropTable($installer->getTable('xfe_mageplugin/admin_phone'));
$table = $installer->getConnection()
    ->newTable($installer->getTable('xfe_mageplugin/admin_phone'))
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), 'Entity ID')
    ->addColumn('user_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => false,
        'unsigned'  => true,
    ), 'Admin User ID')
    ->addColumn('phone', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, array(
        'nullable'  => false,
    ), 'Phone number for SMS verification')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT_UPDATE,
    ), 'Updated At')
    ->addIndex(
        $installer->getIdxName('xfe_mageplugin/admin_phone', array('user_id'), Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE),
        array('user_id'),
        Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
    )
    ->setComment('XFE MagePlugin Admin Phone');

$installer->getConnection()->createTable($table);

$installer->getConnection()->dropTable($installer->getTable('xfe_mageplugin/sms_verification'));
$table = $installer->getConnection()
    ->newTable($installer->getTable('xfe_mageplugin/sms_verification'))
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), 'Entity ID')
    ->addColumn('user_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => false,
        'unsigned'  => true,
    ), 'Admin User ID')
    ->addColumn('phone', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, array(
        'nullable'  => false,
    ), 'Phone number')
    ->addColumn('ip', Varien_Db_Ddl_Table::TYPE_VARCHAR, 45, array(
        'nullable'  => false,
    ), 'Requesting IP')
    ->addColumn('code_hash', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable'  => false,
    ), 'Hashed verification code')
    ->addColumn('verified', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
        'nullable'  => false,
        'default'   => 0,
    ), 'Verified flag')
    ->addColumn('expires_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
        'nullable'  => false,
    ), 'Code expiry')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
    ), 'Created At')
    ->setComment('XFE MagePlugin SMS Verification');

$installer->getConnection()->createTable($table);

$installer->endSetup();
