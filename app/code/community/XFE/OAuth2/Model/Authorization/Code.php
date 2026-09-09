<?php
/**
 * AuthorizationCode Model
 *
 * Class name MUST be XFE_OAuth2_Model_Authorization_Code (two-word) so
 * that the alias `xfeoauth2/authorization_code` resolves correctly via
 * Mage_Core_Model_Config::getGroupedClassName(). See Access/Token.php for
 * the full explanation of why uc_words() splits the entity name.
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Authorization_Code extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeoauth2/authorization_code');
    }
}