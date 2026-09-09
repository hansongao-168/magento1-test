<?php

/**
 * 自定义属性 Service(L3)
 *
 * 唯一对外入口: XFE_Carrier_Model_Service_Registry::customAttributeService()
 *
 * 责任:
 *   - 4 分类(承运商 / 账号 / FTP / LOGO)自定义属性的中央管控
 *   - 提供查询(getAllDefs / getActiveDefs / findByKey / getOptionsForDropdown)
 *   - 提供 CRUD(createDef / updateDef / softDeleteDef / activateDef)
 *   - Im/Ex 接口(importFromCsv / exportToCsv)由独立 Importer / Exporter 实现
 *
 * 依赖方向(单向):
 *   CustomAttributeService ─▶  Domain (L1)
 *                            ─▶  Model  (L2)
 *                            ─▶  Event  (Magento Core)
 *
 * 关联文档: docs/architecture/carrier-global-custom-field-defs.md
 */
class XFE_Carrier_Model_Service_CustomAttributeService
{
    /** @var self|null */
    protected static $_instance = null;

    public static function instance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public static function setInstance($instance)
    {
        self::$_instance = $instance;
    }

    // -------------------------------------------------------------------------
    // 查询
    // -------------------------------------------------------------------------

    /**
     * 全量(包含 is_active=0)按 entity_type 过滤的集合,管理后台列表用。
     *
     * @param string $entityType
     * @return XFE_Carrier_Domain_CustomAttributeCollection
     */
    public function getAllDefs($entityType)
    {
        $coll = $this->_newCollection()
            ->addEntityTypeFilter((string) $entityType)
            ->setOrderByDisplay();
        return XFE_Carrier_Domain_CustomAttributeCollection::fromArray(
            $coll->fetchRawRows()
        );
    }

    /**
     * 仅 is_active=1 的集合,实体编辑下拉用。
     *
     * @param string $entityType
     * @return XFE_Carrier_Domain_CustomAttributeCollection
     */
    public function getActiveDefs($entityType)
    {
        $coll = $this->_newCollection()
            ->addEntityTypeFilter((string) $entityType)
            ->addIsActiveFilter()
            ->setOrderByDisplay();
        return XFE_Carrier_Domain_CustomAttributeCollection::fromArray(
            $coll->fetchRawRows()
        );
    }

    /**
     * 仅 is_required=1 的集合,save 校验必填字段时用。
     *
     * @param string $entityType
     * @return XFE_Carrier_Domain_CustomAttributeCollection
     */
    public function getRequiredDefs($entityType)
    {
        $coll = $this->_newCollection()
            ->addEntityTypeFilter((string) $entityType)
            ->addIsActiveFilter()
            ->addIsRequiredFilter()
            ->setOrderByDisplay();
        return XFE_Carrier_Domain_CustomAttributeCollection::fromArray(
            $coll->fetchRawRows()
        );
    }

    /**
     * 单条查询。
     *
     * @param string $entityType
     * @param string $fieldKey
     * @return XFE_Carrier_Domain_CustomAttribute|null
     */
    public function findByKey($entityType, $fieldKey)
    {
        $coll = $this->_newCollection()
            ->addEntityTypeFilter((string) $entityType)
            ->addFieldToFilter('field_key', (string) $fieldKey)
            ->addIsActiveFilter();
        $row = $coll->fetchRawRows();
        if (empty($row)) {
            return null;
        }
        $coll2 = XFE_Carrier_Domain_CustomAttributeCollection::fromArray($row);
        return $coll2->get($fieldKey);
    }

    /**
     * 拉 [field_key => label] 给前端 select 下拉用。
     *
     * @param string $entityType
     * @return array
     */
    public function getOptionsForDropdown($entityType)
    {
        $defs = $this->getActiveDefs($entityType);
        $out = array();
        foreach ($defs as $def) {
            $out[$def->getFieldKey()] = $def->getLabel()
                . ' [' . $def->getFieldType() . ']'
                . ($def->isRequired() ? ' *' : '');
        }
        return $out;
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * 新建一条属性定义。
     *
     * @param array $post  POST 数据,key 兼容 Edit/Form Block 提交的字段名
     * @return XFE_Carrier_Model_CustomAttribute
     * @throws InvalidArgumentException 字段非法时
     * @throws Mage_Core_Exception 唯一索引冲突时
     */
    public function createDef(array $post)
    {
        $normalized = $this->_normalizePost($post, $isNew = true);

        // 同 (entity_type, field_key) 是否已存在(无论 is_active)
        $existing = $this->_newCollection()
            ->addEntityTypeFilter($normalized['entity_type'])
            ->addFieldToFilter('field_key', $normalized['field_key']);
        $existingRows = $existing->fetchRawRows();
        foreach ($existingRows as $r) {
            if ((int) $r['is_active'] === 1) {
                Mage::throwException(Mage::helper('xfe_carrier')->__(
                    '自定义属性已存在: %s / %s',
                    $normalized['entity_type'],
                    $normalized['field_key']
                ));
            }
        }

        $model = Mage::getModel('xfe_carrier/custom_attribute');
        $this->_applyNormalizedToModel($model, $normalized);
        $model->save();

        Mage::dispatchEvent('xfe_carrier_custom_attribute_created', array(
            'def_id'      => (int) $model->getId(),
            'entity_type' => $normalized['entity_type'],
            'field_key'   => $normalized['field_key'],
        ));

        return $model;
    }

    /**
     * 更新一条属性定义(entity_type / field_key 不允许改)。
     *
     * @param int   $id
     * @param array $post
     * @return XFE_Carrier_Model_CustomAttribute
     */
    public function updateDef($id, array $post)
    {
        $id = (int) $id;
        $model = Mage::getModel('xfe_carrier/custom_attribute')->load($id);
        if (!$model->getId()) {
            Mage::throwException(
                Mage::helper('xfe_carrier')->__('自定义属性不存在(ID=%d)。', $id)
            );
        }

        // 保留 entity_type + field_key(创建时即绑定,不可改)
        $normalized = $this->_normalizePost($post, $isNew = false);
        $normalized['entity_type'] = (string) $model->getData('entity_type');
        $normalized['field_key']   = (string) $model->getData('field_key');

        $this->_applyNormalizedToModel($model, $normalized);
        $model->save();

        Mage::dispatchEvent('xfe_carrier_custom_attribute_updated', array(
            'def_id'      => (int) $model->getId(),
            'entity_type' => $normalized['entity_type'],
            'field_key'   => $normalized['field_key'],
        ));

        return $model;
    }

    /**
     * 软删除(置 is_active=0)。
     *
     * @param int $id
     * @return bool
     */
    public function softDeleteDef($id)
    {
        $id = (int) $id;
        $model = Mage::getModel('xfe_carrier/custom_attribute')->load($id);
        if (!$model->getId()) {
            return false;
        }
        $model->setData('is_active', 0);
        $model->save();

        Mage::dispatchEvent('xfe_carrier_custom_attribute_deactivated', array(
            'def_id'      => (int) $model->getId(),
            'entity_type' => (string) $model->getData('entity_type'),
            'field_key'   => (string) $model->getData('field_key'),
        ));
        return true;
    }

    /**
     * 重新激活(置 is_active=1),仅在同 entity_type 无同名活动记录时允许。
     *
     * @param int $id
     * @return bool
     */
    public function activateDef($id)
    {
        $id = (int) $id;
        $model = Mage::getModel('xfe_carrier/custom_attribute')->load($id);
        if (!$model->getId()) {
            return false;
        }

        $entityType = (string) $model->getData('entity_type');
        $fieldKey   = (string) $model->getData('field_key');

        $existing = $this->_newCollection()
            ->addEntityTypeFilter($entityType)
            ->addFieldToFilter('field_key', $fieldKey)
            ->addIsActiveFilter();
        foreach ($existing->fetchRawRows() as $r) {
            if ((int) $r['id'] !== $id) {
                Mage::throwException(Mage::helper('xfe_carrier')->__(
                    '同 (entity_type, field_key) 已存在活动记录,无法激活 ID=%d。',
                    $id
                ));
            }
        }

        $model->setData('is_active', 1);
        $model->save();

        Mage::dispatchEvent('xfe_carrier_custom_attribute_activated', array(
            'def_id'      => (int) $model->getId(),
            'entity_type' => $entityType,
            'field_key'   => $fieldKey,
        ));
        return true;
    }

    // -------------------------------------------------------------------------
    // Im/Ex(详细实现在 Importer / Exporter Service,这里仅做接口预留)
    // -------------------------------------------------------------------------

    /**
     * CSV 导入。具体实现在 Importer Service(待 commit 9 实现)。
     *
     * @param string $filePath
     * @return XFE_Carrier_Model_Service_CustomAttribute_Importer_Result
     */
    public function importFromCsv($filePath)
    {
        return XFE_Carrier_Model_Service_CustomAttribute_Importer::instance()
            ->importFile($filePath);
    }

    /**
     * CSV 导出。具体实现在 Exporter Service(待 commit 9 实现)。
     *
     * @param string|null $entityType null=全部
     * @return string CSV 字符串
     */
    public function exportToCsv($entityType = null)
    {
        return XFE_Carrier_Model_Service_CustomAttribute_Exporter::instance()
            ->exportToString($entityType);
    }

    // -------------------------------------------------------------------------
    // 内部辅助
    // -------------------------------------------------------------------------

    /**
     * @return XFE_Carrier_Model_Resource_CustomAttribute_Collection
     */
    protected function _newCollection()
    {
        return Mage::getResourceModel('xfe_carrier/custom_attribute_collection');
    }

    /**
     * 校验 + 规范化 POST 数据。
     *
     * @param array $post
     * @param bool  $isNew
     * @return array
     */
    protected function _normalizePost(array $post, $isNew)
    {
        $entityType = isset($post['entity_type']) ? (string) $post['entity_type'] : '';
        $fieldKey   = isset($post['field_key'])   ? (string) $post['field_key']   : '';
        $label      = isset($post['label'])       ? (string) $post['label']       : '';
        $fieldType  = isset($post['field_type'])  ? (string) $post['field_type']  : '';
        $optionsCsv = isset($post['options_csv']) ? (string) $post['options_csv'] : '';
        $defaultRaw = isset($post['default_value']) ? $post['default_value']     : null;
        $isRequired = !empty($post['is_required']);
        $isActive   = !empty($post['is_active']);
        $sortOrder  = isset($post['sort_order'])  ? (int) $post['sort_order']     : 0;
        $description = isset($post['description']) ? (string) $post['description'] : null;

        // 域层做完整校验(构造 Domain 时所有不变量被强制)
        $options = null;
        if ($optionsCsv !== '') {
            $options = array_values(array_filter(array_map('trim', explode(',', $optionsCsv))));
        }
        $defaultValue = null;
        if ($defaultRaw !== null && $defaultRaw !== '') {
            // multiselect 用 | 分隔(参见 ADR 0005)
            if ($fieldType === XFE_Carrier_Domain_CustomField::TYPE_MULTISELECT) {
                $defaultValue = is_array($defaultRaw)
                    ? $defaultRaw
                    : array_values(array_filter(array_map('trim', explode('|', (string) $defaultRaw))));
            } elseif (is_string($defaultRaw)) {
                $decoded = json_decode($defaultRaw, true);
                $defaultValue = ($decoded === null && $defaultRaw !== 'null')
                    ? $defaultRaw
                    : $decoded;
            } else {
                $defaultValue = $defaultRaw;
            }
        }

        new XFE_Carrier_Domain_CustomAttribute(
            $isNew ? null : (isset($post['id']) ? (int) $post['id'] : 0),
            $entityType, $fieldKey, $label, $fieldType,
            $options, $defaultValue, $isRequired, $isActive,
            $sortOrder, $description
        );

        return array(
            'entity_type'   => $entityType,
            'field_key'     => $fieldKey,
            'label'         => $label,
            'field_type'    => $fieldType,
            'options_csv'   => $optionsCsv,
            'default_value' => $defaultValue,
            'is_required'   => $isRequired,
            'is_active'     => $isActive,
            'sort_order'    => $sortOrder,
            'description'   => $description,
        );
    }

    /**
     * 把规范化数据写入 Model。default_value 序列化为 JSON 字符串。
     *
     * @param XFE_Carrier_Model_CustomAttribute $model
     * @param array $normalized
     * @return void
     */
    protected function _applyNormalizedToModel($model, array $normalized)
    {
        $model->setData('entity_type', $normalized['entity_type']);
        $model->setData('field_key',   $normalized['field_key']);
        $model->setData('label',       $normalized['label']);
        $model->setData('field_type',  $normalized['field_type']);
        $model->setData('options_csv', $normalized['options_csv'] === '' ? null : $normalized['options_csv']);
        $model->setData(
            'default_value',
            $normalized['default_value'] === null
                ? null
                : json_encode($normalized['default_value'], JSON_UNESCAPED_UNICODE)
        );
        $model->setData('is_required', $normalized['is_required'] ? 1 : 0);
        $model->setData('is_active',   $normalized['is_active']   ? 1 : 0);
        $model->setData('sort_order',  $normalized['sort_order']);
        $model->setData('description', $normalized['description']);
    }
}
