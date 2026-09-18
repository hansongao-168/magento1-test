<?php
/**
 * ClientCredentials Storage - implements bshaffer ClientCredentials + Scope interfaces
 *
 * 【关键 - 2026-09-09】顶部显式 require 接口文件，让 PHP 编译本类时
 * 接口已加载，绕过 autoloader 注册时序问题。详见
 * Model/Storage/AccessToken.php 同款注释。
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
require_once BP . '/lib/XFE/OAuth2/Autoloader.php';
XFE_OAuth2_Autoloader::register();
require_once BP . '/lib/OAuth2/Storage/ClientCredentialsInterface.php';
require_once BP . '/lib/OAuth2/Storage/ScopeInterface.php';

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

        // Enforce client_secret expiry (ADR 0007 rev. 2: hard expiry).
        //
        // NULL expires_at is a legacy row that pre-dates the TTL feature;
        // treat it as "no TTL configured" and keep the legacy behaviour
        // (verify bcrypt, no expiry check) so that upgrading from 1.0.2 to
        // 1.0.3 does NOT silently brick all existing clients. The upgrade
        // script (upgrade-1.0.2.0-1.0.3.0.php) back-fills expires_at for
        // all NULL rows, so any client that was loaded into the system
        // before the upgrade still gets an expiry; only a row inserted
        // manually without going through the controller would stay NULL,
        // and those are explicitly out of the support contract.
        if ($client->getClientSecretExpiresAt() !== null
            && $client->getClientSecretExpiresAt() !== ''
        ) {
            if ($this->_helper->isClientSecretExpired($client)) {
                // Log once per (client_id, expires_at) tuple so an admin
                // can grep for "rejected because expired" without noise.
                $this->_helper->log(
                    'OAuth2 token request rejected: client_secret expired for '
                    . $client_id . ' (expired at '
                    . $client->getClientSecretExpiresAt() . ')'
                );
                return false;
            }
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

        // Tolerate comma/whitespace/newline separators and any case so that
        // legacy rows saved before the normalization helper existed still
        // pass the check (e.g. "REFRESH_TOKEN" or "client_credentials\nrefresh_token").
        $tokens = preg_split('/[\s,]+/', (string)$details['grant_types'], -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_map('strtolower', array_map('trim', $tokens));

        return in_array(strtolower((string)$grant_type), $tokens, true);
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

