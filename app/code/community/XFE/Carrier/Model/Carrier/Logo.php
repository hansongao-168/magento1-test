<?php

class XFE_Carrier_Model_Carrier_Logo extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_carrier/carrier_logo');
    }

    protected function _beforeSave()
    {
        parent::_beforeSave();

        $now = Varien_Date::now();
        if ($this->isObjectNew()) {
            $this->setCreatedAt($now);
        }
        // Touch updated_at on every save so the resolver's most-recently-updated
        // tie-breaker picks the last edited logo first.
        $this->setUpdatedAt($now);

        return $this;
    }

    /**
     * 原始 JSON 字符串(列原文)。Service 通过此 setter 写入。
     *
     * @param string|null $json
     * @return XFE_Carrier_Model_Carrier_Logo
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
     * 读单个自定义属性值。仅在 Model 已经在内存里时使用 —— 避免再次 load。
     *
     * @param string $key
     * @return string|int|float|bool|string[]|null
     */
    public function getCustomField($key)
    {
        $coll = XFE_Carrier_Domain_CustomFieldCodec::decode($this->getCustomFieldsJson());
        $field = $coll->get($key);
        return $field ? $field->getValue() : null;
    }

    /**
     * @return string|null
     */
    public function getLabel()
    {
        return $this->getData('label');
    }

    /**
     * @param string $label
     * @return $this
     */
    public function setLabel($label)
    {
        return $this->setData('label', $label);
    }

    /**
     * @return string|null
     */
    public function getLogoType()
    {
        return $this->getData('logo_type');
    }

    /**
     * @param string $logoType
     * @return $this
     */
    public function setLogoType($logoType)
    {
        return $this->setData('logo_type', $logoType);
    }

    /**
     * @return int|null
     */
    public function getSortOrder()
    {
        return $this->getData('sort_order');
    }

    /**
     * @param int $sortOrder
     * @return $this
     */
    public function setSortOrder($sortOrder)
    {
        return $this->setData('sort_order', $sortOrder);
    }
}
