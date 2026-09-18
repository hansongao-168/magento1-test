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

    // -------------------------------------------------------------------------
    // options_csv 变更迁移策略(小改 C,2026-09-17)
    // -------------------------------------------------------------------------

    /** 策略:拒绝修改,旧 default_value 不兼容时抛异常(默认,向后兼容) */
    const MIGRATION_STRATEGY_REJECT = 'reject';

    /** 策略:自动清洗 — select 置 null;multiselect 过滤非法项 */
    const MIGRATION_STRATEGY_AUTO_CLEAN = 'auto_clean';

    /** 策略:置空 — select 置 null;multiselect 置空数组 */
    const MIGRATION_STRATEGY_SET_NULL = 'set_null';

    /** 全部合法策略(校验 POST 输入用) */
    const MIGRATION_STRATEGIES = array(
        self::MIGRATION_STRATEGY_REJECT,
        self::MIGRATION_STRATEGY_AUTO_CLEAN,
        self::MIGRATION_STRATEGY_SET_NULL,
    );

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

        // 小改 C(2026-09-17):options_csv 变更时检测 default_value 兼容性
        // 默认 reject 抛异常,行为与改动前一致;POST 传 migration_strategy 可选 auto_clean / set_null
        $oldOptionsCsv = (string) $model->getData('options_csv');
        if ($oldOptionsCsv !== $normalized['options_csv']) {
            list($status, $incompatible, $oldOpts) = $this->_detectOptionsMigration(
                $oldOptionsCsv,
                $normalized['options_csv'],
                $normalized['field_type'],
                $normalized['default_value']
            );
            if ($status === 'incompatible') {
                $strategy = isset($post['migration_strategy'])
                    ? (string) $post['migration_strategy']
                    : self::MIGRATION_STRATEGY_REJECT;
                $normalized = $this->_applyMigrationStrategy(
                    $normalized,
                    $incompatible,
                    $strategy,
                    $normalized['field_type']
                );
            }
        }

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


    /**
     * 检测 options_csv 变更后,default_value 是否会落入不合法状态(小改 C,2026-09-17)。
     *
     * @param string $oldOptionsCsv
     * @param string $newOptionsCsv
     * @param string                            $newFieldType
     * @param mixed                             $newDefaultValue 规范化后的新 default_value
     * @return array{0:string,1:array,2:string[]}
     *   [0] = 'ok' 或 'incompatible'
     *   [1] = 不在新 options 内的值(select=单字符串,multiselect=字符串数组,其他=空数组)
     *   [2] = 旧 options(字符串数组,供日志)
     */
    protected function _detectOptionsMigration($oldOptionsCsv, $newOptionsCsv, $newFieldType, $newDefaultValue)
    {
        // 小改 K(2026-09-18):boolean 也走 options 校验(2 行 + key ∈ {0,1} + default ∈ {0,1})(ADR 0023)
        if ($newFieldType === XFE_Carrier_Domain_CustomField::TYPE_BOOLEAN) {
            return $this->_detectBooleanMigration($oldOptionsCsv, $newOptionsCsv, $newDefaultValue);
        }
        if ($newFieldType !== XFE_Carrier_Domain_CustomField::TYPE_SELECT
            && $newFieldType !== XFE_Carrier_Domain_CustomField::TYPE_MULTISELECT
        ) {
            return array('ok', array(), array());
        }
        $newOptions = array();
        if ($newOptionsCsv !== '') {
            $newOptions = array_values(array_filter(array_map('trim', explode(',', $newOptionsCsv))));
        }
        $oldOptions = array();
        if ($oldOptionsCsv !== '') {
            $oldOptions = array_values(array_filter(array_map('trim', explode(',', $oldOptionsCsv))));
        }
        if ($newDefaultValue === null || $newDefaultValue === '' || $newDefaultValue === array()) {
            return array('ok', array(), $oldOptions);
        }
        if ($newFieldType === XFE_Carrier_Domain_CustomField::TYPE_SELECT) {
            if (!in_array((string) $newDefaultValue, $newOptions, true)) {
                return array('incompatible', array((string) $newDefaultValue), $oldOptions);
            }
            return array('ok', array(), $oldOptions);
        }
        if (!is_array($newDefaultValue)) {
            $newDefaultValue = array((string) $newDefaultValue);
        }
        $bad = array();
        foreach ($newDefaultValue as $v) {
            if (!in_array((string) $v, $newOptions, true)) {
                $bad[] = (string) $v;
            }
        }
        if (count($bad) > 0) {
            return array('incompatible', $bad, $oldOptions);
        }
        return array('ok', array(), $oldOptions);
    }

    /**
     * 按 strategy 处理不兼容的 default_value(小改 C,2026-09-17)。
     *
     * @param array  $normalized
     * @param array  $incompatible  不在新 options 内的值列表
     * @param string $strategy      reject | auto_clean | set_null
     * @param string $newFieldType
     * @return array 修改后的 $normalized
     * @throws Mage_Core_Exception strategy=reject 时
     */

    /**
     * 校验规则:
     *   1. newOptionsCsv 解析后必须正好 2 行
     *   2. 解析出的 key(按 trim)集合必须 \u2261 {0, 1}
     *   3. newDefaultValue 非空时必须 ∈ {0, 1}
     *
     * 旧 options 不参与校验(允许 label 文案修改)。
     *
     * @param string $oldOptionsCsv \u672a\u4f7f\u7528(\u4fdd\u7559\u53c2\u6570\u4ee5\u4fbf\u672a\u6765\u6269\u5c55)
     * @param string $newOptionsCsv
     * @param mixed  $newDefaultValue
     * @return array{0:string,1:array,2:string[]} \u4e0e _detectOptionsMigration \u540c\u5f62\u6001
     */
    protected function _detectBooleanMigration($oldOptionsCsv, $newOptionsCsv, $newDefaultValue)
    {
        // 解析 new options 为结构化对
        $newPairs = XFE_Carrier_Domain_CustomAttribute::parseOptionsCsvToPairs($newOptionsCsv);
        if (count($newPairs) !== 2) {
            return array('incompatible', array('options_count=' . count($newPairs)), array());
        }
        // key 集合必须 \u2261 {0, 1}
        $keys = array_map(function ($p) { return (string) $p['key']; }, $newPairs);
        sort($keys);
        if ($keys !== array('0', '1')) {
            return array('incompatible', array('options_keys=[' . implode(',', $keys) . ']'), array());
        }
        // default_value 必须 ∈ {0, 1}(如果非空)
        if ($newDefaultValue !== null && $newDefaultValue !== '' && $newDefaultValue !== false) {
            $norm = (is_bool($newDefaultValue)) ? ($newDefaultValue ? '1' : '0') : (string) $newDefaultValue;
            if ($norm !== '0' && $norm !== '1') {
                return array('incompatible', array('default_value=' . $norm), array());
            }
        }
        return array('ok', array(), array());
    }

    protected function _applyMigrationStrategy(array $normalized, array $incompatible, $strategy, $newFieldType)
    {
        if (!in_array($strategy, self::MIGRATION_STRATEGIES, true)) {
            $strategy = self::MIGRATION_STRATEGY_REJECT;
        }
        if ($strategy === self::MIGRATION_STRATEGY_REJECT) {
            $badList = implode(', ', array_map(
                function ($v) { return '"' . $v . '"'; },
                $incompatible
            ));
            Mage::throwException(Mage::helper('xfe_carrier')->__(
                '字段 "%s" 的候选项(options_csv)变更后,默认值 %s 已不在新候选项内。'
                . '请在提交前调整 default_value,或在 POST 中传 migration_strategy'
                . ' = auto_clean | set_null 以自动处理。',
                (string) $normalized['field_key'],
                $badList
            ));
        }
        if ($strategy === self::MIGRATION_STRATEGY_SET_NULL) {
            if ($newFieldType === XFE_Carrier_Domain_CustomField::TYPE_MULTISELECT) {
                $normalized['default_value'] = array();
            } else {
                $normalized['default_value'] = null;
            }
            return $normalized;
        }
        // auto_clean
        if ($newFieldType === XFE_Carrier_Domain_CustomField::TYPE_SELECT) {
            $normalized['default_value'] = null;
            return $normalized;
        }
        if ($newFieldType === XFE_Carrier_Domain_CustomField::TYPE_MULTISELECT) {
            $current = is_array($normalized['default_value']) ? $normalized['default_value'] : array();
            $incompatibleMap = array_flip($incompatible);
            $kept = array();
            foreach ($current as $v) {
                if (!isset($incompatibleMap[(string) $v])) {
                    $kept[] = (string) $v;
                }
            }
            $normalized['default_value'] = array_values($kept);
            return $normalized;
        }
        return $normalized;
    }
}
