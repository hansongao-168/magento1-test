<?php
/**
 * probe_oauth2_token.php
 *
 * Standalone diagnostic + round-trip test for the OAuth2 token storage.
 *
 * Verifies that:
 *   1. Mage::getModel('xfeoauth2/access_token') returns a real model.
 *   2. The Storage/AccessToken::setAccessToken() path actually persists a row.
 *   3. Storage/AccessToken::getAccessToken() reads it back.
 *   4. Round-tripped data matches what was written.
 *
 * Reproduces the original fatal error
 *   "Call to a member function setAccessToken() on bool"
 * that triggered this whole investigation.
 */
umask(0);

require __DIR__ . '/app/Mage.php';
Mage::app();

echo "=== PHP / Magento sanity ===\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "Mage::getVersion(): " . Mage::getVersion() . "\n";

echo "\n=== Class-existence checks ===\n";
$classes = array(
    'XFE_OAuth2_Model_Access_Token',
    'XFE_OAuth2_Model_Refresh_Token',
    'XFE_OAuth2_Model_Authorization_Code',
    'XFE_OAuth2_Model_Social_Account',
    'XFE_OAuth2_Model_Order_Channel',
    'XFE_OAuth2_Model_Storage_AccessToken',
);
foreach ($classes as $c) {
    printf("  %-50s exists=%s\n", $c, class_exists($c) ? 'YES' : 'NO');
}

echo "\n=== Mage::getModel('xfeoauth2/access_token') ===\n";
$model = Mage::getModel('xfeoauth2/access_token');
echo "  type: " . (is_object($model) ? get_class($model) : gettype($model)) . "\n";
if (is_object($model)) {
    echo "  resourceName: " . var_export($model->getResourceName(), true) . "\n";
    echo "  idFieldName:  " . var_export($model->getIdFieldName(), true) . "\n";
}

echo "\n=== xfe_oauth2_access_token table presence ===\n";
$resource = Mage::getSingleton('core/resource');
$table = $resource->getTableName('xfeoauth2/access_token');
echo "  table: $table\n";
$read = $resource->getConnection('core_read');
foreach ($read->fetchAll("SHOW COLUMNS FROM `$table`") as $row) {
    echo sprintf("    %-30s %s\n", $row['Field'], $row['Type']);
}

echo "\n=== core_resource for xfeoauth2_setup ===\n";
foreach ($read->fetchAll("SELECT code, version FROM core_resource WHERE code='xfeoauth2_setup'") as $row) {
    echo sprintf("  %s -> v%s\n", $row['code'], $row['version']);
}

echo "\n=== Storage round trip (this is the original failure path) ===\n";
$token = bin2hex(random_bytes(8));
// FK requires a real xfe_oauth2_client.client_id. Pull the first one we find.
$realClient = $read->fetchOne("SELECT client_id FROM xfe_oauth2_client ORDER BY client_id LIMIT 1");
$clientId = $realClient ?: 'probe-no-client';
$expires = time() + 3600;
$scope   = 'probe';

$storage = new XFE_OAuth2_Model_Storage_AccessToken();

// SET — used to blow up with "Call to setAccessToken() on bool"
$storage->setAccessToken($token, $clientId, '0', $expires, $scope);
echo "  setAccessToken wrote token=$token client=$clientId scope=$scope\n";

// GET — read it back
$got = $storage->getAccessToken($token);
echo "  getAccessToken returned: " . print_r($got, true);
$ok = is_array($got)
    && $got['client_id'] === $clientId
    && $got['scope']     === $scope
    && (int)$got['expires'] === $expires;
echo "  round-trip OK: " . ($ok ? 'YES' : 'NO') . "\n";

// CLEAN UP — leave the DB tidy
$del = Mage::getModel('xfeoauth2/access_token');
$del->setAccessToken($token)->delete();
echo "  cleanup done\n";

echo "\nDONE\n";