<?php
/**
 * JSON API v2 Abstract Base Class
 *
 * Handles Bearer Token authentication and standardized JSON responses.
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
abstract class XFE_OAuth2_Model_Api_Abstract
{
    /**
     * @var array
     */
    protected $_tokenData;

    /**
     * @var XFE_OAuth2_Helper_Data
     */
    protected $_helper;

    /**
     * @var string
     */
    protected $_requiredScope;

    /**
     * @var bool
     */
    protected $_requireCustomer = false;

    public function __construct()
    {
        $this->_helper = Mage::helper('xfeoauth2');
    }

    /**
     * Authenticate request via Bearer Token
     *
     * @return bool
     */
    public function authenticate()
    {
        require_once BP . '/lib/XFE/OAuth2/Autoloader.php';
        XFE_OAuth2_Autoloader::register();

        $tokenString = $this->_getBearerToken();

        if (!$tokenString) {
            $this->_helper->sendJsonError(401, 'unauthorized', 'Missing Bearer token');
            return false;
        }

        $storage = new XFE_OAuth2_Model_Storage_AccessToken();
        $this->_tokenData = $storage->getAccessToken($tokenString);

        if (!$this->_tokenData) {
            $this->_helper->sendJsonError(401, 'unauthorized', 'Invalid or expired access token');
            return false;
        }

        // Check expiration
        if ($this->_tokenData['expires'] < time()) {
            $this->_helper->sendJsonError(401, 'unauthorized', 'Access token has expired');
            return false;
        }

        // Check scope
        if ($this->_requiredScope) {
            $tokenScopes = explode(' ', $this->_tokenData['scope']);
            if (!in_array($this->_requiredScope, $tokenScopes) && !in_array('admin', $tokenScopes)) {
                $this->_helper->sendJsonError(401, 'insufficient_scope',
                    'Token does not have the required scope: ' . $this->_requiredScope);
                return false;
            }
        }

        // Set customer context if token has a user_id
        if ($this->_tokenData['user_id']) {
            $customer = Mage::getModel('customer/customer')->load($this->_tokenData['user_id']);
            if ($customer->getId()) {
                Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
            }
        } elseif ($this->_requireCustomer) {
            $this->_helper->sendJsonError(401, 'unauthorized',
                'This endpoint requires a customer-level token');
            return false;
        }

        return true;
    }

    /**
     * Dispatch the API request - to be implemented by subclasses
     *
     * @param string $method
     * @param array $params
     * @return void
     */
    abstract public function dispatch($method, array $params = array());

    /**
     * Extract Bearer token from request headers
     *
     * @return string|null
     */
    protected function _getBearerToken()
    {
        $auth = Mage::app()->getRequest()->getHeader('Authorization');
        if (!$auth) {
            return null;
        }

        if (strpos($auth, 'Bearer ') !== 0) {
            return null;
        }

        return trim(substr($auth, 7));
    }

    /**
     * Send success response
     *
     * @param mixed $data
     * @param array $meta
     * @return void
     */
    protected function _success($data, array $meta = null)
    {
        $this->_helper->sendJson(200, $data, $meta);
    }

    /**
     * Send paginated success response
     *
     * @param array $items
     * @param int $page
     * @param int $limit
     * @param int $total
     * @return void
     */
    protected function _paginated(array $items, $page, $limit, $total)
    {
        $this->_helper->sendJson(200, $items, array(
            'page'  => (int)$page,
            'limit' => (int)$limit,
            'total' => (int)$total,
        ));
    }

    /**
     * Get current page number from request
     *
     * @return int
     */
    protected function _getPage()
    {
        return max(1, (int)Mage::app()->getRequest()->getParam('page', 1));
    }

    /**
     * Get page limit from request
     *
     * @return int
     */
    protected function _getLimit()
    {
        return min(100, max(1, (int)Mage::app()->getRequest()->getParam('limit', 20)));
    }
}
