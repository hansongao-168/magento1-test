<?php
/**
 * XFE_OAuth2 install script - Create 5 database tables
 *
 * @category   Community
 * @package    XFE_OAuth2
 * @version     1.0.0
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/* xfe_oauth2_client */
$table = $installer->getConnection()
    ->newTable($installer->getTable('xfeoauth2/client'))
    ->addColumn('client_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 80, array(
        'nullable' => false,
        'primary'  => true,
    ), 'Client ID')
    ->addColumn('client_secret', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => false,
    ), 'Client Secret (bcrypt)')
    ->addColumn('name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => false,
    ), 'Client Name')
    ->addColumn('description', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => true,
    ), 'Description')
    ->addColumn('redirect_uri', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => true,
    ), 'Redirect URI')
    ->addColumn('grant_types', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'default'  => '',
    ), 'Grant Types')
    ->addColumn('scopes', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'default'  => 'basic',
    ), 'Allowed Scopes')
    ->addColumn('user_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 10, array(
        'unsigned' => true,
        'nullable' => true,
    ), 'Creator Admin User ID')
    ->addColumn('status', Varien_Db_Ddl_Table::TYPE_SMALLINT, 6, array(
        'nullable' => false,
        'default'  => 1,
    ), 'Status (1=active, 0=disabled)')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(), 'Created At')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(), 'Updated At')
    ->setComment('XFE OAuth2 Clients');

$installer->getConnection()->createTable($table);

/* xfe_oauth2_access_token */
$table = $installer->getConnection()
    ->newTable($installer->getTable('xfeoauth2/access_token'))
    ->addColumn('access_token', Varien_Db_Ddl_Table::TYPE_VARCHAR, 80, array(
        'nullable' => false,
        'primary'  => true,
    ), 'Access Token')
    ->addColumn('client_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 80, array(
        'nullable' => false,
    ), 'Client ID')
    ->addColumn('user_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 10, array(
        'unsigned' => true,
        'nullable' => true,
    ), 'User ID')
    ->addColumn('user_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 20, array(
        'default'  => 'customer',
    ), 'User Type (customer/admin)')
    ->addColumn('expires', Varien_Db_Ddl_Table::TYPE_INTEGER, 11, array(
        'nullable' => false,
    ), 'Expires (Unix timestamp)')
    ->addColumn('scope', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => true,
    ), 'Scope')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(), 'Created At')
    ->addForeignKey(
        $installer->getFkName('xfeoauth2/access_token', 'client_id', 'xfeoauth2/client', 'client_id'),
        'client_id',
        $installer->getTable('xfeoauth2/client'),
        'client_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('XFE OAuth2 Access Tokens');

$installer->getConnection()->createTable($table);

/* xfe_oauth2_refresh_token */
$table = $installer->getConnection()
    ->newTable($installer->getTable('xfeoauth2/refresh_token'))
    ->addColumn('refresh_token', Varien_Db_Ddl_Table::TYPE_VARCHAR, 80, array(
        'nullable' => false,
        'primary'  => true,
    ), 'Refresh Token')
    ->addColumn('client_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 80, array(
        'nullable' => false,
    ), 'Client ID')
    ->addColumn('user_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 10, array(
        'unsigned' => true,
        'nullable' => true,
    ), 'User ID')
    ->addColumn('user_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 20, array(
        'default'  => 'customer',
    ), 'User Type')
    ->addColumn('expires', Varien_Db_Ddl_Table::TYPE_INTEGER, 11, array(
        'nullable' => false,
    ), 'Expires (Unix timestamp)')
    ->addColumn('scope', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => true,
    ), 'Scope')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(), 'Created At')
    ->addForeignKey(
        $installer->getFkName('xfeoauth2/refresh_token', 'client_id', 'xfeoauth2/client', 'client_id'),
        'client_id',
        $installer->getTable('xfeoauth2/client'),
        'client_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('XFE OAuth2 Refresh Tokens');

$installer->getConnection()->createTable($table);

/* xfe_oauth2_authorization_code */
$table = $installer->getConnection()
    ->newTable($installer->getTable('xfeoauth2/authorization_code'))
    ->addColumn('code', Varien_Db_Ddl_Table::TYPE_VARCHAR, 80, array(
        'nullable' => false,
        'primary'  => true,
    ), 'Authorization Code')
    ->addColumn('client_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 80, array(
        'nullable' => false,
    ), 'Client ID')
    ->addColumn('user_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 10, array(
        'unsigned' => true,
        'nullable' => false,
    ), 'User ID')
    ->addColumn('user_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 20, array(
        'default'  => 'customer',
    ), 'User Type')
    ->addColumn('redirect_uri', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => true,
    ), 'Redirect URI')
    ->addColumn('expires', Varien_Db_Ddl_Table::TYPE_INTEGER, 11, array(
        'nullable' => false,
    ), 'Expires (Unix timestamp)')
    ->addColumn('scope', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => true,
    ), 'Scope')
    ->addColumn('id_token', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => true,
    ), 'ID Token')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(), 'Created At')
    ->addForeignKey(
        $installer->getFkName('xfeoauth2/authorization_code', 'client_id', 'xfeoauth2/client', 'client_id'),
        'client_id',
        $installer->getTable('xfeoauth2/client'),
        'client_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('XFE OAuth2 Authorization Codes');

$installer->getConnection()->createTable($table);

/* xfe_oauth2_social_account */
$table = $installer->getConnection()
    ->newTable($installer->getTable('xfeoauth2/social_account'))
    ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, 10, array(
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ), 'ID')
    ->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 10, array(
        'unsigned' => true,
        'nullable' => false,
    ), 'Customer ID')
    ->addColumn('provider', Varien_Db_Ddl_Table::TYPE_VARCHAR, 50, array(
        'nullable' => false,
    ), 'Provider (google/wechat)')
    ->addColumn('provider_user_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => false,
    ), 'Provider User ID')
    ->addColumn('email', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => true,
    ), 'Email')
    ->addColumn('name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => true,
    ), 'Display Name')
    ->addColumn('avatar_url', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => true,
    ), 'Avatar URL')
    ->addColumn('access_token', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => true,
    ), 'Access Token (encrypted)')
    ->addColumn('refresh_token', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => true,
    ), 'Refresh Token (encrypted)')
    ->addColumn('expires_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
        'nullable' => true,
    ), 'Token Expires At')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(), 'Created At')
    ->addForeignKey(
        $installer->getFkName('xfeoauth2/social_account', 'customer_id', 'customer/entity', 'entity_id'),
        'customer_id',
        $installer->getTable('customer/entity'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->addIndex(
        $installer->getIdxName(
            'xfeoauth2/social_account',
            array('provider', 'provider_user_id'),
            Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
        ),
        array('provider', 'provider_user_id'),
        array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE)
    )
    ->setComment('XFE OAuth2 Social Accounts');

$installer->getConnection()->createTable($table);

$installer->endSetup();
