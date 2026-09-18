<?php
/**
 * AuthorizationCode Storage - implements bshaffer AuthorizationCodeInterface
 *
 * 【关键 - 2026-09-09】顶部显式 require 接口文件。详见
 * Model/Storage/AccessToken.php 同款注释。
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
require_once BP . '/lib/XFE/OAuth2/Autoloader.php';
XFE_OAuth2_Autoloader::register();
require_once BP . '/lib/OAuth2/Storage/AuthorizationCodeInterface.php';

class XFE_OAuth2_Model_Storage_AuthorizationCode implements OAuth2\Storage\AuthorizationCodeInterface
{
    /**
     * @param string $code
     * @return array|null
     */
    public function getAuthorizationCode($code)
    {
        $authCode = Mage::getModel('xfeoauth2/authorization_code')->load($code);
        if (!$authCode->getId()) {
            return null;
        }

        return array(
            'client_id'    => $authCode->getClientId(),
            'user_id'      => $authCode->getUserId(),
            'expires'      => $authCode->getExpires(),
            'redirect_uri' => $authCode->getRedirectUri(),
            'scope'        => $authCode->getScope(),
            'id_token'     => $authCode->getIdToken(),
        );
    }

    /**
     * @param string $code
     * @param mixed  $client_id
     * @param mixed  $user_id
     * @param string $redirect_uri
     * @param int    $expires
     * @param string $scope
     * @return void
     */
    public function setAuthorizationCode($code, $client_id, $user_id, $redirect_uri, $expires, $scope = null)
    {
        Mage::getModel('xfeoauth2/authorization_code')
            ->setCode($code)
            ->setClientId($client_id)
            ->setUserId($user_id)
            ->setUserType('customer')
            ->setRedirectUri($redirect_uri)
            ->setExpires($expires)
            ->setScope($scope)
            ->setCreatedAt(now())
            ->save();
    }

    /**
     * @param string $code
     * @return void
     */
    public function expireAuthorizationCode($code)
    {
        $authCode = Mage::getModel('xfeoauth2/authorization_code')->load($code);
        if ($authCode->getId()) {
            $authCode->delete();
        }
    }
}
