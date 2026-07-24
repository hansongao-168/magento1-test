<?php
/**
 * OAuth 2.0 Server Factory - assembles and configures bshaffer OAuth2\Server
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Server
{
    /**
     * @var OAuth2\Server
     */
    protected $_server;

    /**
     * @var XFE_OAuth2_Helper_Data
     */
    protected $_helper;

    /**
     * @var array
     */
    protected $_storages = array();

    /**
     * Constructor - register autoloader and build server
     */
    public function __construct()
    {
        require_once BP . '/lib/XFE/OAuth2/Autoloader.php';
        XFE_OAuth2_Autoloader::register();

        $this->_helper = Mage::helper('xfeoauth2');
        $this->_storages = array(
            'client_credentials' => new XFE_OAuth2_Model_Storage_ClientCredentials(),
            'access_token'       => new XFE_OAuth2_Model_Storage_AccessToken(),
            'refresh_token'      => new XFE_OAuth2_Model_Storage_RefreshToken(),
            'authorization_code' => new XFE_OAuth2_Model_Storage_AuthorizationCode(),
        );
    }

    /**
     * Get configured OAuth2\Server instance
     *
     * @return OAuth2\Server
     */
    public function getServer()
    {
        if ($this->_server === null) {
            $this->_server = new OAuth2\Server(
                $this->_storages,
                array(
                    'access_lifetime'        => 3600,    // 1 hour
                    'refresh_token_lifetime' => 2592000, // 30 days
                    'allow_implicit'         => false,
                    'enforce_state'          => false,
                    'require_exact_redirect_uri' => true,
                    'always_issue_new_refresh_token' => true,
                )
            );

            // Authorization Code grant
            $this->_server->addGrantType(
                new OAuth2\GrantType\AuthorizationCode($this->_storages)
            );

            // Client Credentials grant
            $this->_server->addGrantType(
                new OAuth2\GrantType\ClientCredentials($this->_storages)
            );

            // Refresh Token grant
            $this->_server->addGrantType(
                new OAuth2\GrantType\RefreshToken(
                    $this->_storages,
                    array('always_issue_new_refresh_token' => true)
                )
            );
        }

        return $this->_server;
    }

    /**
     * Get a specific storage instance
     *
     * @param string $key
     * @return object|null
     */
    public function getStorage($key)
    {
        return isset($this->_storages[$key]) ? $this->_storages[$key] : null;
    }
}
