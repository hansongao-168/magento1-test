<?php
/**
 * AccessToken Resource Model
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Resource_Access_Token extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeoauth2/access_token', 'access_token');
        $this->_isPkAutoIncrement = false;
    }
}
