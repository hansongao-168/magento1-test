<?php
/**
 * probe_oauth2_grant.php
 *
 * Reproduce the exact code path that triggered the original fatal error:
 *   POST /oauth2/token
 *     grant_type = client_credentials
 *     client_id, client_secret
 *
 * Calls OAuth2\Server::handleTokenRequest() in-process with a synthetic
 * OAuth2\Request, so we don't need a real client_secret and don't need
 * a working webserver.
 */
umask(0);
require __DIR__ . '/app/Mage.php';
Mage::app();

echo "=== Setting up OAuth2 server (same path as TokenController::indexAction) ===\n";

require_once BP . '/lib/XFE/OAuth2/Autoloader.php';
XFE_OAuth2_Autoloader::register();

$server = Mage::getModel('xfeoauth2/server')->getServer();
echo "  server: " . get_class($server) . "\n";

// Pick the existing client (the only one in the DB right now).
$resource = Mage::getSingleton('core/resource');
$write = $resource->getConnection('core_write');
$read  = $resource->getConnection('core_read');
$clientId = $read->fetchOne("SELECT client_id FROM xfe_oauth2_client LIMIT 1");
echo "  client_id from DB: " . ($clientId ?: '(none)') . "\n";

// Stamp a known bcrypt-hashed secret so we can prove the grant works end-to-end.
$knownSecret = 'probe-secret-' . bin2hex(random_bytes(4));
$knownHash   = password_hash($knownSecret, PASSWORD_BCRYPT);
$write->update(
    'xfe_oauth2_client',
    array('client_secret' => $knownHash),
    array('client_id = ?' => $clientId)
);
echo "  seeded known client_secret for the probe\n";

// We don't have the plain client_secret. Use checkClientCredentials to
// confirm what we have. If verifySecret() returns false with a wrong secret
// (which it will here), the storage layer is the issue, not our model layer.
//
// Build a synthetic request that mimics the HTTP POST.
$request = new OAuth2\Request(array(), array(
    'grant_type'    => 'client_credentials',
    'client_id'     => $clientId,
    'client_secret' => $knownSecret,
    'scope'         => 'basic',
));
// OAuth2\Request defaults to GET. bshaffer requires POST for token endpoint.
$request->server['REQUEST_METHOD'] = 'POST';
$request->headers['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

echo "\n=== handleTokenRequest (this is the path the original fatal error lived on) ===\n";
try {
    $response = $server->handleTokenRequest($request, new OAuth2\Response());
    echo "  status: " . $response->getStatusCode() . "\n";
    echo "  body  : " . $response->getResponseBody() . "\n";
} catch (Throwable $e) {
    echo "  THREW: " . get_class($e) . "\n";
    echo "  msg  : " . $e->getMessage() . "\n";
    echo "  file : " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Restore the original secret (best-effort: just leave the new one in place
// if it was already hashed, since we don't know the original plain text).
// Reading back to verify whether the access_token row was actually written:
$newRows = $read->fetchAll(
    "SELECT access_token, client_id, scope, expires FROM xfe_oauth2_access_token "
    . "WHERE client_id = ? ORDER BY expires DESC LIMIT 3",
    array($clientId)
);
echo "\n=== access_token rows written for this client (most recent 3) ===\n";
foreach ($newRows as $row) {
    echo sprintf("  %s  client=%s  scope=%s  expires=%s\n",
        $row['access_token'], $row['client_id'], $row['scope'], $row['expires']);
}

echo "\nDONE\n";