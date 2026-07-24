<?php
/**
 * Social Login Controller - handles /oauth2/login/*
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_LoginController extends Mage_Core_Controller_Front_Action
{
    /**
     * GET /oauth2/login/google - redirect to Google OAuth
     */
    public function googleAction()
    {
        $helper = Mage::helper('xfeoauth2');
        if (!$helper->getConfig('google/enabled')) {
            $this->_forward('noRoute');
            return;
        }

        /** @var XFE_OAuth2_Model_Social_Google $google */
        $google = Mage::getModel('xfeoauth2/social_google');
        $this->_redirectUrl($google->getAuthUrl());
    }

    /**
     * GET /oauth2/login/wechat - redirect to WeChat OAuth
     */
    public function wechatAction()
    {
        $helper = Mage::helper('xfeoauth2');
        if (!$helper->getConfig('wechat/enabled')) {
            $this->_forward('noRoute');
            return;
        }

        /** @var XFE_OAuth2_Model_Social_Wechat $wechat */
        $wechat = Mage::getModel('xfeoauth2/social_wechat');
        $this->_redirectUrl($wechat->getAuthUrl());
    }

    /**
     * GET /oauth2/login/callback?provider=google&code=xxx - unified callback
     */
    public function callbackAction()
    {
        $provider = $this->getRequest()->getParam('provider');
        $code = $this->getRequest()->getParam('code');
        $state = $this->getRequest()->getParam('state');
        $error = $this->getRequest()->getParam('error');

        // Check if user denied authorization
        if ($error) {
            Mage::getSingleton('core/session')->addError(
                $this->__('Login was cancelled or denied.')
            );
            $this->_redirect('customer/account/login');
            return;
        }

        // Validate state parameter (CSRF protection)
        $savedState = Mage::getSingleton('core/session')->getXfeOauth2State();
        if ($state && $savedState && $state !== $savedState) {
            Mage::getSingleton('core/session')->addError(
                $this->__('Invalid state parameter. Please try again.')
            );
            $this->_redirect('customer/account/login');
            return;
        }
        Mage::getSingleton('core/session')->unsXfeOauth2State();

        try {
            // Dynamically instantiate the social provider
            $model = $this->_getSocialModel($provider);
            $customer = $model->login($code);

            Mage::getSingleton('core/session')->addSuccess(
                $this->__('You have successfully logged in with %s.', ucfirst($provider))
            );

            $this->_redirectUrl($model->_getRedirectUrl());

        } catch (Exception $e) {
            Mage::helper('xfeoauth2')->log('Social login error: ' . $e->getMessage());
            Mage::getSingleton('core/session')->addError(
                $this->__('Social login failed: %s', $e->getMessage())
            );
            $this->_redirect('customer/account/login');
        }
    }

    /**
     * GET /oauth2/login/connect?provider=xxx - connect social account to logged-in customer
     */
    public function connectAction()
    {
        if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
            $this->_redirect('customer/account/login');
            return;
        }

        $provider = $this->getRequest()->getParam('provider');
        $code = $this->getRequest()->getParam('code');

        if ($code) {
            // Process callback after authorization
            try {
                $model = $this->_getSocialModel($provider);
                $socialData = $model->getUserInfo($code);

                $socialAccount = Mage::getModel('xfeoauth2/social_account');
                $socialAccount->setCustomerId(Mage::getSingleton('customer/session')->getCustomerId());
                $socialAccount->setProvider($provider);
                $socialAccount->setProviderUserId($socialData['id']);
                $socialAccount->setName($socialData['name'] ?? '');
                $socialAccount->setCreatedAt(now());
                $socialAccount->save();

                Mage::getSingleton('core/session')->addSuccess(
                    $this->__('Your %s account has been connected.', ucfirst($provider))
                );
            } catch (Exception $e) {
                Mage::getSingleton('core/session')->addError($e->getMessage());
            }

            $this->_redirect('oauth2/login/accounts');
        } else {
            // Redirect to provider for authorization
            $model = $this->_getSocialModel($provider);
            $this->_redirectUrl($model->getAuthUrl());
        }
    }

    /**
     * GET /oauth2/login/disconnect?provider=xxx - disconnect social account
     */
    public function disconnectAction()
    {
        if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
            $this->_redirect('customer/account/login');
            return;
        }

        $provider = $this->getRequest()->getParam('provider');
        $customerId = Mage::getSingleton('customer/session')->getCustomerId();

        $socialAccounts = Mage::getModel('xfeoauth2/social_account')->getCollection()
            ->addCustomerFilter($customerId)
            ->addFieldToFilter('provider', $provider);

        foreach ($socialAccounts as $account) {
            $account->delete();
        }

        Mage::getSingleton('core/session')->addSuccess(
            $this->__('Your %s account has been disconnected.', ucfirst($provider))
        );

        $this->_redirect('oauth2/login/accounts');
    }

    /**
     * GET /oauth2/login/accounts - view connected social accounts
     */
    public function accountsAction()
    {
        if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
            $this->_redirect('customer/account/login');
            return;
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Get social model by provider name
     *
     * @param string $provider
     * @return XFE_OAuth2_Model_Social_Abstract
     * @throws Exception
     */
    protected function _getSocialModel($provider)
    {
        $model = Mage::getModel('xfeoauth2/social_' . $provider);
        if (!$model || !$model instanceof XFE_OAuth2_Model_Social_Abstract) {
            throw new Exception('Invalid provider: ' . $provider);
        }
        return $model;
    }
}
