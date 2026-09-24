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
 *   6. field_type=boolean 时 options 必须正好 2 行,key ∈ {0,1}(label 可改,小改 K,ADR 0023)
 *   7. default_value 会被强制为对应 PHP 标量类型
 *   8. label 1~64 字符
 *
 * 序列化形态(由 toCustomFieldArray() 给出):
 *   [
 *     'label'   => string,
 *     'type'    => 'text'|'number'|'select'|'multiselect'|'boolean',
 *     'options' => string[],   // select/multiselect/boolean — 仅 key 喂给 CustomField
 *     'value'   => scalar|string[]|null
 *   ]
 *
 * options 内部结构(由 getOptions() 给出,小改 K,ADR 0023):
 *   [{key: string, label: string}, ...]
 *
 * boolean 专属 label 映射(由 getBooleanLabels() 给出,缺记录回退 {0:否, 1:是}):
 *   [0: string, 1: string]
 *
 * 关联文档:
 *   - docs/architecture/carrier-global-custom-field-defs.md
 *   - docs/architecture/decisions/0006-custom-attribute-management.md
 *   - docs/architecture/decisions/0023-boolean-custom-label.md
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

    /**
     * 允许的 locale 白名单(小改 P,ADR 0027)。
     *
     * 行编辑器按此列表生成 tab;渲染时按当前 store locale 选 label,
     * 找不到再回退 labels.default。
     * 新增语言时在此追加常量 + 行编辑器文案注入即可,无需改 schema。
     *
     * 'default' 是兜底(必须保留),用于未指定 locale 或缺翻译时回退。
     */
    const ALLOWED_LOCALES = array(
        'default',  // 兜底(必须保留)
        'zh_CN',    // 简体中文
        'en_US',    // 英文(美国)
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

    /**
     * options 内部结构(小改 K,ADR 0023):
     *   [{key: string, label: string}, ...]
     *
     * @var array<int,array{key:string,label:string}>|null
     */
    private $options;

    /**
     * options 多语言 JSON 字符串原文(小改 P,ADR 0027)。
     * 解析后形态(对应 localizedOptions 字段):
     *   [{key: string, labels: {locale: string}}, ...]
     *
     * 与 $options 字段的关系:
     *   - $options     = [{key, label}] (单 label,小改 K 兼容路径)
     *   - $localizedOptions = [{key, labels: {locale: ...}}] (多语言,新路径)
     *   - $options 的 label 字段 = $localizedOptions 的 labels.default
     *
     * @var string|null
     */
    private $optionsJson;

    /**
     * options 多语言解析缓存(小改 P,ADR 0027)。
     *
     * 形态: [{key: string, labels: array<locale, label>}]
     * - 顺序与 JSON 字符串一致
     * - labels 至少含 'default' 键(缺失时回填空字符串)
     *
     * @var array<int,array{key:string,labels:array<string,string>}>|null
     */
    private $localizedOptions;


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
     * @param int|null              $id
     * @param string                $entityType   4 选 1
     * @param string                $fieldKey     1~64 字符
     * @param string                $label        1~64 字符
     * @param string                $fieldType
     * @param array|string[]|null   $options      select 必填;multiselect 可选;boolean 可选
     * @param mixed                 $defaultValue
     * @param bool                  $isRequired
     * @param bool                  $isActive
     * @param int                   $sortOrder
     * @param string|null           $description
     *
     * @param string|null           $optionsJson 多语言 JSON 字符串原文(小改 P,ADR 0027);为 null 时回退 $options 单 label
     * @throws InvalidArgumentException
     */
    public function __construct(
        $id,
        $entityType,
        $fieldKey,
        $label,
        $fieldType,
        $options = null,
        $defaultValue = null,
        $isRequired = false,
        $isActive = true,
        $sortOrder = 0,
        $description = null,
        $optionsJson = null
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

        // options 校验 — 内部升级为结构化 [{key, label}, ...](小改 K,ADR 0023)
        if ($fieldType === XFE_Carrier_Domain_CustomField::TYPE_SELECT) {
            if ($options === null || count($options) === 0) {
                throw new InvalidArgumentException(
                    "CustomAttribute field_type=select requires non-empty options"
                );
            }
            $options = self::normalizeOptionsList($options);
        } elseif ($fieldType === XFE_Carrier_Domain_CustomField::TYPE_MULTISELECT) {
            $options = $options === null
                ? array()
                : self::normalizeOptionsList($options);
        } elseif ($fieldType === XFE_Carrier_Domain_CustomField::TYPE_BOOLEAN) {
            // boolean:可选 options;空时默认 {0:否,1:是};非空时必须正好 2 行 + key ∈ {0,1}
            $options = ($options === null || count($options) === 0)
                ? array(
                    array('key' => '0', 'label' => '否'),
                    array('key' => '1', 'label' => '是'),
                )
                : self::normalizeOptionsList($options);
            if (count($options) !== 2) {
                throw new InvalidArgumentException(
                    'CustomAttribute field_type=boolean options must contain exactly 2 rows, got: '
                    . count($options)
                );
            }
            $keys = array_map(
                function ($p) { return (string) $p['key']; },
                $options
            );
            sort($keys);
            if ($keys !== array('0', '1')) {
                throw new InvalidArgumentException(
                    'CustomAttribute field_type=boolean options keys must be exactly 0 and 1, got: ['
                    . implode(',', $keys) . ']'
                );
            }
        } else {
            $options = null;
        }


        // 多语言 JSON 解析(小改 P,ADR 0027)
        // 优先级:optionsJson 字符串 > options 单 label 数组 > null
        $localized = null;
        if (is_string($optionsJson) && $optionsJson !== '') {
            $decoded = json_decode($optionsJson, true);
            if (is_array($decoded)) {
                // 校验 + 规范化 JSON 为结构化 [{key,labels:{locale:str}},...]
                $localized = self::parseOptionsJsonToLocalizedPairs($decoded, $fieldType);
            }
        }
        if ($localized === null && $options !== null) {
            // 旧 options 单 label 数组:全部走 default locale,保证 getLocalizedOptions() 可用
            $localized = array();
            foreach ($options as $pair) {
                $localized[] = array(
                    'key'    => $pair['key'],
                    'labels' => array('default' => (string) $pair['label']),
                );
            }
        }

        // 仅传 keys 给 coerceDefaultValue — CustomField 语义本身只关心 key 集合(小改 K)
        $optionKeys = null;
        if ($options !== null) {
            $optionKeys = array_map(
                function ($p) { return $p['key']; },
                $options
            );
        }
        $coercedDefault = $this->coerceDefaultValue($defaultValue, $fieldType, $optionKeys);

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
        $this->optionsJson      = is_string($optionsJson) ? (string) $optionsJson : null;
        $this->localizedOptions = $localized;
    }

    /**
     * 把外部传入的 options 列表归一化为内部结构 [{key:string, label:string}, ...]。
     *
     * @param mixed $options
     * @return array<int,array{key:string,label:string}>
     */
    public static function normalizeOptionsList($options)
    {
        if ($options === null) {
            return array();
        }
        $out = array();
        foreach ($options as $idx => $item) {
            if (is_string($item)) {
                // 小改 K:string token 当作 'key|label' 解析(支持 Importer/Service 传 string[] 形态)
                $parsed = self::parseOptionToken($item);
                $key   = $parsed['key'];
                $label = $parsed['label'];
            } elseif (is_array($item)) {
                if (!isset($item['key']) && !isset($item['value'])) {
                    throw new InvalidArgumentException(
                        'CustomAttribute option item requires key/value at index '
                        . (is_int($idx) ? $idx : '?')
                    );
                }
                $key = (string) (isset($item['key']) ? $item['key'] : $item['value']);
                if (isset($item['label'])) {
                    $label = (string) $item['label'];
                } elseif (isset($item['value'])) {
                    $label = (string) $item['value'];
                } else {
                    $label = $key;
                }
            } else {
                throw new InvalidArgumentException(
                    'CustomAttribute option item must be string or array at index '
                    . (is_int($idx) ? $idx : '?')
                );
            }
            $key = trim($key);
            $label = trim($label);
            if ($key === '') {
                throw new InvalidArgumentException(
                    'CustomAttribute option key must be non-empty at index '
                    . (is_int($idx) ? $idx : '?')
                );
            }
            $out[] = array('key' => $key, 'label' => $label);
        }
        return $out;
    }


    /**
     * 把外部传入的 labels map 归一化为内部结构 {locale:label}(小改 P,ADR 0027)。
     *
     * 规则:
     *   - 必须是 array<string,string> 形态(键=locale,值=label)
     *   - 不识别的 locale(不在 ALLOWED_LOCALES 内)直接丢弃,防止脏数据
     *   - 至少要包含 'default' 键;缺失则自动补空字符串
     *   - locale 顺序固定:ALLOWED_LOCALES 优先,然后 labels 里出现的其他 locale(防止顺序乱跳)
     *
     * @param mixed $labels
     * @return array<string,string>
     */
    public static function normalizeLabelsMap($labels)
    {
        $out = array('default' => '');
        if (!is_array($labels)) {
            return $out;
        }
        foreach ($labels as $locale => $label) {
            if (!is_string($locale) || !in_array($locale, self::ALLOWED_LOCALES, true)) {
                continue;  // 不识别的 locale,丢弃
            }
            $out[$locale] = trim((string) $label);
        }
        // 保证 default 存在(若用户没填)
        if (!isset($out['default']) || $out['default'] === '') {
            // 找一个非空 locale 作为 default 兜底
            foreach (self::ALLOWED_LOCALES as $loc) {
                if (isset($out[$loc]) && $out[$loc] !== '') {
                    $out['default'] = $out[$loc];
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * 把 JSON 解码后的 options 数组(可能包含 labels map)解析为结构化对(小改 P,ADR 0027)。
     *
     * 输入形态(由 json_decode 第二参=true 得出):
     *   array<int, array{key:string, labels?:array<string,string>}>
     *
     * 校验:
     *   - 必含 key 字段(字符串)
     *   - 可选 labels 字段(若给,走 normalizeLabelsMap 规范化)
     *   - boolean 类型必须正好 2 行 + key ∈ {0,1}
     *   - select 类型必须非空
     *
     * @param mixed $decoded
     * @param string $fieldType
     * @return array<int,array{key:string,labels:array<string,string>}>
     * @throws InvalidArgumentException
     */
    public static function parseOptionsJsonToLocalizedPairs($decoded, $fieldType)
    {
        if (!is_array($decoded)) {
            throw new InvalidArgumentException(
                'CustomAttribute options_json must be a JSON array of objects'
            );
        }
        $out = array();
        foreach ($decoded as $idx => $item) {
            if (!is_array($item) || !isset($item['key'])) {
                throw new InvalidArgumentException(
                    'CustomAttribute options_json item requires key at index '
                    . (is_int($idx) ? $idx : '?')
                );
            }
            $key = trim((string) $item['key']);
            if ($key === '') {
                throw new InvalidArgumentException(
                    'CustomAttribute options_json key must be non-empty at index '
                    . (is_int($idx) ? $idx : '?')
                );
            }
            $labels = isset($item['labels']) ? $item['labels'] : array();
            // 兼容 {value:'xxx'} 旧结构(label 字段名兼容)
            if (!is_array($labels) && isset($item['label'])) {
                $labels = array('default' => (string) $item['label']);
            }
            $out[] = array(
                'key'    => $key,
                'labels' => self::normalizeLabelsMap($labels),
            );
        }
        // 跟 options 走同样的强校验(boolean / select)
        if ($fieldType === XFE_Carrier_Domain_CustomField::TYPE_SELECT && count($out) === 0) {
            throw new InvalidArgumentException(
                'CustomAttribute field_type=select requires non-empty options_json'
            );
        }
        if ($fieldType === XFE_Carrier_Domain_CustomField::TYPE_BOOLEAN) {
            if (count($out) === 0) {
                // 默认 {0:否,1:是}
                $out = array(
                    array('key' => '0', 'labels' => array('default' => '否', 'zh_CN' => '否', 'en_US' => 'No')),
                    array('key' => '1', 'labels' => array('default' => '是', 'zh_CN' => '是', 'en_US' => 'Yes')),
                );
            } elseif (count($out) !== 2) {
                throw new InvalidArgumentException(
                    'CustomAttribute field_type=boolean options_json must contain exactly 2 rows, got: '
                    . count($out)
                );
            } else {
                $keys = array_map(function ($p) { return (string) $p['key']; }, $out);
                sort($keys);
                if ($keys !== array('0', '1')) {
                    throw new InvalidArgumentException(
                        'CustomAttribute field_type=boolean options_json keys must be exactly 0 and 1, got: ['
                        . implode(',', $keys) . ']'
                    );
                }
            }
        }
        return $out;
    }

    /**
     * 把结构化 [{key,labels:{...}},...] 序列化为 JSON 字符串(小改 P,ADR 0027)。
     *
     * 空数组(null / [])时返回 null,避免数据库写入冗余字符串。
     *
     * @param array<int,array{key:string,labels:array<string,string>}>|null $pairs
     * @return string|null
     */
    public static function serializeLocalizedPairsToJson($pairs)
    {
        if (!is_array($pairs) || count($pairs) === 0) {
            return null;
        }
        // 重新组装成稳定形态:每个 entry 仅含 key + labels,labels 仅含 ALLOWED_LOCALES 内 locale
        $cleaned = array();
        foreach ($pairs as $pair) {
            if (!is_array($pair) || !isset($pair['key'])) {
                continue;
            }
            $cleaned[] = array(
                'key'    => (string) $pair['key'],
                'labels' => self::normalizeLabelsMap(
                    isset($pair['labels']) ? $pair['labels'] : array()
                ),
            );
        }
        if (count($cleaned) === 0) {
            return null;
        }
        return json_encode(
            $cleaned,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * 把单个 string token 解析为 {key, label}(小改 K,与 JS parseOptionToken 对齐)。
     *
     * @param mixed $s
     * @return array{key:string,label:string}
     */
    public static function parseOptionToken($s)
    {
        if (!is_string($s)) { return array('key' => '', 'label' => ''); }
        $t = trim($s);
        if ($t === '') { return array('key' => '', 'label' => ''); }
        $pipeIdx = strpos($t, '|');
        if ($pipeIdx === false) {
            return array('key' => $t, 'label' => $t);
        }
        $key = trim(substr($t, 0, $pipeIdx));
        $label = trim(substr($t, $pipeIdx + 1));
        if ($key === '') {
            return array('key' => $t, 'label' => $t);
        }
        return array('key' => $key, 'label' => $label === '' ? $key : $label);
    }

    /**
     * 把存储形态的 options_csv 解析为结构化对。
     *
     * @param string $csv
     * @return array<int,array{key:string,label:string}>
     */
    public static function parseOptionsCsvToPairs($csv)
    {
        if (!is_string($csv) || $csv === '') {
            return array();
        }
        $out = array();
        $tokens = explode(',', $csv);
        foreach ($tokens as $token) {
            $t = trim($token);
            if ($t === '') { continue; }
            $pair = self::parseOptionToken($t);
            if ($pair['key'] === '') { continue; }
            $out[] = $pair;
        }
        return $out;
    }

    /**
     * 把结构化 options 对序列化为 options_csv 字符串。
     *
     * @param array<int,array{key:string,label:string}>|null $pairs
     * @return string
     */
    public static function serializeOptionsPairsToCsv($pairs)
    {
        if ($pairs === null || count($pairs) === 0) {
            return '';
        }
        $parts = array();
        foreach ($pairs as $pair) {
            if (!is_array($pair) || !isset($pair['key'])) { continue; }
            $key = trim((string) $pair['key']);
            if ($key === '') { continue; }
            $label = isset($pair['label']) ? trim((string) $pair['label']) : '';
            if ($label === '' || $label === $key) {
                $parts[] = $key;
            } else {
                $parts[] = $key . '|' . $label;
            }
        }
        return implode(',', $parts);
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

    /**
     * 返回结构化 options(小改 K,ADR 0023)。
     *
     * @return array<int,array{key:string,label:string}>|null
     */
    public function getOptions()      { return $this->options; }

    /**
     * 返回 options 的 key 列表(供 CustomField 构造使用,小改 K)。
     *
     * @return string[]
     */
    public function getOptionKeys()
    {
        if ($this->options === null) {
            return array();
        }
        $keys = array();
        foreach ($this->options as $pair) {
            $keys[] = $pair['key'];
        }
        return $keys;
    }

    /**
     * 返回 boolean 类型的 label 映射(小改 K,ADR 0023)。
     *
     * @return array{0:string,1:string}
     */
    public function getBooleanLabels()
    {
        $map = array('0' => '否', '1' => '是');
        if ($this->options === null) {
            return $map;
        }
        foreach ($this->options as $pair) {
            if ($pair['key'] === '0' && $pair['label'] !== '') {
                $map['0'] = $pair['label'];
            } elseif ($pair['key'] === '1' && $pair['label'] !== '') {
                $map['1'] = $pair['label'];
            }
        }
        return $map;
    }

    /**
     * 返回 options 多语言 JSON 字符串原文(小改 P,ADR 0027)。
     *
     * 与 getOptions() 的区别:
     *   - getOptions()       返回 [{key,label}] (单 label,小改 K 路径)
     *   - getOptionsJson()   返回数据库原始 JSON(可能含多语言)
     *
     * @return string|null
     */
    public function getOptionsJson()
    {
        return $this->optionsJson;
    }

    /**
     * 返回允许的 locale 列表(小改 P,ADR 0027)。
     *
     * 与 ALLOWED_LOCALES 同步,但用 instance method 暴露方便 service / template 调。
     *
     * @return string[]
     */
    public function getLocales()
    {
        return self::ALLOWED_LOCALES;
    }

    /**
     * 返回按指定 locale 选过的 options 结构化数组(小改 P,ADR 0027)。
     *
     * 形态: [{key:string, label:string}, ...]
     * - label 从 localizedOptions[].labels[$locale] 取
     * - 找不到该 locale 时回退 labels.default(再空才用 key)
     *
     * @param string $locale  形如 'zh_CN' / 'en_US' / 'default'
     * @return array<int,array{key:string,label:string}>|null
     */
    public function getLocalizedOptions($locale)
    {
        if ($this->localizedOptions === null) {
            return null;
        }
        $locale = is_string($locale) && $locale !== '' ? $locale : 'default';
        $out = array();
        foreach ($this->localizedOptions as $pair) {
            $label = '';
            if (isset($pair['labels'][$locale]) && $pair['labels'][$locale] !== '') {
                $label = $pair['labels'][$locale];
            } elseif (isset($pair['labels']['default']) && $pair['labels']['default'] !== '') {
                $label = $pair['labels']['default'];
            } else {
                $label = (string) $pair['key'];  // 终极兜底:用 key
            }
            $out[] = array(
                'key'   => (string) $pair['key'],
                'label' => $label,
            );
        }
        return $out;
    }

    /**
     * 返回按指定 locale 选过的 boolean label 映射(小改 P,ADR 0027)。
     *
     * @param string $locale  形如 'zh_CN' / 'en_US' / 'default'
     * @return array{0:string,1:string}
     */
    public function getLocalizedBooleanLabels($locale)
    {
        $map = array('0' => '否', '1' => '是');
        if ($this->localizedOptions === null) {
            return $map;
        }
        $locale = is_string($locale) && $locale !== '' ? $locale : 'default';
        foreach ($this->localizedOptions as $pair) {
            if ($pair['key'] !== '0' && $pair['key'] !== '1') {
                continue;
            }
            $label = '';
            if (isset($pair['labels'][$locale]) && $pair['labels'][$locale] !== '') {
                $label = $pair['labels'][$locale];
            } elseif (isset($pair['labels']['default']) && $pair['labels']['default'] !== '') {
                $label = $pair['labels']['default'];
            }
            if ($label !== '') {
                $map[$pair['key']] = $label;
            }
        }
        return $map;
    }

    /**
     * 按 locale 选单个 option 的 label(小改 P,ADR 0027)。
     *
     * @param string $key
     * @param string $locale
     * @return string|null  找不到时返回 null(不会回退 default,让调用方决策)
     */
    public function getLocalizedLabelFor($key, $locale)
    {
        if ($this->localizedOptions === null) {
            return null;
        }
        $locale = is_string($locale) && $locale !== '' ? $locale : 'default';
        foreach ($this->localizedOptions as $pair) {
            if ((string) $pair['key'] === (string) $key) {
                if (isset($pair['labels'][$locale]) && $pair['labels'][$locale] !== '') {
                    return $pair['labels'][$locale];
                }
                if (isset($pair['labels']['default']) && $pair['labels']['default'] !== '') {
                    return $pair['labels']['default'];
                }
                return null;
            }
        }
        return null;
    }


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
     * 转为 CustomField 构造签名形态。
     *
     * options 仅喂 key 列表给 CustomField;label 信息由 template 直接从 def 拿(小改 K)。
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
            || $this->fieldType === XFE_Carrier_Domain_CustomField::TYPE_BOOLEAN
        ) {
            $out['options'] = $this->getOptionKeys();
        }
        return $out;
    }

    /**
     * 类型强转(简化版,与 CustomField::coerceValue 行为一致)。
     *
     * @param mixed         $value
     * @param string        $type
     * @param string[]|null $options key 列表
     * @return string|int|float|bool|string[]|null
     */
    private function coerceDefaultValue($value, $type, $options = null)
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
