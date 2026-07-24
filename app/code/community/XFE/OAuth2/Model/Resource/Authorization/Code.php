<?php
/**
 * AuthorizationCode Resource Model
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Resource_Authorization_Code extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeoauth2/authorization_code', 'code');
        $this->_isPkAutoIncrement = false;
    }
}
