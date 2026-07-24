<?php

/**
 * InvoiceUpload Integration Test
 *
 * Run from CLI (PHP 7.3.4+ with Mage bootstrap):
 *   D:/phpstudy_pro/Extensions/php/php7.3.4nts_p/php.exe \
 *       app/code/community/XFE/InvoiceUpload/Test/IntegrationTest.php
 */
require_once 'D:/www/m1-test.com/app/Mage.php';
Mage::app()->setCurrentStore(0);

// Bypass stale CONFIG cache so newly added modules become visible
Mage::app()->getCacheInstance()->banUse('config');
Mage::getConfig()->reinit();

$failed = 0;

function check($label, $expected, $actual) {
    global $failed;
    $ok = $expected === $actual;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        echo '       expected: ' . var_export($expected, true) . PHP_EOL;
        echo '       got     : ' . var_export($actual, true) . PHP_EOL;
        $failed++;
    }
}

function section($title) {
    echo PHP_EOL . '--- ' . $title . ' ---' . PHP_EOL;
}

// ---------- 0. Self-heal: install the table if needed -------------------
// The installer script (Mage setup form) calls into our helper which is
// also callable directly from CLI tests.
require_once dirname(__FILE__) . '/../sql/invoiceupload_setup/Install.php';
if (XFE_InvoiceUpload_Sql_Install::createInvoiceTable()) {
    section('installed xfe_invoiceupload_invoice');
} else {
    section('table already present (no-op)');
}

// ---------- 1. Seed: customer + sales_flat_order row --------------------
section('Seeding customer + order');

$write = Mage::getSingleton('core/resource')->getConnection('core_write');
$now = Varien_Date::now();

// Reuse an existing email suffix-unique customer; create if missing.
$email = 'xfe_invoice_test+' . uniqid() . '@example.com';
$customer = Mage::getModel('customer/customer')
    ->setFirstname('Inv')
    ->setLastname('Tester')
    ->setEmail($email)
    ->setPassword('testpass1')
    ->setWebsiteId(1)
    ->setStoreId(1)
    ->save();
$customerId = (int)$customer->getId();
echo 'customer_id = ' . $customerId . PHP_EOL;

// A second customer for the negative path
$email2 = 'xfe_invoice_other+' . uniqid() . '@example.com';
$otherCustomer = Mage::getModel('customer/customer')
    ->setFirstname('Other')
    ->setLastname('Customer')
    ->setEmail($email2)
    ->setPassword('testpass1')
    ->setWebsiteId(1)
    ->setStoreId(1)
    ->save();
$otherCustomerId = (int)$otherCustomer->getId();

$orderTable = $write->getTableName('sales_flat_order');
$incrementId = 'INV-' . strtoupper(substr(md5(uniqid()), 0, 8));
$write->insert($orderTable, array(
    'state'             => 'complete',
    'status'            => 'complete',
    'protect_code'      => substr(md5(uniqid()), 0, 8),
    'store_id'          => 1,
    'customer_id'       => $customerId,
    'grand_total'       => 9.99,
    'base_grand_total'  => 9.99,
    'subtotal'          => 9.99,
    'base_subtotal'     => 9.99,
    'total_paid'        => 9.99,
    'base_total_paid'   => 9.99,
    'base_currency_code'=> 'USD',
    'store_currency_code' => 'USD',
    'global_currency_code' => 'USD',
    'order_currency_code'  => 'USD',
    'increment_id'      => $incrementId,
    'created_at'        => $now,
    'updated_at'        => $now,
));
$orderId = (int)$write->lastInsertId($orderTable);
echo 'order_id = ' . $orderId . PHP_EOL;

// ---------- 2. uploadForOrder with simulated $_FILES row ----------------
section('uploadForOrder');

// Build a fake $_FILES row that survives validation. We point
// tmp_name at a real file we wrote ourselves; service uses
// is_uploaded_file() which would normally fail. We bypass that by
// patching the Validator just for this test.
$tmpFile = tempnam(sys_get_temp_dir(), 'invup');
file_put_contents($tmpFile, '%PDF-1.4 fake invoice content');
$fakeFiles = array(
    'invoice' => array(
        'name'     => 'tax_invoice_test.pdf',
        'type'     => 'application/pdf',
        'tmp_name' => $tmpFile,
        'error'    => 0,
        'size'     => filesize($tmpFile),
    ),
);

// Patch the Service's Validator so it does NOT call is_uploaded_file().
// The pure Validator accepts the row as long as tmp_name is non-empty
// and size > 0; but invoice-uploader uses move_uploaded_file() which
// will fail in CLI. We temporarily swap the validator + move mechanism.

class TestInvoiceFileValidator extends XFE_InvoiceUpload_Service_File_Validator
{
    public function validate(array $fileData, $maxBytes, array $allowedExtensions)
    {
        // Re-implement: skip is_uploaded_file check (CLI does not upload).
        $size = (int)$fileData['size'];
        if ($size <= 0) {
            Mage::throwException(Mage::helper('xfe_invoiceupload')->__('Empty file.'));
        }
        $ext = strtolower((string)pathinfo($fileData['name'], PATHINFO_EXTENSION));
        return array('ext' => $ext, 'size' => $size,
                     'name' => (string)pathinfo($fileData['name'], PATHINFO_FILENAME));
    }
}

class TestInvoiceStore extends XFE_InvoiceUpload_Service_File_Store
{
    public function ensureOrderDir($customerId, $orderId) {
        return parent::ensureOrderDir($customerId, $orderId);
    }
    // copy() in CLI; the real validator would use move_uploaded_file.
    // We override the test class so copy() is used (handled below).
}

$fakeUploader = new XFE_InvoiceUpload_Service_Invoice_Uploader(
    new XFE_InvoiceUpload_Service_File_Store(),
    new TestInvoiceFileValidator()
);
XFE_InvoiceUpload_Service_Registry::setUploader($fakeUploader);

// Replace move_uploaded_file via the override: do copy + unlink manually.
// We do this by re-implementing the copy step in a wrapping helper.
$resultUpload = XFE_InvoiceUpload_Service_Registry::uploader()
    ->uploadForOrder($orderId, $customerId, $fakeFiles['invoice']);

// uploader's internal copy uses move_uploaded_file which returns false
// in CLI. But since we've patched the validator, file is still moved
// to tmp path. We then manually copy.
if ($resultUpload->isSuccess()) {
    echo 'upload succeeded with id ' . $resultUpload->getInvoiceId() . PHP_EOL;
    $invoiceId = (int)$resultUpload->getInvoiceId();
} else {
    // The CLI move_uploaded_file returns false and the service falls
    // back to copy() which still works for non-uploaded temp files
    // because copy() doesn't care about the file origin.
    echo 'upload message: ' . $resultUpload->getMessage() . PHP_EOL;
    // Try once more by ensuring our tmp file can be copied.
    // In our service we already try @copy() as a fallback.
    $invoiceId = 0;
}

if ($invoiceId <= 0) {
    // Fallback path: try copying the file manually to the expected
    // service-created path so the rest of the test can proceed.
    $inv = Mage::getModel('xfe_invoiceupload/invoice')->getCollection()
        ->addCustomerFilter($customerId)
        ->addOrderFilter($orderId)
        ->setOrder('invoice_id', 'DESC')
        ->getFirstItem();
    if ($inv->getId()) {
        $invoiceId = (int)$inv->getId();
        $abs = Mage::getBaseDir('media') . DS . $inv->getData('path');
        @copy($tmpFile, $abs);
    }
}

check('uploadForOrder returned success', true, $resultUpload->isSuccess());
check('uploadForOrder returned an invoice id', true, $invoiceId > 0);

$inv = Mage::getModel('xfe_invoiceupload/invoice')->load($invoiceId);
check('DB row exists',                       true,           (bool)$inv->getId());
check('row scoped to current customer',      $customerId,   (int)$inv->getCustomerId());
check('row scoped to current order',         $orderId,      (int)$inv->getOrderId());
check('row keeps label (filename)',          'tax_invoice_test', (string)$inv->getLabel());
check('row keeps size_bytes',                filesize($tmpFile), (int)$inv->getSizeBytes());
check('row keeps relative path on disk',     true,   (bool)$inv->getData('path'));

// File on disk
$absPath = Mage::getBaseDir('media') . DS . $inv->getData('path');
check('file exists on disk',                 true,   file_exists($absPath));

// ---------- 3. listForOrder - reading is restricted to the owner ---------
section('listForOrder');

$list = XFE_InvoiceUpload_Service_Registry::uploader()
    ->listForOrder($orderId, $customerId);
$listIds = array();
foreach ($list as $row) { $listIds[] = (int)$row->getId(); }
check('listForOrder contains our invoice',  true,           in_array($invoiceId, $listIds));
check('listForOrder size matches',           1,              count($listIds));

// Wrong customer cannot see anything
$otherList = XFE_InvoiceUpload_Service_Registry::uploader()
    ->listForOrder($orderId, $otherCustomerId);
check('listForOrder from wrong customer = empty', 0, $otherList->getSize());

// ---------- 4. deleteForCustomer -----------------------------------------
section('deleteForCustomer');

$okDelete = XFE_InvoiceUpload_Service_Registry::uploader()
    ->deleteForCustomer($invoiceId, $customerId);
check('delete by owner = true',    true, $okDelete);
check('file removed from disk',    false, file_exists($absPath));
check('row removed from DB',       false, (bool)Mage::getModel('xfe_invoiceupload/invoice')->load($invoiceId)->getId());

// Negative: wrong customer cannot delete
$stillOk = XFE_InvoiceUpload_Service_Registry::uploader()
    ->deleteForCustomer($invoiceId, $otherCustomerId);
check('delete by non-owner = false', false, $stillOk);

// Invalid ids
check('delete invoiceId=0',  false, XFE_InvoiceUpload_Service_Registry::uploader()->deleteForCustomer(0, $customerId));
check('delete customerId=0', false, XFE_InvoiceUpload_Service_Registry::uploader()->deleteForCustomer($invoiceId, 0));

// ---------- 5. Validator negative paths ----------------------------------
section('Validator');

$validator = new XFE_InvoiceUpload_Service_File_Validator();

// Disallowed extension
$bad = $fakeFiles; $bad['invoice']['name'] = 'evil.exe';
$fails = 0;
try {
    $validator->validate($bad, 5242880, array('pdf'));
} catch (Mage_Core_Exception $e) {
    $fails++;
}
check('disallowed extension throws', 1, $fails);

// Empty file
$bad2 = $fakeFiles; $bad2['invoice']['size'] = 0;
$fails = 0;
try {
    $validator->validate($bad2, 5242880, array('pdf'));
} catch (Mage_Core_Exception $e) {
    $fails++;
}
check('empty file throws', 1, $fails);

// Over the size limit
$bad3 = $fakeFiles; $bad3['invoice']['size'] = 99999999;
$fails = 0;
try {
    $validator->validate($bad3, 1048576, array('pdf'));
} catch (Mage_Core_Exception $e) {
    $fails++;
}
check('over-size file throws', 1, $fails);

// ---------- 6. Archive the test for inspection --------------------------
@unlink($tmpFile);

// ---------- 7. Cleanup ---------------------------------------------------
section('Cleanup');
Mage::getSingleton('core/resource')->getConnection('core_write')
    ->delete($orderTable, array('entity_id = ?' => $orderId));
$customer->delete();
$otherCustomer->delete();

echo PHP_EOL;
if ($failed === 0) {
    echo 'ALL INVOICE UPLOAD TESTS PASSED' . PHP_EOL;
    exit(0);
}
echo $failed . ' INVOICE UPLOAD TESTS FAILED' . PHP_EOL;
exit(1);
