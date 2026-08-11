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

        // Trim and validate inputs
        $name      = trim((string)($data['name'] ?? ''));
        $grantType = trim((string)($data['grant_types'] ?? 'client_credentials'));
        $scopes    = trim((string)($data['scopes'] ?? 'basic'));

        if ($name === '') {
            $session->addError($helper->__('Name is required.'));
            $this->_redirect('*/*/new');
            return;
        }

        // Frontend callers should not be allowed to use authorization_code
        // (which requires a redirect_uri and is meant for third-party apps).
        $allowed = array('client_credentials', 'refresh_token');
        $parts = array_filter(array_map('trim', explode(',', $grantType)));
        $parts = array_values(array_intersect($parts, $allowed));
        if (empty($parts)) {
            $parts = array('client_credentials');
        }
        $grantTypes = implode(',', $parts);

        try {
            $model = Mage::getModel('xfeoauth2/client');
            $model->setClientId($helper->generateUuid());

            // One-time secret shown to the customer
            $secret = $helper->generateToken(32);
            $model->setClientSecret($helper->hashSecret($secret));

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
}