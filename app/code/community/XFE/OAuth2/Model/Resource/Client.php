<?php
/**
 * Client Resource Model
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Resource_Client extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeoauth2/client', 'client_id');
        $this->_isPkAutoIncrement = false;
    }
}
