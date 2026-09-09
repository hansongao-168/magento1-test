<?php
/**
 * OAuth2 Client Frontend Controller
 *
 * Allows a logged-in customer to create, view, and delete their own OAuth2
 * API clients from the storefront account dashboard. The "owner" of the client
 * is stored in the existing `user_id` column (re-purposed to hold either an
 * admin user id or a customer id).
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_ClientController extends Mage_Core_Controller_Front_Action
{
    /**
     * Force login before any action
     */
    public function preDispatch()
    {
        parent::preDispatch();
        if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
            $this->_redirect('customer/account/login');
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            return;
        }
    }

    /**
     * GET /oauth2/client/index - list caller's clients
     */
    public function indexAction()
    {
        $this->_prepareOAuth2Layout('My API Clients', 'list');
        $this->renderLayout();
    }

    /**
     * GET /oauth2/client/new - new client form
     */
    public function newAction()
    {
        $this->_prepareOAuth2Layout('New API Client', 'new');
        $this->renderLayout();
    }

    /**
     * Build the layout for an OAuth2 client page directly in the controller,
     * so we don't depend on the xfeoauth2_customer.xml / oauth.xml layout
     * update files being merged in correctly for this action.
     *
     * @param string $pageTitle
     * @param string $blockName  'list' or 'new'
     */
    protected function _prepareOAuth2Layout($pageTitle, $blockName)
    {
        $this->loadLayout();

        $layout = $this->getLayout();

        // Set the root template to 2columns-left
        $root = $layout->getBlock('root');
        if ($root) {
            $root->setTemplate('page/2columns-left.phtml');
            $root->setHeaderTitle($this->__($pageTitle));
        }

        // Make sure my.account.wrapper exists in the content block.
        // Some skins (exp5) redeclare <customer_account> and strip the wrapper,
        // so we always (re)define it here.
        $wrapper = $layout->getBlock('my.account.wrapper');
        if (!$wrapper) {
            $layout->getBlock('content')->insert(
                $layout->createBlock('page/html_wrapper', 'my.account.wrapper')
                    ->setElementClass('my-account')
            );
        }

        // Replace any existing list/new child of my.account.wrapper with our
        // block so the page renders the OAuth2 form/table.
        $blockAlias = 'xfeoauth2/customer_oauth2Client';
        $template   = $blockName === 'new'
            ? 'xfeoauth2/customer/oauth2client/new.phtml'
            : 'xfeoauth2/customer/oauth2client/list.phtml';
        $newName    = 'customer.oauth2.client.' . $blockName;

        // Remove any stale block with our name (e.g. when re-running after FPC)
        $existing = $layout->getBlock($newName);
        if ($existing) {
            $layout->removeBlock($newName);
        }

        $block = $layout->createBlock($blockAlias, $newName, array('template' => $template));
        $layout->getBlock('my.account.wrapper')->append($block);

        $this->_initLayoutMessages('customer/session');
        $layout->getBlock('head')->setTitle($this->__($pageTitle));
    }

    /**
     * POST /oauth2/client/save - create a new client and show one-time secret
     */
    public function saveAction()
    {
        $helper = Mage::helper('xfeoauth2');
        $session = Mage::getSingleton('customer/session');
        $data = $this->getRequest()->getPost();

        if (!$data) {
            $this->_redirect('*/*/index');
            return;
        }

        // Trim and validate inputs. grant_types is a checkbox group on the
        // form, so it may arrive as either an array or a string. We deliberately
        // do NOT default it server-side here - any prior fallback would let a
        // user submit with no selection and silently get a client created with
        // their choice forced on them. The empty-string check below enforces
        // the same at-least-one rule as the JS validator.
        $name      = trim((string)($data['name'] ?? ''));
        $grantRaw  = $data['grant_types'] ?? '';
        if (is_array($grantRaw)) {
            $grantRaw = implode(',', $grantRaw);
        }
        $grantType = trim((string)$grantRaw);
        $scopes    = trim((string)($data['scopes'] ?? 'basic'));

        if ($name === '') {
            $session->addError($helper->__('Name is required.'));
            $this->_redirect('*/*/new');
            return;
        }

        // Storefront-created clients are limited to grant types that do not
        // need a redirect_uri - authorization_code is the one reserved for
        // third-party apps the customer authorizes via a browser redirect.
        // The form enforces at-least-one client-side; this is the matching
        // server-side guard so we never persist an empty grant_types value.
        // normalizeGrantTypes() also enforces case/whitespace tolerance and
        // silently drops unknown identifiers.
        $grantTypes = $helper->normalizeGrantTypes($grantType);
        if ($grantTypes === '') {
            $session->addError($helper->__('Please select at least one Grant Type for this client.'));
            $this->_redirect('*/*/new');
            return;
        }
        // Strip authorization_code for storefront-created clients - they
        // don't carry a redirect_uri and shouldn't be able to obtain one.
        $grantTypes = implode(',', array_values(array_filter(
            explode(',', $grantTypes),
            function ($g) { return $g !== 'authorization_code'; }
        )));
        if ($grantTypes === '') {
            $grantTypes = 'client_credentials,refresh_token';
        }

        try {
            $model = Mage::getModel('xfeoauth2/client');
            $model->setClientId($helper->generateUuid());

            // One-time secret shown to the customer.
            // client_secret holds the one-way bcrypt hash (used for auth).
            // client_secret_encrypted holds a reversible copy so the owner can
            // view the secret later via the "Show Secret" button.
            $secret = $helper->generateToken(32);
            $model->setClientSecret($helper->hashSecret($secret));
            $model->setClientSecretEncrypted($helper->encryptData($secret));

            $model->setName($name);
            $model->setDescription(trim((string)($data['description'] ?? '')));
            $model->setRedirectUri(''); // not used in customer-facing flow
            $model->setGrantTypes($grantTypes);
            $model->setScopes($scopes);
            $model->setStatus(1);
            // user_id is re-used to store the owning customer id
            $model->setUserId((int)$session->getCustomerId());
            $model->setCreatedAt(now());
            $model->setUpdatedAt(now());
            $model->save();

            // Stash secret for the success page to render once
            Mage::getSingleton('core/session')->setData(
                'xfeoauth2_new_secret_' . $model->getClientId(),
                $secret
            );
            $session->addSuccess($helper->__('The API client has been created.'));

            $this->_redirect('*/*/index', array('show_secret' => $model->getClientId()));
        } catch (Exception $e) {
            $helper->log('Customer save client error: ' . $e->getMessage());
            $session->addError($helper->__('Could not save the API client: %s', $e->getMessage()));
            $this->_redirect('*/*/new');
        }
    }

    /**
     * POST /oauth2/client/delete - delete a client owned by the current customer
     */
    public function deleteAction()
    {
        $helper = Mage::helper('xfeoauth2');
        $session = Mage::getSingleton('customer/session');
        $clientId = $this->getRequest()->getParam('id');

        if (!$clientId) {
            $session->addError($helper->__('Missing client id.'));
            $this->_redirect('*/*/index');
            return;
        }

        try {
            $model = Mage::getModel('xfeoauth2/client')->load($clientId);
            if (!$model->getId()) {
                $session->addError($helper->__('Client not found.'));
            } elseif ((int)$model->getUserId() !== (int)$session->getCustomerId()) {
                $session->addError($helper->__('You are not allowed to delete this client.'));
                $helper->log(
                    'Customer delete attempt for client ' . $clientId
                    . ' by non-owner customer ' . $session->getCustomerId()
                );
            } else {
                $model->delete();
                $session->addSuccess($helper->__('The API client has been deleted.'));
            }
        } catch (Exception $e) {
            $session->addError($e->getMessage());
        }

        $this->_redirect('*/*/index');
    }

    /**
     * GET /oauth2/client/reveal?id=xxx - return the client secret for a client
     * owned by the current customer as JSON.
     *
     * The plaintext secret is recovered from client_secret_encrypted (a
     * reversible AES copy stored via core/encrypt). Clients created before
     * version 1.0.2 do not have this column filled, in which case the secret
     * cannot be recovered (bcrypt hash is one-way) and a 409 is returned.
     */
    public function revealAction()
    {
        $helper  = Mage::helper('xfeoauth2');
        $session = Mage::getSingleton('customer/session');
        $clientId = $this->getRequest()->getParam('id');

        if (!$clientId) {
            $helper->sendJsonError(400, 'bad_request', $helper->__('Missing client id.'));
            return;
        }

        $model = Mage::getModel('xfeoauth2/client')->load($clientId);
        if (!$model->getId()) {
            $helper->sendJsonError(404, 'not_found', $helper->__('Client not found.'));
            return;
        }

        if ((int)$model->getUserId() !== (int)$session->getCustomerId()) {
            $helper->log('Customer reveal attempt for client ' . $clientId
                . ' by non-owner customer ' . $session->getCustomerId());
            $helper->sendJsonError(403, 'forbidden', $helper->__('You are not allowed to view this client.'));
            return;
        }

        $encrypted = (string)$model->getClientSecretEncrypted();
        if ($encrypted === '') {
            $helper->sendJsonError(409, 'not_recoverable', $helper->__(
                'This client was created before secret recovery was available. '
                . 'The secret cannot be recovered. Please create a new client.'
            ));
            return;
        }

        try {
            $secret = $helper->decryptData($encrypted);
        } catch (Exception $e) {
            $helper->log('Decrypt client secret error for ' . $clientId . ': ' . $e->getMessage());
            $helper->sendJsonError(500, 'decrypt_failed', $helper->__('Could not decrypt the client secret.'));
            return;
        }

        $helper->sendJson(200, array(
            'client_id'     => $model->getClientId(),
            'client_secret' => $secret,
        ));
    }
}