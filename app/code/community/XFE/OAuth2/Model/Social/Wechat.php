<?php
/**
 * WeChat Social Login Implementation
 *
 * Uses WeChat Open Platform OAuth API via cURL
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Social_Wechat extends XFE_OAuth2_Model_Social_Abstract
{
    protected $_provider = 'wechat';

    /**
     * @return string
     */
    public function getAuthUrl()
    {
        $appId = $this->_helper->getConfig('wechat/app_id');
        $redirectUri = $this->_getCallbackUrl();
        $state = $this->_helper->generateToken(16);

        Mage::getSingleton('core/session')->setXfeOauth2State($state);

        return 'https://open.weixin.qq.com/connect/qrconnect?'
            . http_build_query(array(
                'appid'         => $appId,
                'redirect_uri'  => $redirectUri,
                'response_type' => 'code',
                'scope'         => 'snsapi_login',
                'state'         => $state,
            ))
            . '#wechat_redirect';
    }

    /**
     * @param string $code
     * @return Mage_Customer_Model_Customer
     * @throws Exception
     */
    public function login($code)
    {
        $appId = $this->_helper->getConfig('wechat/app_id');
        $appSecret = $this->_helper->decryptData(
            Mage::getStoreConfig('xfeoauth2/wechat/app_secret')
        );

        // Exchange code for access token
        $tokenUrl = 'https://api.weixin.qq.com/sns/oauth2/access_token?'
            . http_build_query(array(
                'appid'      => $appId,
                'secret'     => $appSecret,
                'code'       => $code,
                'grant_type' => 'authorization_code',
            ));

        $tokenResponse = $this->_httpGet($tokenUrl);
        $tokenData = json_decode($tokenResponse, true);

        if (empty($tokenData['access_token']) || empty($tokenData['openid'])) {
            Mage::log('WeChat login failed: ' . ($tokenResponse ?? 'no response'), null, 'xfeoauth2.log', true);
            throw new Exception('Failed to obtain access token from WeChat');
        }

        // Fetch user info
        $userUrl = 'https://api.weixin.qq.com/sns/userinfo?'
            . http_build_query(array(
                'access_token' => $tokenData['access_token'],
                'openid'       => $tokenData['openid'],
                'lang'         => 'zh_CN',
            ));

        $userResponse = $this->_httpGet($userUrl);
        $userData = json_decode($userResponse, true);

        if (empty($userData['openid'])) {
            throw new Exception('Failed to fetch user info from WeChat');
        }

        $this->_helper->log('WeChat user data: ' . json_encode($userData));

        // Create or update social account
        $socialAccount = Mage::getModel('xfeoauth2/social_account');
        $socialAccount->getResource()->loadByProvider(
            $socialAccount,
            $this->_provider,
            (string)$userData['openid']
        );

        $customer = $this->_findOrCreateCustomer(
            null, // WeChat doesn't always provide email
            $userData['nickname'] ?? null
        );

        if (!$socialAccount->getId()) {
            $socialAccount->setCustomerId($customer->getId());
            $socialAccount->setProvider($this->_provider);
            $socialAccount->setProviderUserId((string)$userData['openid']);
        }

        $socialAccount->setName($userData['nickname'] ?? '');
        $socialAccount->setAvatarUrl($userData['headimgurl'] ?? '');
        $socialAccount->setCreatedAt(now());
        $socialAccount->save();

        $this->_loginCustomer($customer);
        return $customer;
    }
}
