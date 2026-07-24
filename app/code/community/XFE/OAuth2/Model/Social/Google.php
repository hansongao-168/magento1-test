<?php
/**
 * Google Social Login Implementation
 *
 * Uses direct cURL calls to Google OAuth 2.0 endpoints (no league library needed)
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Social_Google extends XFE_OAuth2_Model_Social_Abstract
{
    protected $_provider = 'google';

    /**
     * @return string
     */
    public function getAuthUrl()
    {
        $clientId = $this->_helper->getConfig('google/client_id');
        $redirectUri = $this->_getCallbackUrl();
        $state = $this->_helper->generateToken(16);

        Mage::getSingleton('core/session')->setXfeOauth2State($state);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'
            . http_build_query(array(
                'client_id'     => $clientId,
                'redirect_uri'  => $redirectUri,
                'response_type' => 'code',
                'scope'         => 'openid email profile',
                'state'         => $state,
                'access_type'   => 'online',
            ));
    }

    /**
     * @param string $code
     * @return Mage_Customer_Model_Customer
     * @throws Exception
     */
    public function login($code)
    {
        $clientId = $this->_helper->getConfig('google/client_id');
        $clientSecret = $this->_helper->decryptData(
            Mage::getStoreConfig('xfeoauth2/google/client_secret')
        );
        $redirectUri = $this->_getCallbackUrl();

        // Exchange authorization code for access token
        $tokenResponse = $this->_httpPost(
            'https://oauth2.googleapis.com/token',
            array(
                'code'          => $code,
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri'  => $redirectUri,
                'grant_type'    => 'authorization_code',
            ),
            array('Content-Type: application/x-www-form-urlencoded')
        );

        $tokenData = json_decode($tokenResponse, true);
        if (empty($tokenData['access_token'])) {
            Mage::log('Google login failed: ' . ($tokenResponse ?? 'no response'), null, 'xfeoauth2.log', true);
            throw new Exception('Failed to obtain access token from Google');
        }

        // Fetch user info with access token
        $userResponse = $this->_httpGet(
            'https://www.googleapis.com/oauth2/v2/userinfo',
            array('Authorization: Bearer ' . $tokenData['access_token'])
        );

        $userData = json_decode($userResponse, true);
        if (empty($userData['id'])) {
            throw new Exception('Failed to fetch user info from Google');
        }

        // Create or update social account
        $socialAccount = Mage::getModel('xfeoauth2/social_account');
        $socialAccount->getResource()->loadByProvider(
            $socialAccount,
            $this->_provider,
            (string)$userData['id']
        );

        $customer = $this->_findOrCreateCustomer(
            $userData['email'] ?? null,
            $userData['name'] ?? null
        );

        if (!$socialAccount->getId()) {
            $socialAccount->setCustomerId($customer->getId());
            $socialAccount->setProvider($this->_provider);
            $socialAccount->setProviderUserId((string)$userData['id']);
        }

        $socialAccount->setEmail($userData['email'] ?? '');
        $socialAccount->setName($userData['name'] ?? '');
        $socialAccount->setAvatarUrl($userData['picture'] ?? '');
        $socialAccount->setCreatedAt(now());
        $socialAccount->save();

        $this->_loginCustomer($customer);
        return $customer;
    }
}
