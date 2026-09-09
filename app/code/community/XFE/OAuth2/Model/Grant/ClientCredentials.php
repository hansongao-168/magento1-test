<?php
/**
 * Client Credentials grant type that also issues a refresh token.
 *
 * The bshaffer library intentionally omits refresh tokens from the
 * client_credentials grant (RFC 6749 4.4.3). Internal systems that obtain
 * tokens this way still need a way to rotate the short-lived access token,
 * so this subclass re-enables refresh token issuance.
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
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
