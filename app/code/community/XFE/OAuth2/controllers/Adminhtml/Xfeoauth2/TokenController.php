<?php
/**
 * Admin Token Controller - view and revoke access tokens
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Adminhtml_Xfeoauth2_TokenController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Token list
     */
    public function indexAction()
    {
        $this->_title(Mage::helper('xfeoauth2')->__('Access Tokens'));

        $this->loadLayout()
            ->_setActiveMenu('xfe/xfeoauth2_tokens')
            ->_addBreadcrumb(
                Mage::helper('xfeoauth2')->__('XFE'),
                Mage::helper('xfeoauth2')->__('XFE')
            )
            ->_addBreadcrumb(
                Mage::helper('xfeoauth2')->__('Access Tokens'),
                Mage::helper('xfeoauth2')->__('Access Tokens')
            );

        $this->renderLayout();
    }

    /**
     * Revoke token
     */
    public function revokeAction()
    {
        $token = $this->getRequest()->getParam('token');
        $helper = Mage::helper('xfeoauth2');
        $session = Mage::getSingleton('adminhtml/session');

        if ($token) {
            try {
                $model = Mage::getModel('xfeoauth2/access_token')->load($token);
                if ($model->getId()) {
                    $model->delete();
                    $session->addSuccess($helper->__('The access token has been revoked.'));
                } else {
                    $session->addError($helper->__('Token not found.'));
                }
            } catch (Exception $e) {
                $session->addError($e->getMessage());
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
        return Mage::getSingleton('admin/session')->isAllowed('xfe/xfeoauth2_tokens');
    }
}
