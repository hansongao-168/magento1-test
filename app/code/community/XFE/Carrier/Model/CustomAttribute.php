<?php

/**
 * 自定义属性 Model
 *
 * 承载 xfe_carrier_custom_attribute 单行(全局属性定义)。
 *
 * 字段写入路径:
 *   - Controller addData() 后 save() — Service 层负责校验
 *
 * 读取路径:
 *   - 业务模块:走 Service::getActiveDefs($entityType) 拿集合
 *   - 直接用 Model:Service 内部用 getDomain() 包装为不可变 Domain 对象
 *
 * 关联文档: docs/architecture/carrier-global-custom-field-defs.md
 */
class XFE_Carrier_Model_CustomAttribute extends Mage_Core_Model_Abstract
{
    protected $_eventPrefix = 'xfe_carrier_custom_attribute';
    protected $_eventObject = 'custom_attribute';

    protected function _construct()
    {
        $this->_init('xfe_carrier/custom_attribute');
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
     * 把 Mage Model 包装为不可变 Domain 值对象(供 Service 层使用)。
     *
     * @return XFE_Carrier_Domain_CustomAttribute
     */
    public function getDomain()
    {
        $optionsRaw = $this->getData('options_csv');
        $options = null;
        if (is_string($optionsRaw) && $optionsRaw !== '') {
            $options = array_values(array_filter(array_map(
                'trim',
                explode(',', $optionsRaw)
            )));
        }

        $defaultRaw = $this->getData('default_value');
        $defaultValue = null;
        if (is_string($defaultRaw) && $defaultRaw !== '') {
            $decoded = json_decode($defaultRaw, true);
            if ($decoded !== null) {
                $defaultValue = $decoded;
            }
        }

        return new XFE_Carrier_Domain_CustomAttribute(
            (int) $this->getId(),
            (string) $this->getData('entity_type'),
            (string) $this->getData('field_key'),
            (string) $this->getData('label'),
            (string) $this->getData('field_type'),
            $options,
            $defaultValue,
            (bool) $this->getData('is_required'),
            (bool) $this->getData('is_active'),
            (int) $this->getData('sort_order'),
            $this->getData('description')
        );
    }
}
