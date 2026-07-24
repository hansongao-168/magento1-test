<?php
/**
 * AccessToken Collection
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Resource_Access_Token_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeoauth2/access_token');
    }
}
