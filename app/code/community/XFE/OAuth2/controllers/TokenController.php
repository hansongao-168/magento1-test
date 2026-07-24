<?php
/**
 * OAuth2 Token Controller - handles /oauth2/token and /oauth2/token/info
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
require_once BP . '/lib/XFE/OAuth2/Autoloader.php';
XFE_OAuth2_Autoloader::register();

class XFE_OAuth2_TokenController extends Mage_Core_Controller_Front_Action
{
    /**
     * POST /oauth2/token - exchange authorization code or client credentials for access token
     */
    public function indexAction()
    {
        $server = Mage::getModel('xfeoauth2/server')->getServer();

        // Handle the token request
        $server->handleTokenRequest(
            OAuth2\Request::createFromGlobals(),
            $response = new OAuth2\Response()
        );

        // Send the response
        $response->send();
        exit;
    }

    /**
     * GET /oauth2/token/info - validate and return token information
     */
    public function infoAction()
    {
        $helper = Mage::helper('xfeoauth2');
        $tokenString = $this->getRequest()->getHeader('Authorization');

        if (!$tokenString) {
            $helper->sendJsonError(401, 'unauthorized', 'Missing Authorization header');
            return;
        }

        // Extract Bearer token
        $tokenString = str_replace('Bearer ', '', $tokenString);
        $tokenString = trim($tokenString);

        $storage = new XFE_OAuth2_Model_Storage_AccessToken();
        $tokenData = $storage->getAccessToken($tokenString);

        if (!$tokenData) {
            $helper->sendJsonError(401, 'unauthorized', 'Invalid or expired access token');
            return;
        }

        $helper->sendJson(200, array(
            'client_id' => $tokenData['client_id'],
            'user_id'   => $tokenData['user_id'],
            'expires'   => $tokenData['expires'],
            'scope'     => $tokenData['scope'],
            'user_type' => $tokenData['user_type'],
        ));
    }
}
