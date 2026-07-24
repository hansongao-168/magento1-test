<?php
/**
 * AccessToken Storage - implements bshaffer AccessTokenInterface
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
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
     * @param mixed $client_id
     * @param mixed $user_id
     * @param int $expires
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
