<?php

/**
 * FTP账号资源 Collection
 */
class XFE_Carrier_Model_Resource_Carrier_FtpAccount_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_carrier/carrier_ftp_account');
    }
}