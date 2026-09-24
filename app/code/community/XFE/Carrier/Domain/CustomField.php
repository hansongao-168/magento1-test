<?php
/**
 * 承运商账号自定义字段(L1 Domain 值对象)
 *
 * 表示一个键值对形式的扩展字段,带 label / type / value / options 元数据。
 * 本类**不引用任何 Mage_* 类**,可以独立单元测试。
 *
 * 关键不变量(由构造函数强制):
 *   1. key 合法:`/^[a-z0-9_]{1,64}$/`
 *   2. type ∈ {text, number, select, multiselect, boolean}
 *   3. type=select 时 options 非空
 *   4. type=multiselect 时 options 可选(空 = 自由标签输入,非空 = 强制从列表选)
 *   5. value 会被强制为对应 PHP 标量类型(multiselect 为 string[])
 *
 * 序列化形态(由 toArray() 给出):
 *   [
 *     'label'   => string,
 *     'type'    => 'text'|'number'|'select'|'multiselect'|'boolean',
 *     'value'   => string|int|float|bool|null|string[],
 *     'options' => string[]   // select / multiselect
 *   ]
 *
 * 关联文档:
 *   - docs/architecture/carrier-account-custom-fields.md
 *   - docs/architecture/decisions/0004-carrier-account-custom-fields-json.md
 *   - docs/architecture/decisions/0005-custom-field-multiselect.md
 */
final class XFE_Carrier_Domain_CustomField
{
    const TYPE_TEXT        = 'text';
    const TYPE_NUMBER      = 'number';
    const TYPE_SELECT      = 'select';
    const TYPE_MULTISELECT = 'multiselect';
    const TYPE_BOOLEAN     = 'boolean';

    const ALLOWED_TYPES = array(
        self::TYPE_TEXT,
        self::TYPE_NUMBER,
        self::TYPE_SELECT,
        self::TYPE_MULTISELECT,
        self::TYPE_BOOLEAN,
    );

    /** @var string */
    private $key;

    /** @var string */
    private $label;

    /** @var string */
    private $type;

    /** @var string|int|float|bool|null */
    private $value;

    /** @var string[]|null */
    private $options;

    /**
     * @param string          $key     字段键(英文/数字/下划线,1~64 字符)
     * @param string          $label   展示名(1~64 字符)
     * @param string          $type    text|number|select|multiselect|boolean
     * @param mixed           $value   字段值(multiselect 时为 string[] / 逗号分隔字符串)
     * @param string[]|null   $options 仅 type=select 必填;multiselect 可选
     *
     * @throws InvalidArgumentException 当 key / label / type / options 非法时
     */
    public function __construct($key, $label, $type, $value = null, ?array $options = null)
    {
        $key   = (string) $key;
        $label = (string) $label;
        $type  = (string) $type;

        if (!preg_match('/^[a-z0-9_]{1,64}$/', $key)) {
            throw new InvalidArgumentException(
                "CustomField key must match /^[a-z0-9_]{1,64}$/, got: '$key'"
            );
        }
        if ($label === '' || mb_strlen($label) > 64) {
            throw new InvalidArgumentException(
                'CustomField label must be 1~64 characters'
            );
        }
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException(
                "CustomField type must be one of "
                . implode(',', self::ALLOWED_TYPES) . ", got: '$type'"
            );
        }

        if ($type === self::TYPE_SELECT) {
            if ($options === null || count($options) === 0) {
                throw new InvalidArgumentException(
                    "CustomField type=select requires non-empty options"
                );
            }
            $options = array_values(array_map('strval', $options));
        } elseif ($type === self::TYPE_MULTISELECT) {
            // multiselect 的 options 是可选的:空 = 自由标签输入;非空 = 强制从列表选
            if ($options === null) {
                $options = array();
            } else {
                $options = array_values(array_map('strval', $options));
            }
        } else {
            $options = null;
        }

        $this->key     = $key;
        $this->label   = $label;
        $this->type    = $type;
        $this->options = $options;
        $this->value   = $this->coerceValue($value, $type, $options);
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string|int|float|bool|null
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string[]|null
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * 返回一个 value 被替换、其余元数据不变的新实例。
     *
     * @param mixed $newValue
     * @return XFE_Carrier_Domain_CustomField
     */
    public function withValue($newValue)
    {
        return new self(
            $this->key,
            $this->label,
            $this->type,
            $newValue,
            $this->options
        );
    }

    /**
     * 输出可被 json_encode 序列化的形态。
     *
     * @return array
     */
    public function toArray()
    {
        $out = array(
            'label' => $this->label,
            'type'  => $this->type,
            'value' => $this->value,
        );
        if ($this->type === self::TYPE_SELECT || $this->type === self::TYPE_MULTISELECT) {
            $out['options'] = $this->options;
        }
        return $out;
    }

    /**
     * 类型强转:type→value 的实际 PHP 标量。
     *
     * @param mixed         $value
     * @param string        $type
     * @param string[]|null $options
     * @return string|int|float|bool|null
     *
     * @throws InvalidArgumentException 当 type=select 的 value 不在 options 中
     */
    private function coerceValue($value, $type, ?array $options = null)
    {
        switch ($type) {
            case self::TYPE_TEXT:
                if ($value === null) {
                    return '';
                }
                return (string) $value;

            case self::TYPE_NUMBER:
                if ($value === null || $value === '') {
                    return null;
                }
                if (is_numeric($value)) {
                    // 整数优先,否则用 float。注意:用弱比较 == (而非 ===)
                    // 兼容 JSON 解码后是 int / float / string 三种形态。
                    return (int) $value == $value
                        ? (int) $value
                        : (float) $value;
                }
                return null;

            case self::TYPE_BOOLEAN:
                if (is_bool($value)) {
                    return $value;
                }
                if ($value === 1 || $value === '1') {
                    return true;
                }
                if ($value === 0 || $value === '0' || $value === '' || $value === null) {
                    return false;
                }
                return (bool) $value;

            case self::TYPE_SELECT:
                $strVal = (string) $value;
                if (!in_array($strVal, $options, true)) {
                    throw new InvalidArgumentException(
                        "CustomField select value '$strVal' is not in options: "
                        . implode(',', $options)
                    );
                }
                return $strVal;

            case self::TYPE_MULTISELECT:
                // 接受:array / 逗号分隔字符串 / null。每项 trim + 去空 + 去重。
                // 当 options 非空时,value 必须在 options 之内(options 空 = 自由输入)。
                if ($value === null || $value === '') {
                    return array();
                }
                if (is_string($value)) {
                    $value = explode(',', $value);
                } elseif (!is_array($value)) {
                    return array();
                }
                $arr = array_values(array_unique(array_filter(
                    array_map('trim', array_map('strval', $value)),
                    function ($v) { return $v !== ''; }
                )));
                if (count($options) > 0) {
                    foreach ($arr as $v) {
                        if (!in_array($v, $options, true)) {
                            throw new InvalidArgumentException(
                                "CustomField multiselect value '$v' is not in options: "
                                . implode(',', $options)
                            );
                        }
                    }
                }
                return $arr;
        }
        return null;
    }
}
