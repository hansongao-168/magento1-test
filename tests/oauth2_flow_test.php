<?php
/**
 * OAuth2 token flow verification script.
 *
 * Standalone (no Magento bootstrap): uses the bshaffer library's built-in
 * Memory storage to simulate the exact server configuration from
 * XFE_OAuth2_Model_Server::getServer(), then verifies:
 *
 *   1. client_credentials grant returns BOTH access_token and refresh_token
 *      (the custom XFE_OAuth2_Model_Grant_ClientCredentials behavior)
 *   2. refresh_token grant rotates the refresh token (always_issue_new_refresh_token)
 *   3. access_lifetime / refresh_token_lifetime config is honored
 *   4. error paths: bad client secret, invalid scope, expired refresh token
 *   5. carriers scope is registered in the real XFE storage class
 *
 * Usage: php tests/oauth2_flow_test.php
 *
 * @category   Community
 * @package    XFE_OAuth2
 */

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');

$root = dirname(__DIR__);

require_once $root . '/lib/XFE/OAuth2/Autoloader.php';
XFE_OAuth2_Autoloader::register();

// --- Minimal Mage stub so the real XFE storage class can be loaded ---
if (!class_exists('Mage', false)) {
    class Mage
    {
        public static function getResourceModel($model) { return new stdClass(); }
        public static function helper($name)           { return new stdClass(); }
    }
}

require_once $root . '/app/code/community/XFE/OAuth2/Model/Grant/ClientCredentials.php';
require_once $root . '/app/code/community/XFE/OAuth2/Model/Storage/ClientCredentials.php';

// --- Tiny test harness ---
$GLOBALS['passed'] = 0;
$GLOBALS['failed'] = 0;

function check($label, $cond, $detail = '')
{
    if ($cond) {
        $GLOBALS['passed']++;
        echo "  [PASS] {$label}\n";
    } else {
        $GLOBALS['failed']++;
        echo "  [FAIL] {$label}" . ($detail !== '' ? " => {$detail}" : '') . "\n";
    }
}

function tokenRequest(OAuth2\Server $server, array $params)
{
    $request = new OAuth2\Request(
        array(),
        $params,
        array(),
        array(),
        array(),
        array('REQUEST_METHOD' => 'POST')
    );
    $response = new OAuth2\Response();
    $server->handleTokenRequest($request, $response);
    return $response;
}

// =====================================================================
// Build the server exactly like XFE_OAuth2_Model_Server::getServer()
// =====================================================================
$storages = new OAuth2\Storage\Memory(array(
    'client_credentials' => array(
        'test_client' => array(
            'client_secret' => 'test_secret',
            'grant_types'   => 'client_credentials refresh_token',
            'scope'         => 'basic carriers',
        ),
    ),
    'supported_scopes' => array('basic', 'orders', 'customers', 'carriers', 'admin'),
));

$server = new OAuth2\Server(
    $storages,
    array(
        'access_lifetime'        => 3600,
        'refresh_token_lifetime' => 2592000,
        'always_issue_new_refresh_token' => true,
    )
);

$server->addGrantType(new XFE_OAuth2_Model_Grant_ClientCredentials($storages));
$server->addGrantType(new OAuth2\GrantType\RefreshToken(
    $storages,
    array('always_issue_new_refresh_token' => true)
));

// =====================================================================
// 1. client_credentials grant
// =====================================================================
echo "\n== 1. client_credentials grant ==\n";
$res = tokenRequest($server, array(
    'grant_type'    => 'client_credentials',
    'client_id'     => 'test_client',
    'client_secret' => 'test_secret',
    'scope'         => 'carriers',
));
$params = $res->getParameters();

check('HTTP 200', $res->getStatusCode() === 200, 'status=' . $res->getStatusCode());
check('access_token issued', !empty($params['access_token']));
check('refresh_token issued (custom grant fix)', !empty($params['refresh_token']));
check('expires_in = 3600 (1 hour)', isset($params['expires_in']) && $params['expires_in'] === 3600, 'expires_in=' . (isset($params['expires_in']) ? $params['expires_in'] : 'n/a'));
check('token_type = Bearer', isset($params['token_type']) && $params['token_type'] === 'Bearer');
check('scope = carriers', isset($params['scope']) && $params['scope'] === 'carriers', isset($params['scope']) ? $params['scope'] : 'n/a');

$firstAccess  = isset($params['access_token'])  ? $params['access_token']  : '';
$firstRefresh = isset($params['refresh_token']) ? $params['refresh_token'] : '';

// =====================================================================
// 2. refresh_token grant
// =====================================================================
echo "\n== 2. refresh_token grant ==\n";
$res = tokenRequest($server, array(
    'grant_type'    => 'refresh_token',
    'refresh_token' => $firstRefresh,
    'client_id'     => 'test_client',
    'client_secret' => 'test_secret',
));
$params2 = $res->getParameters();

check('HTTP 200', $res->getStatusCode() === 200, 'status=' . $res->getStatusCode());
check('new access_token issued', !empty($params2['access_token']) && $params2['access_token'] !== $firstAccess);
check('new refresh_token rotated (always_issue_new_refresh_token)', !empty($params2['refresh_token']) && $params2['refresh_token'] !== $firstRefresh);
check('expires_in = 3600', isset($params2['expires_in']) && $params2['expires_in'] === 3600);

// =====================================================================
// 3. Error paths
// =====================================================================
echo "\n== 3. error paths ==\n";

// 3a. bad client secret (library returns 400 invalid_client by design)
$res = tokenRequest($server, array(
    'grant_type'    => 'client_credentials',
    'client_id'     => 'test_client',
    'client_secret' => 'wrong_secret',
    'scope'         => 'carriers',
));
check('bad secret -> 400 invalid_client', $res->getStatusCode() === 400 && $res->getParameter('error') === 'invalid_client',
    'status=' . $res->getStatusCode() . ' error=' . $res->getParameter('error'));

// 3b. unknown client
$res = tokenRequest($server, array(
    'grant_type'    => 'client_credentials',
    'client_id'     => 'ghost_client',
    'client_secret' => 'x',
    'scope'         => 'carriers',
));
check('unknown client -> 400 invalid_client', $res->getStatusCode() === 400, 'status=' . $res->getStatusCode());

// 3c. invalid scope
$res = tokenRequest($server, array(
    'grant_type'    => 'client_credentials',
    'client_id'     => 'test_client',
    'client_secret' => 'test_secret',
    'scope'         => 'carriers evil_scope',
));
check('invalid scope -> 400 invalid_scope', $res->getStatusCode() === 400 && $res->getParameter('error') === 'invalid_scope',
    'status=' . $res->getStatusCode() . ' error=' . $res->getParameter('error'));

// 3d. expired refresh token
$storages->refreshTokens['expired_rt'] = array(
    'refresh_token' => 'expired_rt',
    'client_id'     => 'test_client',
    'user_id'       => null,
    'expires'       => time() - 100,
    'scope'         => 'carriers',
);
$res = tokenRequest($server, array(
    'grant_type'    => 'refresh_token',
    'refresh_token' => 'expired_rt',
    'client_id'     => 'test_client',
    'client_secret' => 'test_secret',
));
check('expired refresh token -> 400 invalid_grant', $res->getStatusCode() === 400 && $res->getParameter('error') === 'invalid_grant',
    'status=' . $res->getStatusCode() . ' error=' . $res->getParameter('error'));

// 3e. missing refresh_token parameter
$res = tokenRequest($server, array(
    'grant_type'    => 'refresh_token',
    'client_id'     => 'test_client',
    'client_secret' => 'test_secret',
));
check('missing refresh_token -> 400 invalid_request', $res->getStatusCode() === 400 && $res->getParameter('error') === 'invalid_request',
    'status=' . $res->getStatusCode() . ' error=' . $res->getParameter('error'));

// =====================================================================
// 4. carriers scope registration (real XFE storage class)
// =====================================================================
echo "\n== 4. carriers scope registration ==\n";
$xfeStorage = new XFE_OAuth2_Model_Storage_ClientCredentials();
check('scopeExists("carriers")', $xfeStorage->scopeExists('carriers') === true);
check('scopeExists("basic carriers")', $xfeStorage->scopeExists('basic carriers') === true);
check('scopeExists("evil")', $xfeStorage->scopeExists('evil') === false);
check('getDefaultScope() = basic', $xfeStorage->getDefaultScope() === 'basic');

// =====================================================================
// Summary
// =====================================================================
echo "\n========================================\n";
echo "Result: {$GLOBALS['passed']} passed, {$GLOBALS['failed']} failed\n";
exit($GLOBALS['failed'] > 0 ? 1 : 0);
