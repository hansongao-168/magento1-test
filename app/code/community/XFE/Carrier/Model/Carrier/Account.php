<?php

/**
 * 承运商账号 Model
 *
 * 承载 xfe_carrier_carrier_account 单行。固定列与 1.0.1 起的安装脚本一致;
 * `custom_fields_json` 1.0.14 引入,承载 EAV-like 键值对扩展字段
 * (XFE_Carrier_Domain_CustomFieldCodec 负责 JSON ↔ 值对象互转)。
 *
 * 字段写入路径:
 *   - 固定列:Controller 直接 addData() 后 save()
 *   - custom_fields_json:Controller 拿到 custom_fields POST 段后,
 *      调 XFE_Carrier_Model_Service_Account_CustomFieldService::applyFromPost()
 *      写回。本 Model 只提供 getter/setter,不直接反序列化。
 *
 * 读取路径:
 *   - getCustomField($key):快捷读取单个字段值(在 Model 已经在内存里时使用)
 *   - 推荐:业务模块走 CredentialResolver 拿账号,然后调 getCustomField
 *     —— 不需要 Service 再 load 一次。
 */
class XFE_Carrier_Model_Carrier_Account extends Mage_Core_Model_Abstract
{
    protected $_eventPrefix = 'xfe_carrier_account';
    protected $_eventObject = 'account';

    protected function _construct()
    {
        $this->_init('xfe_carrier/carrier_account');
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
     * @return XFE_Carrier_Model_Carrier_Account
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
     * 读单个自定义字段值。仅在 Model 已经在内存里时使用 —— 避免再次 load。
     * 业务模块首选用 Service::getValue() / getCollection(),本方法只是便利访问。
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
