<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$tableName = $installer->getTable('xfe_carrier/carrier_logo');
$connection = $installer->getConnection();

// 1. Add new columns: label, logo_type, sort_order
$connection->addColumn($tableName, 'label', array(
    'type'    => Varien_Db_Ddl_Table::TYPE_VARCHAR,
    'length'  => 128,
    'nullable' => true,
    'default' => null,
    'comment' => 'Logo name/label',
    'after'   => 'carrier_id',
));

$connection->addColumn($tableName, 'logo_type', array(
    'type'    => Varien_Db_Ddl_Table::TYPE_VARCHAR,
    'length'  => 32,
    'nullable' => true,
    'default' => 'main',
    'comment' => 'Logo type: main/mobile/alt',
    'after'   => 'label',
));

$connection->addColumn($tableName, 'sort_order', array(
    'type'    => Varien_Db_Ddl_Table::TYPE_INTEGER,
    'nullable' => false,
    'default' => 0,
    'comment' => 'Sort order',
    'after'   => 'logo_type',
));

// 2. Drop old UNIQUE index on (carrier_id, size_type)
$oldIndexName = $installer->getIdxName(
    'xfe_carrier/carrier_logo',
    array('carrier_id', 'size_type'),
    Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
);

// The old index name might differ; drop known variants
try {
    $connection->dropIndex($tableName, $oldIndexName);
} catch (Exception $e) {
    // Try alternative index names
    $indexes = $connection->getIndexList($tableName);
    foreach ($indexes as $idxName => $idxInfo) {
        if ($idxInfo['type'] === Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
            && in_array('carrier_id', $idxInfo['fields'])
            && in_array('size_type', $idxInfo['fields'])
        ) {
            try {
                $connection->dropIndex($tableName, $idxName);
            } catch (Exception $e2) {
                // silent
            }
        }
    }
}

// 3. Add a plain index on carrier_id (non-unique, for joins)
$newIdxName = $installer->getIdxName('xfe_carrier/carrier_logo', array('carrier_id'));
try {
    $connection->addIndex($tableName, $newIdxName, array('carrier_id'));
} catch (Exception $e) {
    // Might already exist
}

// 4. Add index on logo_type for filtering
$typeIdx = $installer->getIdxName('xfe_carrier/carrier_logo', array('carrier_id', 'logo_type'));
try {
    $connection->addIndex($tableName, $typeIdx, array('carrier_id', 'logo_type'));
} catch (Exception $e) {
    // Might already exist
}

// 5. Migrate existing data: remove size_type value, set logo_type based on size_type
// Mark original entries as 'main' type, small/medium/large as 'alt'
// Add sort_order based on size_type ordering
$sizeTypeMap = array(
    'original' => array('type' => 'main', 'sort' => 10),
    'small'    => array('type' => 'main', 'sort' => 20),
    'medium'   => array('type' => 'main', 'sort' => 30),
    'large'    => array('type' => 'main', 'sort' => 40),
);

$existingData = $connection->fetchAll("SELECT * FROM `{$tableName}` WHERE logo_type IS NULL OR logo_type = ''");
foreach ($existingData as $row) {
    $sizeType = !empty($row['size_type']) ? $row['size_type'] : 'original';
    $mapping  = isset($sizeTypeMap[$sizeType]) ? $sizeTypeMap[$sizeType] : $sizeTypeMap['original'];

    $connection->update(
        $tableName,
        array(
            'logo_type'  => $mapping['type'],
            'sort_order' => $mapping['sort'],
        ),
        array('logo_id = ?' => $row['logo_id'])
    );
}

$installer->endSetup();
