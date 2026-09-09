<?php
/**
 * 自定义属性(L1 Domain 值对象)
 *
 * 表示一条"自定义属性定义"——由系统管理员在后台登记,用于约束 4 个分类
 * (承运商 / 账号 / FTP / LOGO) 各自可以声明的字段集。
 *
 * 本类**不引用任何 Mage_* 类**,可以独立单元测试。
 *
 * 关键不变量(由构造函数强制):
 *   1. entity_type 必须在 ALLOWED_ENTITY_TYPES 内
 *   2. field_key 合法:`/^[a-z0-9_]{1,64}$/`
 *   3. field_type 复用 CustomField::ALLOWED_TYPES
 *   4. field_type=select 时 options 非空
 *   5. field_type=multiselect 时 options 可选(空=自由输入,非空=固定选项)
 *   6. default_value 会被强制为对应 PHP 标量类型
 *   7. label 1~64 字符
 *
 * 序列化形态(由 toCustomFieldArray() 给出):
 *   [
 *     'label'   => string,
 *     'type'    => 'text'|'number'|'select'|'multiselect'|'boolean',
 *     'options' => string[],   // select/multiselect
 *     'value'   => scalar|string[]|null
 *   ]
 * 复用 CustomField 构造签名:可直接喂给 CustomField::__construct / CustomFieldCollection::fromArray。
 *
 * 关联文档:
 *   - docs/architecture/carrier-global-custom-field-defs.md
 *   - docs/architecture/decisions/0006-custom-attribute-management.md
 */
final class XFE_Carrier_Domain_CustomAttribute
{
    const ENTITY_TYPE_CARRIER     = 'carrier';
    const ENTITY_TYPE_ACCOUNT     = 'account';
    const ENTITY_TYPE_FTP_ACCOUNT = 'ftp_account';
    const ENTITY_TYPE_LOGO        = 'logo';

    const ALLOWED_ENTITY_TYPES = array(
        self::ENTITY_TYPE_CARRIER,
        self::ENTITY_TYPE_ACCOUNT,
        self::ENTITY_TYPE_FTP_ACCOUNT,
        self::ENTITY_TYPE_LOGO,
    );

    /** @var int|null */
    private $id;

    /** @var string */
    private $entityType;

    /** @var string */
    private $fieldKey;

    /** @var string */
    private $label;

    /** @var string */
    private $fieldType;

    /** @var string[]|null */
    private $options;

    /** @var string|int|float|bool|string[]|null */
    private $defaultValue;

    /** @var bool */
    private $isRequired;

    /** @var bool */
    private $isActive;

    /** @var int */
    private $sortOrder;

    /** @var string|null */
    private $description;

    /**
     * @param int|null     $id           数据库自增 ID(新建时可为 null)
     * @param string       $entityType   4 选 1: carrier | account | ftp_account | logo
     * @param string       $fieldKey     英文/数字/下划线,1~64 字符
     * @param string       $label        展示名,1~64 字符
     * @param string       $fieldType    复用 CustomField::TYPE_*
     * @param string[]|null $options     仅 select 必填;multiselect 可选
     * @param mixed        $defaultValue scalar | string[] | null(由 fieldType 决定)
     * @param bool         $isRequired
     * @param bool         $isActive
     * @param int          $sortOrder
     * @param string|null  $description
     *
     * @throws InvalidArgumentException 当 entity_type / field_key / label / field_type / options / defaultValue 非法时
     */
    public function __construct(
        $id,
        $entityType,
        $fieldKey,
        $label,
        $fieldType,
        ?array $options = null,
        $defaultValue = null,
        $isRequired = false,
        $isActive = true,
        $sortOrder = 0,
        $description = null
    ) {
        $entityType = (string) $entityType;
        $fieldKey   = (string) $fieldKey;
        $label      = (string) $label;
        $fieldType  = (string) $fieldType;

        if (!in_array($entityType, self::ALLOWED_ENTITY_TYPES, true)) {
            throw new InvalidArgumentException(
                "CustomAttribute entity_type must be one of "
                . implode(',', self::ALLOWED_ENTITY_TYPES) . ", got: '$entityType'"
            );
        }
        if (!preg_match('/^[a-z0-9_]{1,64}$/', $fieldKey)) {
            throw new InvalidArgumentException(
                "CustomAttribute field_key must match /^[a-z0-9_]{1,64}$/, got: '$fieldKey'"
            );
        }
        if ($label === '' || mb_strlen($label) > 64) {
            throw new InvalidArgumentException(
                'CustomAttribute label must be 1~64 characters'
            );
        }
        if (!in_array($fieldType, XFE_Carrier_Domain_CustomField::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException(
                "CustomAttribute field_type must be one of "
                . implode(',', XFE_Carrier_Domain_CustomField::ALLOWED_TYPES)
                . ", got: '$fieldType'"
            );
        }

        // options 校验(规则与 CustomField::__construct 一致)
        if ($fieldType === XFE_Carrier_Domain_CustomField::TYPE_SELECT) {
            if ($options === null || count($options) === 0) {
                throw new InvalidArgumentException(
                    "CustomAttribute field_type=select requires non-empty options"
                );
            }
            $options = array_values(array_map('strval', $options));
        } elseif ($fieldType === XFE_Carrier_Domain_CustomField::TYPE_MULTISELECT) {
            $options = $options === null
                ? array()
                : array_values(array_map('strval', $options));
        } else {
            $options = null;
        }

        // defaultValue 按 fieldType coerce(复用 CustomField 私有 coerce 规则)
        // 拷贝一份精简版的 coerce 逻辑(因 CustomField::coerceValue 是 private)
        $coercedDefault = $this->coerceDefaultValue($defaultValue, $fieldType, $options);

        $this->id            = $id === null ? null : (int) $id;
        $this->entityType    = $entityType;
        $this->fieldKey      = $fieldKey;
        $this->label         = $label;
        $this->fieldType     = $fieldType;
        $this->options       = $options;
        $this->defaultValue  = $coercedDefault;
        $this->isRequired    = (bool) $isRequired;
        $this->isActive      = (bool) $isActive;
        $this->sortOrder     = (int) $sortOrder;
        $this->description   = $description === null ? null : (string) $description;
    }

    /** @return int|null */
    public function getId()           { return $this->id; }

    /** @return string */
    public function getEntityType()   { return $this->entityType; }

    /** @return string */
    public function getFieldKey()     { return $this->fieldKey; }

    /** @return string */
    public function getLabel()        { return $this->label; }

    /** @return string */
    public function getFieldType()    { return $this->fieldType; }

    /** @return string[]|null */
    public function getOptions()      { return $this->options; }

    /** @return string|int|float|bool|string[]|null */
    public function getDefaultValue() { return $this->defaultValue; }

    /** @return bool */
    public function isRequired()      { return $this->isRequired; }

    /** @return bool */
    public function isActive()        { return $this->isActive; }

    /** @return int */
    public function getSortOrder()    { return $this->sortOrder; }

    /** @return string|null */
    public function getDescription()  { return $this->description; }

    /**
     * 转为 CustomField 构造签名形态,供 per-row JSON 写入时复用 coerceValue 逻辑。
     *
     * @return array
     */
    public function toCustomFieldArray()
    {
        $out = array(
            'label' => $this->label,
            'type'  => $this->fieldType,
            'value' => $this->defaultValue,
        );
        if ($this->fieldType === XFE_Carrier_Domain_CustomField::TYPE_SELECT
            || $this->fieldType === XFE_Carrier_Domain_CustomField::TYPE_MULTISELECT
        ) {
            $out['options'] = $this->options;
        }
        return $out;
    }

    /**
     * 类型强转(简化版,与 CustomField::coerceValue 行为一致,因后者是 private)。
     *
     * @param mixed         $value
     * @param string        $type
     * @param string[]|null $options
     * @return string|int|float|bool|string[]|null
     *
     * @throws InvalidArgumentException 当 type=select 的 defaultValue 不在 options 中
     */
    private function coerceDefaultValue($value, $type, ?array $options = null)
    {
        switch ($type) {
            case XFE_Carrier_Domain_CustomField::TYPE_TEXT:
                if ($value === null || $value === '') {
                    return '';
                }
                return (string) $value;

            case XFE_Carrier_Domain_CustomField::TYPE_NUMBER:
                if ($value === null || $value === '') {
                    return null;
                }
                if (is_numeric($value)) {
                    return (int) $value == $value
                        ? (int) $value
                        : (float) $value;
                }
                return null;

            case XFE_Carrier_Domain_CustomField::TYPE_BOOLEAN:
                if (is_bool($value)) {
                    return $value;
                }
                if ($value === 1 || $value === '1' || $value === 'true') {
                    return true;
                }
                if ($value === 0 || $value === '0' || $value === '' || $value === null
                    || $value === 'false'
                ) {
                    return false;
                }
                return (bool) $value;

            case XFE_Carrier_Domain_CustomField::TYPE_SELECT:
                // 允许 null / 空 (无默认);非空则必须在 options 内
                if ($value === null || $value === '') {
                    return null;
                }
                $strVal = (string) $value;
                if (!in_array($strVal, $options, true)) {
                    throw new InvalidArgumentException(
                        "CustomAttribute default_value '$strVal' is not in options: "
                        . implode(',', $options)
                    );
                }
                return $strVal;

            case XFE_Carrier_Domain_CustomField::TYPE_MULTISELECT:
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
                                "CustomAttribute default_value '$v' is not in options: "
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
