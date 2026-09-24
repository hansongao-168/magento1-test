<?php

/**
 * 自定义属性 Resource Collection
 *
 * 提供 addEntityTypeFilter / addIsActiveFilter / addIsRequiredFilter
 * / setOrderByDisplay 等方法,供 Service 层使用。
 *
 * 关联文档: docs/architecture/carrier-global-custom-field-defs.md
 */
class XFE_Carrier_Model_Resource_CustomAttribute_Collection
    extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_carrier/custom_attribute', 'xfe_carrier/custom_attribute');
    }

    /**
     * 过滤 entity_type(carrier / account / ftp_account / logo)。
     *
     * @param string $entityType
     * @return $this
     */
    public function addEntityTypeFilter($entityType)
    {
        $this->addFieldToFilter('entity_type', (string) $entityType);
        return $this;
    }

    /**
     * 过滤 is_active=1。
     *
     * @return $this
     */
    public function addIsActiveFilter()
    {
        $this->addFieldToFilter('is_active', 1);
        return $this;
    }

    /**
     * 过滤 is_required=1。
     *
     * @return $this
     */
    public function addIsRequiredFilter()
    {
        $this->addFieldToFilter('is_required', 1);
        return $this;
    }

    /**
     * 按 sort_order 升序 + field_key 字母序排序。
     *
     * @return $this
     */
    public function setOrderByDisplay()
    {
        $this->setOrder('sort_order', 'ASC');
        $this->setOrder('field_key', 'ASC');
        return $this;
    }

    /**
     * 直接返回 DB 行 array list,供 CustomAttributeCollection::fromArray 灌入。
     * 跳过 Mage Model 包装,性能更优。
     *
     * @return array
     */
    public function fetchRawRows()
    {
        return $this->getConnection()->fetchAll($this->getSelect());
    }
}
