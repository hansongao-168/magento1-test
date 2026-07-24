<?php
/**
 * AccessToken Model
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_AccessToken extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeoauth2/access_token');
    }
}
