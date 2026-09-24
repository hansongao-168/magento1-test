<?php
/**
 * Client Credentials grant type that also issues a refresh token.
 *
 * The bshaffer library intentionally omits refresh tokens from the
 * client_credentials grant (RFC 6749 4.4.3). Internal systems that obtain
 * tokens this way still need a way to rotate the short-lived access token,
 * so this subclass re-enables refresh token issuance.
 *
 * 【2026-09-09】本类 `extends OAuth2\GrantType\ClientCredentials` 会强制
 * PHP 加载时 resolve 父类。父类位于 lib/OAuth2/...，走自建 autoloader。
 * 必须在类声明之前确保 autoloader 已注册并使用绝对路径（详见
 * lib/XFE/OAuth2/Autoloader.php）。register() 内部自带 $_registered
 * 幂等保护，重复调用是 no-op。
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
require_once BP . '/lib/XFE/OAuth2/Autoloader.php';
XFE_OAuth2_Autoloader::register();

class XFE_OAuth2_Model_Grant_ClientCredentials extends OAuth2\GrantType\ClientCredentials
{
    /**
     * Create access token and always include a refresh token.
     *
     * @param OAuth2\ResponseType\AccessTokenInterface $accessToken
     * @param mixed $clientId
     * @param mixed $userId
     * @param string $scope
     * @return array
     */
    public function createAccessToken(OAuth2\ResponseType\AccessTokenInterface $accessToken, $clientId, $userId, $scope)
    {
        return $accessToken->createAccessToken($clientId, $userId, $scope, true);
    }
}
