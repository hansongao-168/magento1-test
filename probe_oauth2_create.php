<?php
/**
 * probe_oauth2_create.php
 *
 * End-to-end create flow: simulate what the storefront /oauth2/client/save
 * controller does, then immediately fetch a token with the new
 * client_id + the one-time secret. This catches:
 *   - model alias miss
 *   - hashing not happening
 *   - DB constraint surprise
 *   - immediate token grant regression
 */
umask(0);
require __DIR__ . '/app/Mage.php';
Mage::app();

require_once BP . '/lib/XFE/OAuth2/Autoloader.php';
XFE_OAuth2_Autoloader::register();

$helper = Mage::helper('xfeoauth2');

echo "=== Step 1: create client via the same flow as the controller ===\n";
$model = Mage::getModel('xfeoauth2/client');
$cid   = $helper->generateUuid();
$secret = $helper->generateToken(32);
echo "  generated client_id     : $cid\n";
echo "  generated client_secret : $secret\n";

$model->setClientId($cid)
    ->setClientSecret($helper->hashSecret($secret))
    ->setName('probe client')
    ->setDescription('autotest')
    ->setRedirectUri('')
    ->setGrantTypes('client_credentials')
    ->setScopes('basic')
    ->setStatus(1)
    ->setUserId(0)
    ->setCreatedAt(now())
    ->setUpdatedAt(now())
    ->save();
echo "  saved (id hash matches) : " . substr($model->getClientSecret(), 0, 7) . "...\n";

echo "\n=== Step 2: reload and verify secret verifies ===\n";
$reloaded = Mage::getModel('xfeoauth2/client')->load($cid);
echo "  reloaded id        : " . $reloaded->getClientId() . "\n";
echo "  reloaded status    : " . $reloaded->getStatus() . "\n";
echo "  secret verify(correct): " . var_export($helper->verifySecret($secret, $reloaded->getClientSecret()), true) . "\n";
echo "  secret verify(wrong)  : " . var_export($helper->verifySecret('WRONG', $reloaded->getClientSecret()), true) . "\n";

echo "\n=== Step 3: full OAuth2 grant using the new client ===\n";
$server = Mage::getModel('xfeoauth2/server')->getServer();
$request = new OAuth2\Request(array(), array(
    'grant_type'    => 'client_credentials',
    'client_id'     => $cid,
    'client_secret' => $secret,
    'scope'         => 'basic',
));
$request->server['REQUEST_METHOD'] = 'POST';
$request->headers['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

$response = $server->handleTokenRequest($request, new OAuth2\Response());
echo "  status: " . $response->getStatusCode() . "\n";
echo "  body  : " . $response->getResponseBody() . "\n";

echo "\n=== Cleanup ===\n";
$reloaded->delete();
echo "  deleted probe client $cid\n";

echo "\nDONE\n";