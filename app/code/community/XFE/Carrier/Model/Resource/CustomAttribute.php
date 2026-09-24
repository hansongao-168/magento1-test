<?php

/**
 * 自定义属性 Resource
 *
 * 关联 xfe_carrier_custom_attribute 表。
 *
 * 关联文档: docs/architecture/carrier-global-custom-field-defs.md
 */
class XFE_Carrier_Model_Resource_CustomAttribute extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_carrier/custom_attribute', 'id');
    }
}
