<?php
/**
 * Authorize Block - renders the OAuth2 authorization confirmation page
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Block_Authorize extends Mage_Core_Block_Template
{
    /**
     * Get the client requesting authorization
     *
     * @return XFE_OAuth2_Model_Client
     */
    public function getClient()
    {
        return Mage::registry('xfeoauth2_authorize_client');
    }

    /**
     * Get the authorization request
     *
     * @return OAuth2\Request
     */
    public function getAuthorizeRequest()
    {
        return Mage::registry('xfeoauth2_authorize_request');
    }

    /**
     * Get form action URL
     *
     * @return string
     */
    public function getConfirmUrl()
    {
        return Mage::getUrl('oauth2/authorize/confirm');
    }
}
