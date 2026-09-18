<?php
/**
 * RefreshToken Storage - implements bshaffer RefreshTokenInterface
 *
 * 【关键 - 2026-09-09】顶部显式 require 接口文件。详见
 * Model/Storage/AccessToken.php 同款注释。
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
require_once BP . '/lib/XFE/OAuth2/Autoloader.php';
XFE_OAuth2_Autoloader::register();
require_once BP . '/lib/OAuth2/Storage/RefreshTokenInterface.php';

class XFE_OAuth2_Model_Storage_RefreshToken implements OAuth2\Storage\RefreshTokenInterface
{
    /**
     * @param string $refresh_token
     * @return array|null
     */
    public function getRefreshToken($refresh_token)
    {
        $token = Mage::getModel('xfeoauth2/refresh_token')->load($refresh_token);
        if (!$token->getId()) {
            return null;
        }

        return array(
            'refresh_token' => $token->getRefreshToken(),
            'client_id'     => $token->getClientId(),
            'user_id'       => $token->getUserId(),
            'expires'       => $token->getExpires(),
            'scope'         => $token->getScope(),
        );
    }

    /**
     * @param string $refresh_token
     * @param mixed  $client_id
     * @param mixed  $user_id
     * @param int    $expires
     * @param string $scope
     * @return void
     */
    public function setRefreshToken($refresh_token, $client_id, $user_id, $expires, $scope = null)
    {
        Mage::getModel('xfeoauth2/refresh_token')
            ->setRefreshToken($refresh_token)
            ->setClientId($client_id)
            ->setUserId($user_id)
            ->setExpires($expires)
            ->setScope($scope)
            ->setCreatedAt(now())
            ->save();
    }

    /**
     * @param string $refresh_token
     * @return void
     */
    public function unsetRefreshToken($refresh_token)
    {
        $token = Mage::getModel('xfeoauth2/refresh_token')->load($refresh_token);
        if ($token->getId()) {
            $token->delete();
        }
    }
}
