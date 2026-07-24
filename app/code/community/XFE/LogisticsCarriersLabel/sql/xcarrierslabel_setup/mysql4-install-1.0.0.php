<?php
/**
 * XFE LogisticsCarriersLabel Setup
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */

$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */

$installer->startSetup();

/**
 * Create xcarrierslabel_rule table
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('xcarrierslabel/rule'))
    ->addColumn('rule_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), 'Rule ID')
    ->addColumn('country_code', Varien_Db_Ddl_Table::TYPE_VARCHAR, 4, array(
        'nullable'  => false,
    ), 'Country Code (DE, AT, BE, etc.)')
    ->addColumn('country_name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 100, array(
        'nullable'  => false,
    ), 'Country Name')
    ->addColumn('partner_name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 64, array(
        'nullable'  => false,
    ), 'Partner Name (DPD, Speedy, Postnord, etc.)')
    ->addColumn('shipping_company_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => false,
        'default'   => '0',
    ), 'Shipping Company ID')
    ->addColumn('tracking_url_template', Varien_Db_Ddl_Table::TYPE_VARCHAR, 512, array(
        'nullable'  => true,
    ), 'Tracking URL Template with {tracking_number} and {zip} placeholders')
    ->addColumn('display_title', Varien_Db_Ddl_Table::TYPE_VARCHAR, 128, array(
        'nullable'  => true,
        'default'   => 'Chrono Classic',
    ), 'Display Title (e.g. Chrono Classic)')
    ->addColumn('background_color', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, array(
        'nullable'  => true,
    ), 'Background Color')
    ->addColumn('text_color', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, array(
        'nullable'  => true,
    ), 'Text Color')
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
    ->setComment('XFE Logistic Carriers Label Rule Table');

$installer->getConnection()->createTable($table);

/**
 * Create xcarrierslabel_condition_group table
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('xcarrierslabel/condition_group'))
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
        $installer->getFkName('xcarrierslabel/condition_group', 'rule_id', 'xcarrierslabel/rule', 'rule_id'),
        'rule_id',
        $installer->getTable('xcarrierslabel/rule'),
        'rule_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('XFE Logistic Carriers Label Condition Group Table');

$installer->getConnection()->createTable($table);

/**
 * Create xcarrierslabel_condition table
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('xcarrierslabel/condition'))
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
        $installer->getFkName('xcarrierslabel/condition', 'group_id', 'xcarrierslabel/condition_group', 'group_id'),
        'group_id',
        $installer->getTable('xcarrierslabel/condition_group'),
        'group_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('XFE Logistic Carriers Label Condition Item Table');

$installer->getConnection()->createTable($table);

/**
 * Insert initial data for 22 countries
 */
$installer->getConnection()->insertMultiple(
    $installer->getTable('xcarrierslabel/rule'),
    array(
        array('country_code' => 'DE', 'country_name' => 'Allemagne', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://my.dpd.de/myParcel.aspx?parcelno={tracking_number}&zip={zip}', 'status' => 1),
        array('country_code' => 'AT', 'country_name' => 'Autriche', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://www.dpdgroup.com/fr/mydpd/my-parcels/incoming?parcelNumber={tracking_number}&lang=en', 'status' => 1),
        array('country_code' => 'BE', 'country_name' => 'Belgique', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://www.dpdgroup.com/be/mydpd/my-parcels/incoming?parcelNumber={tracking_number}', 'status' => 1),
        array('country_code' => 'BG', 'country_name' => 'Bulgarie', 'partner_name' => 'Speedy', 'tracking_url_template' => 'https://www.speedy.bg/en/track-shipment?shipmentNumber={tracking_number}', 'status' => 1),
        array('country_code' => 'HR', 'country_name' => 'Croatie', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://www.dpdgroup.com/hr/mydpd/my-parcels/incoming?parcelNumber={tracking_number}', 'status' => 1),
        array('country_code' => 'DK', 'country_name' => 'Danemark', 'partner_name' => 'Postnord', 'tracking_url_template' => 'https://www.postnord.dk/en/tools/track-and-trace/?shipmentId={tracking_number}', 'status' => 1),
        array('country_code' => 'ES', 'country_name' => 'Espagne', 'partner_name' => 'SEUR', 'tracking_url_template' => 'https://www.dpdgroup.com/fr/mydpd/my-parcels/incoming?parcelNumber={tracking_number}&lang=en', 'status' => 1),
        array('country_code' => 'EE', 'country_name' => 'Estonie', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://www.dpdgroup.com/ee/mydpd/my-parcels/incoming?parcelNumber={tracking_number}', 'status' => 1),
        array('country_code' => 'FI', 'country_name' => 'Finlande', 'partner_name' => 'Postnord', 'tracking_url_template' => 'https://www.postnord.fi/en/our-tools/track-and-trace/?shipmentId={tracking_number}', 'status' => 1),
        array('country_code' => 'GR', 'country_name' => 'Grèce', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://services.dpd.gr/tracking/?shipmentNumber={tracking_number}&language=en', 'status' => 1),
        array('country_code' => 'HU', 'country_name' => 'Hongrie', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://www.dpdgroup.com/hu/mydpd/my-parcels/incoming?parcelNumber={tracking_number}', 'status' => 1),
        array('country_code' => 'IE', 'country_name' => 'Irlande', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://www.dpdgroup.com/ie/mydpd/my-parcels/incoming?parcelNumber={tracking_number}', 'status' => 1),
        array('country_code' => 'IT', 'country_name' => 'Italie', 'partner_name' => 'BRT', 'tracking_url_template' => 'https://www.mybrt.it/it/mybrt/my-parcels/incoming?parcelNumber={tracking_number}', 'status' => 1),
        array('country_code' => 'LV', 'country_name' => 'Lettonie', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://www.dpdgroup.com/lv/mydpd/my-parcels/incoming?parcelNumber={tracking_number}', 'status' => 1),
        array('country_code' => 'LT', 'country_name' => 'Litanie', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://www.dpdgroup.com/lt/mydpd/my-parcels/incoming?parcelNumber={tracking_number}', 'status' => 1),
        array('country_code' => 'LU', 'country_name' => 'Luxembourg', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://www.dpdgroup.com/lu/mydpd/my-parcels/incoming?parcelNumber={tracking_number}', 'status' => 1),
        array('country_code' => 'NL', 'country_name' => 'Pays-Bas', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://www.dpdgroup.com/nl/mydpd/my-parcels/incoming?parcelNumber={tracking_number}', 'status' => 1),
        array('country_code' => 'PL', 'country_name' => 'Pologne', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://tracktrace.dpd.com.pl/parcelDetails?typ=1&p1={tracking_number}', 'status' => 1),
        array('country_code' => 'PT', 'country_name' => 'Portugal', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://www.dpdgroup.com/pt/mydpd/my-parcels/incoming?parcelNumber={tracking_number}', 'status' => 1),
        array('country_code' => 'RO', 'country_name' => 'Roumanie', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://services.dpd.ro/tracking/?shipmentNumber={tracking_number}&language=en', 'status' => 1),
        array('country_code' => 'GB', 'country_name' => 'Royaume-Uni', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://www.dpdgroup.com/fr/mydpd/my-parcels/incoming?parcelNumber={tracking_number}&lang=en', 'status' => 1),
        array('country_code' => 'SK', 'country_name' => 'Slovaquie', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://www.dpdgroup.com/sk/mydpd/my-parcels/incoming?parcelNumber={tracking_number}', 'status' => 1),
        array('country_code' => 'SI', 'country_name' => 'Slovénie', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://www.dpdgroup.com/si/mydpd/my-parcels/incoming?parcelNumber={tracking_number}', 'status' => 1),
        array('country_code' => 'SE', 'country_name' => 'Suède', 'partner_name' => 'Postnord', 'tracking_url_template' => 'https://www.postnord.se/en/our-tools/track-and-trace/?shipmentId={tracking_number}', 'status' => 1),
        array('country_code' => 'CH', 'country_name' => 'Suisse', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://www.dpdgroup.com/ch/mydpd/my-parcels/incoming?parcelNumber={tracking_number}', 'status' => 1),
        array('country_code' => 'CZ', 'country_name' => 'Tcheque', 'partner_name' => 'DPD', 'tracking_url_template' => 'https://www.dpdgroup.com/cz/mydpd/my-parcels/incoming?parcelNumber={tracking_number}', 'status' => 1),
    )
);

$installer->endSetup();
