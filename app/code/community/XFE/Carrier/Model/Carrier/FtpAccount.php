<?php

/**
 * FTP账号模型
 *
 * 平行于 XFE_Carrier_Model_Carrier_Account,承载一个承运商下
 * 多个 FTP/SFTP/FTPS 接入点的配置信息。
 *
 * 持久化列与 xfe_carrier_ftp_account 表一一对应;其余字段
 * (host/port/protocol/username/password/remote_path/mode/encoding)
 * 由 Controller 的 saveFtpAccountAction 通过 addData() 落入数据库。
 */
class XFE_Carrier_Model_Carrier_FtpAccount extends Mage_Core_Model_Abstract
{
    protected $_eventPrefix = 'xfe_carrier_ftp_account';
    protected $_eventObject = 'ftp_account';

    protected function _construct()
    {
        $this->_init('xfe_carrier/carrier_ftp_account');
    }

    protected function _beforeSave()
    {
        parent::_beforeSave();
        $now = Varien_Date::now();
        if ($this->isObjectNew()) {
            $this->setCreatedAt($now);
        }
        $this->setUpdatedAt($now);
        return $this;
    }
}