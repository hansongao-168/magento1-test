<?php
/**
 * SocialAccount Model
 *
 * Class name MUST be XFE_OAuth2_Model_Social_Account (two-word) so that
 * the alias `xfeoauth2/social_account` resolves correctly via
 * Mage_Core_Model_Config::getGroupedClassName(). See Access/Token.php
 * for the full explanation.
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Social_Account extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeoauth2/social_account');
    }
}