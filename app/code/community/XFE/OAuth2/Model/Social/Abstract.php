<?php
/**
 * Social Login Abstract Base Class
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
abstract class XFE_OAuth2_Model_Social_Abstract
{
    /**
     * @var XFE_OAuth2_Helper_Data
     */
    protected $_helper;

    /**
     * @var string
     */
    protected $_provider;

    public function __construct()
    {
        $this->_helper = Mage::helper('xfeoauth2');
    }

    /**
     * Get authorization URL to redirect user to the provider
     *
     * @return string
     */
    abstract public function getAuthUrl();

    /**
     * Process callback code and login/create customer
     *
     * @param string $code
     * @return Mage_Customer_Model_Customer
     * @throws Exception
     */
    abstract public function login($code);

    /**
     * Get provider name
     *
     * @return string
     */
    public function getProvider()
    {
        return $this->_provider;
    }

    /**
     * Find or create customer by email
     *
     * @param string $email
     * @param string $name
     * @return Mage_Customer_Model_Customer
     */
    protected function _findOrCreateCustomer($email, $name)
    {
        $customer = Mage::getModel('customer/customer');

        // Try to load by email
        if ($email) {
            $customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
            $customer->loadByEmail($email);
        }

        if ($customer->getId()) {
            return $customer;
        }

        // Create new customer
        $customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
        $customer->setStoreId(Mage::app()->getStore()->getId());

        if ($email) {
            $customer->setEmail($email);
        } else {
            // Generate placeholder email if none provided
            $customer->setEmail($this->_provider . '_' . uniqid() . '@example.com');
        }

        $customer->setFirstname($name ?: $this->_provider . '_user');
        $customer->setLastname($name ?: 'User');
        $customer->setPassword($this->_helper->generateToken(8));

        $customer->save();

        return $customer;
    }

    /**
     * Login customer and redirect
     *
     * @param Mage_Customer_Model_Customer $customer
     * @return void
     */
    protected function _loginCustomer(Mage_Customer_Model_Customer $customer)
    {
        Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
    }

    /**
     * Get redirect URL after successful login
     *
     * @return string
     */
    protected function _getRedirectUrl()
    {
        $redirectPage = $this->_helper->getConfig('general/redirect_page');
        if ($redirectPage) {
            return Mage::getUrl('', array('_direct' => $redirectPage));
        }

        $referer = Mage::getSingleton('customer/session')->getBeforeAuthUrl();
        return $referer ?: Mage::getUrl('customer/account');
    }

    /**
     * Perform HTTP GET request via cURL
     *
     * @param string $url
     * @param array $headers
     * @return string
     */
    protected function _httpGet($url, array $headers = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('HTTP request failed: ' . $error);
        }

        return $result;
    }

    /**
     * Perform HTTP POST request via cURL
     *
     * @param string $url
     * @param array $postFields
     * @param array $headers
     * @return string
     */
    protected function _httpPost($url, array $postFields, array $headers = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('HTTP request failed: ' . $error);
        }

        return $result;
    }

    /**
     * Get callback URL for this provider
     *
     * @return string
     */
    protected function _getCallbackUrl()
    {
        return Mage::getUrl('oauth2/login/callback', array('provider' => $this->_provider));
    }
}
