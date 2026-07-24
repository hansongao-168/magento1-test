<?php
/**
 * AuthorizationCode Storage - implements bshaffer AuthorizationCodeInterface
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
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
     * @param mixed $client_id
     * @param mixed $user_id
     * @param string $redirect_uri
     * @param int $expires
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
