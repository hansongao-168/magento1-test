<?php
/**
 * ClientCredentials Storage - implements bshaffer ClientCredentials + Scope interfaces
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Storage_ClientCredentials implements
    OAuth2\Storage\ClientCredentialsInterface,
    OAuth2\Storage\ScopeInterface
{
    /**
     * @var XFE_OAuth2_Model_Resource_Client
     */
    protected $_resource;

    /**
     * @var XFE_OAuth2_Helper_Data
     */
    protected $_helper;

    public function __construct()
    {
        $this->_resource = Mage::getResourceModel('xfeoauth2/client');
        $this->_helper = Mage::helper('xfeoauth2');
    }

    /**
     * @param string $client_id
     * @return array|false
     */
    public function getClientDetails($client_id)
    {
        $client = Mage::getModel('xfeoauth2/client')->load($client_id);
        if (!$client->getId() || !$client->getStatus()) {
            return false;
        }

        return array(
            'redirect_uri' => $client->getRedirectUri(),
            'client_id'    => $client->getClientId(),
            'grant_types'  => $client->getGrantTypes(),
            'user_id'      => $client->getUserId(),
            'scope'        => $client->getScopes(),
        );
    }

    /**
     * @param string $client_id
     * @return string|null
     */
    public function getClientScope($client_id)
    {
        $client = Mage::getModel('xfeoauth2/client')->load($client_id);
        return $client->getId() ? $client->getScopes() : null;
    }

    /**
     * @param string $client_id
     * @param string $client_secret
     * @return bool
     */
    public function checkClientCredentials($client_id, $client_secret = null)
    {
        $client = Mage::getModel('xfeoauth2/client')->load($client_id);
        if (!$client->getId()) {
            return false;
        }
        return $this->_helper->verifySecret($client_secret, $client->getClientSecret());
    }

    /**
     * @param string $client_id
     * @return bool
     */
    public function isPublicClient($client_id)
    {
        return false;
    }

    /**
     * @param string $client_id
     * @param string $grant_type
     * @return bool
     */
    public function checkRestrictedGrantType($client_id, $grant_type)
    {
        $details = $this->getClientDetails($client_id);
        if ($details === false) {
            return false;
        }

        $grantTypes = array_map('trim', explode(',', $details['grant_types']));
        return in_array($grant_type, $grantTypes);
    }

    /**
     * @param string $scope
     * @return bool
     */
    public function scopeExists($scope)
    {
        $scopes = array(
            'basic',
            'orders',
            'customers',
            'carriers',
            'admin',
        );

        $requestedScopes = explode(' ', trim($scope));
        foreach ($requestedScopes as $s) {
            if (!in_array($s, $scopes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $client_id
     * @return string
     */
    public function getDefaultScope($client_id = null)
    {
        return 'basic';
    }
}
