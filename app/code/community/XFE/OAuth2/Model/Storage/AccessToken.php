<?php
/**
 * AccessToken Storage - implements bshaffer AccessTokenInterface
 *
 * 行为契约与 bshaffer AccessTokenInterface 保持一致：
 *   - getAccessToken($oauth_token): ?array
 *   - setAccessToken($oauth_token, $client_id, $user_id, $expires, $scope = null): void
 *
 * 【关键 - 2026-09-09】PHP 在编译本类时必须立即 resolve
 * `implements OAuth2\Storage\AccessTokenInterface` 指向的接口，
 * 否则会回退到 `include(类名)` 报
 * "failed to open stream: No such file or directory"。
 *
 * autoloader (lib/XFE/OAuth2/Autoloader.php) 在 helper/server 还没
 * 实例化时不会被调用，所以本文件顶部显式 require 接口文件，让 PHP
 * 编译本类时接口已经加载到 Zend 内存里。register() 内部 $_registered
 * 幂等保护，多个入口重复 register 是 no-op。
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
require_once BP . '/lib/XFE/OAuth2/Autoloader.php';
XFE_OAuth2_Autoloader::register();
require_once BP . '/lib/OAuth2/Storage/AccessTokenInterface.php';

class XFE_OAuth2_Model_Storage_AccessToken implements OAuth2\Storage\AccessTokenInterface
{
    /**
     * @param string $oauth_token
     * @return array|null
     */
    public function getAccessToken($oauth_token)
    {
        $token = Mage::getModel('xfeoauth2/access_token')->load($oauth_token);
        if (!$token->getId()) {
            return null;
        }

        return array(
            'expires'   => $token->getExpires(),
            'client_id' => $token->getClientId(),
            'user_id'   => $token->getUserId(),
            'scope'     => $token->getScope(),
            'user_type' => $token->getUserType(),
        );
    }

    /**
     * @param string $oauth_token
     * @param mixed  $client_id
     * @param mixed  $user_id
     * @param int    $expires
     * @param string $scope
     * @return void
     */
    public function setAccessToken($oauth_token, $client_id, $user_id, $expires, $scope = null)
    {
        Mage::getModel('xfeoauth2/access_token')
            ->setAccessToken($oauth_token)
            ->setClientId($client_id)
            ->setUserId($user_id)
            ->setUserType($user_id ? 'customer' : 'client')
            ->setExpires($expires)
            ->setScope($scope)
            ->setCreatedAt(now())
            ->save();
    }
}
