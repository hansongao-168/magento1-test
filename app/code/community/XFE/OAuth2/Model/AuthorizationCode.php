<?php
/**
 * AuthorizationCode Model
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_AuthorizationCode extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeoauth2/authorization_code');
    }
}
