<?php

/**
 * FTP账号资源模型
 *
 * 与 xfe_carrier_ftp_account 表绑定。
 */
class XFE_Carrier_Model_Resource_Carrier_FtpAccount extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_carrier/carrier_ftp_account', 'ftp_account_id');
    }
}