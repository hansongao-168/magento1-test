<?php
/**
 * Admin Client Controller - CRUD for OAuth 2.0 Clients
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Adminhtml_Xfeoauth2_ClientController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Init layout, menus, breadcrumbs
     *
     * @return $this
     */
    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('xfe/xfeoauth2_clients')
            ->_addBreadcrumb(
                Mage::helper('xfeoauth2')->__('XFE'),
                Mage::helper('xfeoauth2')->__('XFE')
            )
            ->_addBreadcrumb(
                Mage::helper('xfeoauth2')->__('OAuth 2.0 Clients'),
                Mage::helper('xfeoauth2')->__('OAuth 2.0 Clients')
            );
        return $this;
    }

    /**
     * Client list
     */
    public function indexAction()
    {
        $this->_title($this->__('OAuth 2.0 Clients'));
        $this->_initAction();
        $this->renderLayout();
    }

    /**
     * New client
     */
    public function newAction()
    {
        $this->_forward('edit');
    }

    /**
     * Edit client
     */
    public function editAction()
    {
        $id = $this->getRequest()->getParam('id');
        $helper = Mage::helper('xfeoauth2');
        $model = Mage::getModel('xfeoauth2/client');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    $helper->__('This client no longer exists.')
                );
                $this->_redirect('*/*/');
                return;
            }
        }

        Mage::register('xfeoauth2_client', $model);

        $this->_title($helper->__('OAuth 2.0 Client'))
            ->_title($helper->__($model->getId() ? $model->getName() : 'New Client'));

        $this->_initAction();
        $this->renderLayout();
    }

    /**
     * Save client
     */
    public function saveAction()
    {
        $helper = Mage::helper('xfeoauth2');
        $session = Mage::getSingleton('adminhtml/session');
        $data = $this->getRequest()->getPost();

        if (!$data) {
            $this->_redirect('*/*/');
            return;
        }

        try {
            $model = Mage::getModel('xfeoauth2/client');

            if (!empty($data['client_id'])) {
                $model->load($data['client_id']);
            }

            $isNew = !$model->getId();

            if ($isNew) {
                $model->setClientId($helper->generateUuid());
                $secret = $helper->generateToken(32);
                $model->setClientSecret($helper->hashSecret($secret));
                // Reversible copy so the admin can view the secret later via
                // the "Show Secret" button.
                $model->setClientSecretEncrypted($helper->encryptData($secret));
                // Store raw secret for display (one-time)
                Mage::register('xfeoauth2_new_secret', $secret);
            }

            $model->setName($data['name'] ?? '');
            $model->setDescription($data['description'] ?? '');
            $model->setRedirectUri($data['redirect_uri'] ?? '');
            // Normalize grant_types via the shared helper so the stored value
            // is always a clean, lowercase, comma-separated list of known
            // grant type identifiers. Unknown values are dropped silently,
            // case/whitespace/newlines are tolerated on input. The form
            // requires at least one selection (multiselect validate-select),
            // and we mirror that server-side so a JS-disabled submit cannot
            // persist an empty grant_types value.
            $grantTypesNormalized = $helper->normalizeGrantTypes($data['grant_types'] ?? '');
            if ($grantTypesNormalized === '') {
                $session->addError($helper->__('Please select at least one Grant Type for this client.'));
                $this->_redirect('*/*/edit', array('id' => $data['client_id'] ?? null));
                return;
            }
            $model->setGrantTypes($grantTypesNormalized);
            $model->setScopes($data['scopes'] ?? 'basic');
            $model->setStatus((int)($data['status'] ?? 1));
            $model->setUserId(Mage::getSingleton('admin/session')->getUser()->getId());
            $model->setUpdatedAt(now());

            if ($isNew) {
                $model->setCreatedAt(now());
            }

            $model->save();

            $session->addSuccess($helper->__('The client has been saved.'));

            if ($isNew && Mage::registry('xfeoauth2_new_secret')) {
                $session->addNotice(
                    $helper->__('Client Secret (shown once): %s', Mage::registry('xfeoauth2_new_secret'))
                );
            }

            $this->_redirect('*/*/');

        } catch (Exception $e) {
            $helper->log('Save client error: ' . $e->getMessage());
            $session->addError($e->getMessage());
            $this->_redirect('*/*/edit', array('id' => $data['client_id'] ?? null));
        }
    }

    /**
     * Delete client
     */
    public function deleteAction()
    {
        $id = $this->getRequest()->getParam('id');
        $helper = Mage::helper('xfeoauth2');

        if ($id) {
            try {
                $model = Mage::getModel('xfeoauth2/client')->load($id);
                if ($model->getId()) {
                    $model->delete();
                    Mage::getSingleton('adminhtml/session')->addSuccess(
                        $helper->__('The client has been deleted.')
                    );
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/');
    }

    /**
     * Grid AJAX action
     */
    public function gridAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * GET admin/xfeoauth2_client/reveal?id=xxx - return the client secret as
     * JSON for the "Show Secret" button on the grid.
     *
     * The plaintext secret is recovered from client_secret_encrypted (a
     * reversible AES copy stored via core/encrypt). Clients created before
     * version 1.0.2 do not have this column filled, in which case the secret
     * cannot be recovered (bcrypt hash is one-way) and a 409 is returned.
     */
    public function revealAction()
    {
        $helper   = Mage::helper('xfeoauth2');
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
            $helper->log('Admin decrypt client secret error for ' . $clientId . ': ' . $e->getMessage());
            $helper->sendJsonError(500, 'decrypt_failed', $helper->__('Could not decrypt the client secret.'));
            return;
        }

        $helper->sendJson(200, array(
            'client_id'     => $model->getClientId(),
            'client_secret' => $secret,
        ));
    }

    /**
     * ACL check
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('xfe/xfeoauth2_clients');
    }
}
