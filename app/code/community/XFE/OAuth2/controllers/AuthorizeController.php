<?php
/**
 * OAuth2 Authorize Controller - handles /oauth2/authorize
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
require_once BP . '/lib/XFE/OAuth2/Autoloader.php';
XFE_OAuth2_Autoloader::register();

class XFE_OAuth2_AuthorizeController extends Mage_Core_Controller_Front_Action
{
    /**
     * GET /oauth2/authorize - show authorization page
     */
    public function indexAction()
    {
        $server = Mage::getModel('xfeoauth2/server')->getServer();

        // Validate the authorize request
        $request = OAuth2\Request::createFromGlobals();
        $response = new OAuth2\Response();

        if (!$server->validateAuthorizeRequest($request, $response)) {
            $this->getResponse()
                ->clearHeaders()
                ->setHeader('Content-Type', 'application/json')
                ->setHttpResponseCode(400)
                ->setBody(json_encode(array(
                    'code' => 400,
                    'error' => $response->getParameter('error'),
                    'message' => $response->getParameter('error_description'),
                )));
            return;
        }

        // Get client details for display
        $clientId = $request->query('client_id');
        $client = Mage::getModel('xfeoauth2/client')->load($clientId);

        // Require customer login
        if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
            Mage::getSingleton('customer/session')->setBeforeAuthUrl(
                Mage::getUrl('oauth2/authorize', array('_current' => true))
            );
            $this->_redirect('customer/account/login');
            return;
        }

        Mage::register('xfeoauth2_authorize_client', $client);
        Mage::register('xfeoauth2_authorize_request', $request);

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * POST /oauth2/authorize - user confirms or denies authorization
     */
    public function confirmAction()
    {
        $server = Mage::getModel('xfeoauth2/server')->getServer();

        $request = OAuth2\Request::createFromGlobals();
        $response = new OAuth2\Response();

        $userId = Mage::getSingleton('customer/session')->getCustomerId();
        $authorized = (bool)$this->getRequest()->getParam('authorized', false);

        $server->handleAuthorizeRequest(
            $request,
            $response,
            $authorized,
            $userId
        );

        if ($authorized) {
            // Redirect to client with authorization code
            $response->send();
            exit;
        }

        // User denied - redirect with error
        $this->getResponse()
            ->clearHeaders()
            ->setHeader('Content-Type', 'application/json')
            ->setHttpResponseCode(403)
            ->setBody(json_encode(array(
                'code' => 403,
                'error' => 'access_denied',
                'message' => 'The user denied access to your application',
            )));
    }
}
