<?php
/**
 * probe_oauth2_refresh.php
 *
 * Smoke-test the refresh_token grant path end-to-end, to verify that
 * Storage/RefreshToken (which was affected by the same class-name typo)
 * works correctly.
 */
umask(0);
require __DIR__ . '/app/Mage.php';
Mage::app();

require_once BP . '/lib/XFE/OAuth2/Autoloader.php';
XFE_OAuth2_Autoloader::register();

$server = Mage::getModel('xfeoauth2/server')->getServer();
$resource = Mage::getSingleton('core/resource');
$read  = $resource->getConnection('core_read');
$write = $resource->getConnection('core_write');

// Pick the most recently issued refresh_token for our test client
$clientId = $read->fetchOne("SELECT client_id FROM xfe_oauth2_client LIMIT 1");
$refresh  = $read->fetchOne(
    "SELECT refresh_token FROM xfe_oauth2_refresh_token "
    . "WHERE client_id = ? ORDER BY expires DESC LIMIT 1",
    array($clientId)
);

// Re-seed the known secret again (probe_oauth2_grant.php may have been run,
// but we want this probe to be self-contained).
$knownSecret = 'probe-secret-refresh';
$write->update(
    'xfe_oauth2_client',
    array(
        'client_secret' => password_hash($knownSecret, PASSWORD_BCRYPT),
        // This client was created with grant_types=client_credentials only.
        // Temporarily add refresh_token so we can exercise that grant path.
        'grant_types'   => 'client_credentials,refresh_token',
    ),
    array('client_id = ?' => $clientId)
);

echo "client_id:   $clientId\n";
echo "refresh_tok: " . ($refresh ?: '(none)') . "\n";

if (!$refresh) {
    echo "\nNo refresh_token to test with. Run probe_oauth2_grant.php first.\n";
    exit(1);
}

$request = new OAuth2\Request(array(), array(
    'grant_type'    => 'refresh_token',
    'refresh_token' => $refresh,
    'client_id'     => $clientId,
    'client_secret' => $knownSecret,
    'scope'         => 'basic',
));
$request->server['REQUEST_METHOD'] = 'POST';
$request->headers['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

$response = $server->handleTokenRequest($request, new OAuth2\Response());
echo "\nrefresh_token grant:\n";
echo "  status: " . $response->getStatusCode() . "\n";
echo "  body  : " . $response->getResponseBody() . "\n";

echo "\nDONE\n";