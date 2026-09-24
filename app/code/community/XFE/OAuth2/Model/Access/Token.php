<?php
/**
 * AccessToken Model
 *
 * Class name MUST be XFE_OAuth2_Model_Access_Token (with an underscore
 * between Access and Token) so that Magento's grouped-class-name resolver
 * in Mage_Core_Model_Config::getGroupedClassName() resolves the alias
 * `xfeoauth2/access_token` to this class. The default uc_words() helper
 * splits underscore-separated parts, so the alias
 *
 *     xfeoauth2 / access_token
 *
 * resolves to the class name
 *
 *     XFE_OAuth2_Model_Access_Token
 *
 * NOT to `XFE_OAuth2_Model_AccessToken`. If the class is named with the
 * one-word spelling, `Mage::getModel('xfeoauth2/access_token')` returns
 * false (because the resolved class name doesn't exist), and the chained
 * `->setAccessToken(...)` call in Storage/AccessToken::setAccessToken()
 * blows up with "Call to a member function setAccessToken() on bool".
 *
 * The resource model at Model/Resource/Access/Token.php uses the same
 * two-word naming for the same reason.
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Access_Token extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeoauth2/access_token');
    }
}