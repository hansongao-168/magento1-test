<?php
/**
 * 自定义字段集合(L1 Domain 值对象)
 *
 * 保证同一账号内 key 唯一(由 add() 强制)。提供"按 key 取值"和"toArray/fromArray"
 * 用于 JSON 序列化。
 *
 * 本类**不引用任何 Mage_* 类**。
 *
 * 关联文档: docs/architecture/carrier-account-custom-fields.md
 */
final class XFE_Carrier_Domain_CustomFieldCollection implements IteratorAggregate, Countable
{
    /** @var XFE_Carrier_Domain_CustomField[] */
    private $fields = array();

    /**
     * @param XFE_Carrier_Domain_CustomField $field
     * @return $this
     * @throws DomainException 当 key 重复时
     */
    public function add(XFE_Carrier_Domain_CustomField $field)
    {
        if (isset($this->fields[$field->getKey()])) {
            throw new DomainException(
                'CustomField key already exists in collection: '
                . $field->getKey()
            );
        }
        $this->fields[$field->getKey()] = $field;
        return $this;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return isset($this->fields[(string) $key]);
    }

    /**
     * @param string $key
     * @return XFE_Carrier_Domain_CustomField|null
     */
    public function get($key)
    {
        return isset($this->fields[(string) $key])
            ? $this->fields[(string) $key]
            : null;
    }

    /**
     * @param string $key
     * @return $this
     */
    public function remove($key)
    {
        unset($this->fields[(string) $key]);
        return $this;
    }

    /**
     * @return string[]
     */
    public function getKeys()
    {
        return array_keys($this->fields);
    }

    /**
     * 序列化为 [key => {label,type,value,options?}] 形态。
     *
     * @return array
     */
    public function toArray()
    {
        $out = array();
        foreach ($this->fields as $k => $f) {
            $out[$k] = $f->toArray();
        }
        return $out;
    }

    /**
     * 由 [key => {label,type,value,options?}] 形态构建集合。
     * 不抛异常:遇到非法 spec 时**跳过**(允许 import 时跳过坏行)。
     *
     * 严格规则:
     *   - spec 必须是关联数组(至少有 'type' 或 'label' 字段),否则视为 list
     *     形态丢弃。
     *   - spec['type'] 必须在 ALLOWED_TYPES 中,否则丢弃。
     *   - spec['key'] 字段(若存在)只用于提示,不参与字段 key(以数组外层 key 为准)。
     *
     * @param array $data
     * @return XFE_Carrier_Domain_CustomFieldCollection
     */
    public static function fromArray(array $data)
    {
        $coll = new self();
        foreach ($data as $key => $spec) {
            if (!is_string($key) || !is_array($spec)) {
                continue;
            }
            // 拒绝 list 形态(整个 spec 形如 ['a','b'] 而不是 assoc 数组)
            $hasShape = isset($spec['type']) || isset($spec['label']) || isset($spec['value']);
            if (!$hasShape) {
                continue;
            }
            $type    = isset($spec['type'])    ? $spec['type']    : 'text';
            $label   = isset($spec['label'])   ? $spec['label']   : $key;
            $value   = isset($spec['value'])   ? $spec['value']   : null;
            $options = null;
            if (($type === XFE_Carrier_Domain_CustomField::TYPE_SELECT
                    || $type === XFE_Carrier_Domain_CustomField::TYPE_MULTISELECT)
                && isset($spec['options'])
                && is_array($spec['options'])
            ) {
                $options = array_values(array_map('strval', $spec['options']));
            }
            try {
                $coll->add(new XFE_Carrier_Domain_CustomField(
                    $key, $label, $type, $value, $options
                ));
            } catch (InvalidArgumentException $e) {
                // 跳过非法 spec
                continue;
            }
        }
        return $coll;
    }

    /**
     * IteratorAggregate:让 foreach 直接遍历字段对象。
     *
     * @return ArrayIterator
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new ArrayIterator(array_values($this->fields));
    }

    /**
     * Countable.
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return count($this->fields);
    }
}
