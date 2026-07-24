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
                // Store raw secret for display (one-time)
                Mage::register('xfeoauth2_new_secret', $secret);
            }

            $model->setName($data['name'] ?? '');
            $model->setDescription($data['description'] ?? '');
            $model->setRedirectUri($data['redirect_uri'] ?? '');
            $model->setGrantTypes($data['grant_types'] ?? '');
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
     * ACL check
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('xfe/xfeoauth2_clients');
    }
}
