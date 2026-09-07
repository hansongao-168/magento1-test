<?php
/**
 * 自定义属性集合(L1 Domain 值对象)
 *
 * 同一 entity_type 内 field_key 唯一(由 add() 强制)。
 * 跨 entity_type 可以重名——本集合内仅做"按 key 唯一"约束,不区分 entity_type。
 * 因此 Service 层应按 entity_type 过滤后再灌入本集合。
 *
 * 本类**不引用任何 Mage_* 类**。
 *
 * 关联文档: docs/architecture/carrier-global-custom-field-defs.md
 */
final class XFE_Carrier_Domain_CustomAttributeCollection
    implements IteratorAggregate, Countable
{
    /** @var XFE_Carrier_Domain_CustomAttribute[] */
    private $attributes = array();

    /**
     * @param XFE_Carrier_Domain_CustomAttribute $attr
     * @return $this
     * @throws DomainException 当 field_key 重复时
     */
    public function add(XFE_Carrier_Domain_CustomAttribute $attr)
    {
        if (isset($this->attributes[$attr->getFieldKey()])) {
            throw new DomainException(
                'CustomAttribute field_key already exists in collection: '
                . $attr->getFieldKey()
            );
        }
        $this->attributes[$attr->getFieldKey()] = $attr;
        return $this;
    }

    /**
     * @param string $fieldKey
     * @return bool
     */
    public function has($fieldKey)
    {
        return isset($this->attributes[(string) $fieldKey]);
    }

    /**
     * @param string $fieldKey
     * @return XFE_Carrier_Domain_CustomAttribute|null
     */
    public function get($fieldKey)
    {
        return isset($this->attributes[(string) $fieldKey])
            ? $this->attributes[(string) $fieldKey]
            : null;
    }

    /**
     * @param string $fieldKey
     * @return $this
     */
    public function remove($fieldKey)
    {
        unset($this->attributes[(string) $fieldKey]);
        return $this;
    }

    /**
     * @return string[]
     */
    public function getKeys()
    {
        return array_keys($this->attributes);
    }

    /**
     * 返回所有 is_required=true 的 field_key 列表。
     *
     * @return string[]
     */
    public function getRequiredKeys()
    {
        $out = array();
        foreach ($this->attributes as $attr) {
            if ($attr->isRequired()) {
                $out[] = $attr->getFieldKey();
            }
        }
        return $out;
    }

    /**
     * 仅返回 is_active=true 的子集(返回新集合,不影响原集合)。
     *
     * @return XFE_Carrier_Domain_CustomAttributeCollection
     */
    public function filterActive()
    {
        $coll = new self();
        foreach ($this->attributes as $attr) {
            if ($attr->isActive()) {
                $coll->add($attr);
            }
        }
        return $coll;
    }

    /**
     * 返回 [field_key => CustomAttribute] 形态。
     *
     * @return array
     */
    public function toArray()
    {
        return $this->attributes;
    }

    /**
     * 返回 [field_key => {label,type,options,value}] 形态,
     * 供 CustomFieldCollection::fromArray 复用 coerce 逻辑。
     *
     * @return array
     */
    public function toCustomFieldArray()
    {
        $out = array();
        foreach ($this->attributes as $k => $attr) {
            $out[$k] = $attr->toCustomFieldArray();
        }
        return $out;
    }

    /**
     * 由 DB 行 array list 构建集合。**只接受 is_active=1**——Service 层
     * 在调用前应用 SQL 过滤。遇到非法行(spec 字段缺失或类型错)直接跳过。
     *
     * @param array $rows
     * @return XFE_Carrier_Domain_CustomAttributeCollection
     */
    public static function fromArray(array $rows)
    {
        $coll = new self();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $entityType = isset($row['entity_type']) ? $row['entity_type'] : null;
            $fieldKey   = isset($row['field_key'])   ? $row['field_key']   : null;
            $label      = isset($row['label'])       ? $row['label']       : null;
            $fieldType  = isset($row['field_type'])  ? $row['field_type']  : null;
            if ($entityType === null || $fieldKey === null || $label === null || $fieldType === null) {
                continue;
            }
            $options       = isset($row['options_csv']) && $row['options_csv'] !== ''
                ? array_map('trim', explode(',', (string) $row['options_csv']))
                : null;
            $defaultRaw    = isset($row['default_value']) ? $row['default_value'] : null;
            $defaultValue  = $defaultRaw === null || $defaultRaw === ''
                ? null
                : json_decode((string) $defaultRaw, true);
            $isRequired    = !empty($row['is_required']);
            $isActive      = !empty($row['is_active']);
            $sortOrder     = isset($row['sort_order']) ? (int) $row['sort_order'] : 0;
            $description   = isset($row['description']) ? (string) $row['description'] : null;
            $id            = isset($row['id']) ? (int) $row['id'] : null;
            try {
                $coll->add(new XFE_Carrier_Domain_CustomAttribute(
                    $id, $entityType, $fieldKey, $label, $fieldType,
                    $options, $defaultValue, $isRequired, $isActive,
                    $sortOrder, $description
                ));
            } catch (InvalidArgumentException $e) {
                // 跳过非法行
                continue;
            }
        }
        return $coll;
    }

    /**
     * IteratorAggregate:让 foreach 直接遍历 CustomAttribute 对象。
     *
     * 排序:按 sort_order 升序,相同则按 field_key 字母序。
     *
     * @return ArrayIterator
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        $arr = array_values($this->attributes);
        usort($arr, function (XFE_Carrier_Domain_CustomAttribute $a, XFE_Carrier_Domain_CustomAttribute $b) {
            $soCmp = $a->getSortOrder() - $b->getSortOrder();
            if ($soCmp !== 0) {
                return $soCmp;
            }
            return strcmp($a->getFieldKey(), $b->getFieldKey());
        });
        return new ArrayIterator($arr);
    }

    /**
     * Countable.
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return count($this->attributes);
    }
}
