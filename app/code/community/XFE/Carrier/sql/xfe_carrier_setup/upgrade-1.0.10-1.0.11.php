<?php
/**
 * Upgrade 1.0.10 -> 1.0.11
 *
 * Make xfe_carrier.shipping_company_id SIGNED so it can hold the negative
 * placeholder IDs the picker uses for built-in carriers (e.g. -1001..-1010
 * for 顺丰国际标快 / 顺丰国际特惠 / FedEx-IE / ...).
 *
 * Background:
 *   - The picker in
 *     XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_General::_getShippingCompanyList()
 *     uses large negative IDs (-1001..-1010) as a namespace for built-in
 *     shipping companies that have no real carrier_account row.
 *   - The original install-1.0.0.php declared the column as INT UNSIGNED,
 *     which silently truncates negative values to 0 in MySQL strict mode
 *     and in MySQL non-strict mode. As a result, every save through the
 *     admin edit form ended up with shipping_company_id = 0 even when
 *     the user picked a real option in the dropdown.
 *   - Changing the column to SIGNED INT restores the negative range we
 *     need for the picker without altering existing rows (no existing
 *     value is negative, so the conversion is a no-op data-wise).
 *
 * Idempotent / safe to re-run.
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$carrierTable = $installer->getTable('xfe_carrier/carrier');

// describeColumn gives the column definition; we only change the sign.
$describe = $connection->describeColumn($carrierTable, 'shipping_company_id');
if ($describe && !empty($describe['UNSIGNED'])) {
    // Drop the index that depends on the column before altering, then
    // recreate it with the same definition. Magento's MODIFY COLUMN goes
    // through the same code path as CREATE TABLE for index handling, so
    // we proactively drop/recreate to be safe on MySQL < 8.0.
    $idxName = $installer->getIdxName('xfe_carrier/carrier', array('shipping_company_id'));
    $indexList = $connection->getIndexList($carrierTable);
    if (isset($indexList[strtoupper($idxName)])) {
        $connection->dropIndex($carrierTable, $idxName);
    }

    $connection->query(sprintf(
        'ALTER TABLE %s MODIFY COLUMN shipping_company_id INT NULL DEFAULT NULL '
        . 'COMMENT \'线路公司ID\'',
        $carrierTable
    ));

    $connection->addIndex($carrierTable, $idxName, array('shipping_company_id'));
}

$installer->endSetup();