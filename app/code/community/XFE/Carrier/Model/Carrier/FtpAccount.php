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

    /**
     * 原始 JSON 字符串(列原文)。Service 通过此 setter 写入。
     *
     * @param string|null $json
     * @return XFE_Carrier_Model_Carrier_FtpAccount
     */
    public function setCustomFieldsJson($json)
    {
        if ($json !== null && $json !== '' && is_string($json) === false) {
            $json = (string) $json;
        }
        return $this->setData('custom_fields_json', $json);
    }

    /**
     * 原始 JSON 字符串(列原文,可能为 null)。
     *
     * @return string|null
     */
    public function getCustomFieldsJson()
    {
        return $this->getData('custom_fields_json');
    }

    /**
     * 读单个自定义字段值。仅在 Model 已经在内存里时使用。
     *
     * @param string $key
     * @return string|int|float|bool|null
     */
    public function getCustomField($key)
    {
        $coll = XFE_Carrier_Domain_CustomFieldCodec::decode($this->getCustomFieldsJson());
        $field = $coll->get($key);
        return $field ? $field->getValue() : null;
    }
}