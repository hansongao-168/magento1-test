# 承运商账号 — 自定义字段(Custom Fields)

> 主题:`XFE_Carrier` 的承运商账号(主账号 + FTP 账号)支持**键值对形式的自定义字段**,
> 整体以 JSON 存储在一行 `TEXT` 列,提供"声明 → 编辑 → 序列化 → 读取"全链路。
>
> 状态:**提案**(2026-09-02)
>
> 适用范围:`XFE_Carrier` 模块内的两个账号实体。
>
> 配套文档:
> - [`carrier-facade.md`](./carrier-facade.md) — Facade / Resolver 形态
> - [`carrier-observer-events.md`](./carrier-observer-events.md) — 事件契约
> - [`decisions/0004-carrier-account-custom-fields-json.md`](./decisions/0004-carrier-account-custom-fields-json.md) — ADR(决策记录)

---

## 1. 背景与现状

### 1.1 问题

`XFE_Carrier` 当前在 `xfe_carrier_carrier_account` 与 `xfe_carrier_carrier_ftp_account`
两张表中,**只支持固定字段**(`account_name` / `api_key` / `endpoint_url` 等)。
不同承运商(顺丰 / FedEx / DHL / EMS / GLS 等)的私有参数各不相同,目前没有扩展点:

- 一种方式是**给每家承运商都加 N 列**(`sf_param_a`、`fedex_param_x` ...),表会被严重污染。
- 另一种方式是**让业务模块自带 system config**(`accounts_json`),但这违反了 [`carrier-facade.md`](./carrier-facade.md) 的"统一挑账号"原则。

### 1.2 目标

账号具备一个**通用、自描述、可演进**的扩展字段集:

- 任意账号可以声明任意多个 `key → value`。
- 每个字段带 `label`(中文展示名)、`type`(text/number/select)、`options`(select 时的候选项)。
- 编辑页面提供可视化"键值对编辑器",增删行所见即所得。
- 数据以 **JSON** 序列化到 `custom_fields_json TEXT` 列,跟表里其他固定字段解耦。
- 读取时,业务模块通过 **Resolver / Facade** 拿到账号,再调用 `getCustomField('key')` 读单个值,
  或 `getCustomFields()` 拿整个集合。
- **不暴露为公开 API**(由用户在 `consumer_visibility` 选择 `internal_only`),
  跨模块必须走 Facade,不得直接读 raw JSON 字段。

### 1.3 与 EAV 的关系

EAV(实体-属性-值)在 Magento 中通过 5 张表实现(`*_entity` / `*_varchar` / `*_int` ...),
查询/聚合时多表 JOIN。本方案**不引入 EAV**,原因:

| 维度 | EAV | 本方案(JSON 键值) |
|------|-----|---------------------|
| 字段类型 | 多表按类型拆分 | JSON 内嵌 `type` 字段 |
| 查询能力 | 支持范围/排序 | 不支持(全表扫) |
| 演进成本 | ALTER TABLE 频繁 | ALTER TABLE 仅一次,后续通过 JSON 演进 |
| 一致性 | 弱(可以乱填 type) | 强(读时按 type 强校验) |
| 实现复杂度 | 高 | 低 |

**EAV-like 行为**指:账号**对外提供 `getCustomField($key)` 的访问器**,业务模块
不需要知道该字段是固定列还是扩展字段,读起来"和 EAV 一样"——这正是用户原话
"和 EAV 原理差不多"的诉求。

---

## 2. 设计原则

### 2.1 单向依赖

```
L4  Controller / Block                ← HTTP 入口 / 渲染
        ↓
L3  Service (XFE_Carrier_Service_CustomFieldCodec, Registry::account, ...)  ← 业务编排
        ↓
L2  Repository / Model (Carrier_Account, Carrier_FtpAccount)  ← 持久化
        ↓
L1  Domain (XFE_Carrier_Domain_CustomField, CustomFieldCollection, Codec)  ← 纯值对象
```

- L1 Domain **不引用任何 Magento 类**(不继承 `Mage_*`、不调 `Mage::`)。
- L2 Model 只在 `_afterLoad` / `_beforeSave` 钩子里调 Codec,业务不直接读写 raw JSON。
- L3 Service 是**唯一**可以同时访问 Model 与 Domain 的层,负责"DTO ↔ Model data"互转。
- L4 Controller / Block **不**自己 `json_encode` / `json_decode`,全部委托 Service。

### 2.2 模块边界

- `XFE_Carrier` 内部:Service / Model / Domain 可互相调用。
- 其他模块(例如 `XFE_NewLogistics`):只能通过
  - `Mage::getModel('xfe_carrier/credential_resolver')->resolveAccount($code, $ctx)`
    拿到账号对象,然后调 `getCustomField($key)` —— **走 Facade**;
  - 或订阅事件 `xfe_carrier_account_custom_field_changed` —— **走事件**;
  - **禁止**直接 `Mage::getResourceModel('xfe_carrier/carrier_account')->getTableName(...)`。

### 2.3 不变量

| # | 不变量 | 强制位置 |
|---|--------|----------|
| 1 | `key` 唯一(同一账号内) | `CustomFieldCollection::add()` |
| 2 | `key` 必须匹配 `/^[a-z0-9_]{1,64}$/` | `CustomField::__construct()` |
| 3 | `type ∈ {text, number, select, multiselect, boolean}` | `CustomField::__construct()` |
| 4 | `type=select` 时 `options` 非空且每项是字符串 | `CustomField::__construct()` |
| 4b | `type=multiselect` 时 `value` 是 `string[]`,`options` 可选(空 = 自由输入) | `CustomField::__construct()` + `coerceValue` |
| 5 | JSON 列允许为 `NULL` 或 `{}`(空对象) | DB 约束 + Codec |
| 6 | 不在日志/事件 payload 里写入 `value` 原文(可能含凭据) | Controller + 事件契约 |

---

## 3. 数据契约

### 3.1 数据库

在 `xfe_carrier_carrier_account` 与 `xfe_carrier_carrier_ftp_account` 两张表都
新增一列:

```sql
ALTER TABLE xfe_carrier_carrier_account
    ADD COLUMN custom_fields_json TEXT NULL DEFAULT NULL
    COMMENT 'Key-value 自定义字段,JSON 格式:{key:{label,type,value,options?},...}'
    AFTER note;

ALTER TABLE xfe_carrier_carrier_ftp_account
    ADD COLUMN custom_fields_json TEXT NULL DEFAULT NULL
    COMMENT 'Key-value 自定义字段,JSON 格式:{key:{label,type,value,options?},...}'
    AFTER note;
```

迁移脚本路径:
- `app/code/community/XFE/Carrier/sql/xfe_carrier_setup/upgrade-1.0.13-1.0.14.php`

### 3.2 JSON 形态

列存的是 `JSON_OBJECT` 或 `NULL`。空集合用 `{}` 表示,**不要**用 `[]`(键值集合语义)。

```json
{
  "warehouse_code": {
    "label":   "仓库代码",
    "type":    "text",
    "value":   "WH-001"
  },
  "max_weight_kg": {
    "label":   "最大承重(kg)",
    "type":    "number",
    "value":   20
  },
  "service_level": {
    "label":   "服务等级",
    "type":    "select",
    "options": ["standard", "express", "economy"],
    "value":   "express"
  },
  "auto_retry": {
    "label":   "失败自动重试",
    "type":    "boolean",
    "value":   true
  },
  "supported_areas": {
    "label":   "支持区域",
    "type":    "multiselect",
    "options": [],
    "value":   ["华东", "华南", "华北"]
  },
  "service_levels": {
    "label":   "服务等级",
    "type":    "multiselect",
    "options": ["standard", "express", "economy"],
    "value":   ["express"]
  }
}
```

**字段约束**:

| 字段 | 类型 | 必填 | 校验 |
|------|------|------|------|
| `key` | string | 是 | `/^[a-z0-9_]{1,64}$/` |
| `label` | string | 是 | 1~64 字符 |
| `type` | enum | 是 | `text` / `number` / `select` / `multiselect` / `boolean` |
| `value` | scalar | 是 | text→string, number→float\|int, select→string∈options, **multiselect→string[]**, boolean→bool |
| `options` | string[] | 否 | `type=select` 或 `type=multiselect` 时可选(后者空=自由标签) |

---

## 4. Domain 层

### 4.1 值对象

**文件**: `app/code/community/XFE/Carrier/Domain/CustomField.php`

```php
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

    public function __construct(
        $key, $label, $type, $value = null, array $options = null
    ) {
        $key   = (string)$key;
        $label = (string)$label;
        $type  = (string)$type;

        if (!preg_match('/^[a-z0-9_]{1,64}$/', $key)) {
            throw new InvalidArgumentException("invalid key: $key");
        }
        if ($label === '' || mb_strlen($label) > 64) {
            throw new InvalidArgumentException("invalid label");
        }
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException("invalid type: $type");
        }
        if ($type === self::TYPE_SELECT) {
            if (!$options || count($options) === 0) {
                throw new InvalidArgumentException("select requires non-empty options");
            }
            $options = array_values(array_map('strval', $options));
        } else {
            $options = null;
        }

        $this->key     = $key;
        $this->label   = $label;
        $this->type    = $type;
        $this->options = $options;
        $this->value   = $this->coerceValue($value, $type, $options);
    }

    public function getKey()     { return $this->key; }
    public function getLabel()   { return $this->label; }
    public function getType()    { return $this->type; }
    public function getValue()   { return $this->value; }
    public function getOptions() { return $this->options; }

    public function withValue($newValue)
    {
        return new self($this->key, $this->label, $this->type, $newValue, $this->options);
    }

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

    private function coerceValue($value, $type, array $options = null)
    {
        switch ($type) {
            case self::TYPE_TEXT:
                return $value === null ? '' : (string)$value;
            case self::TYPE_NUMBER:
                if ($value === null || $value === '') return null;
                return is_numeric($value) ? 0 + $value : null;
            case self::TYPE_BOOLEAN:
                if (is_bool($value))  return $value;
                if ($value === '1' || $value === 1)  return true;
                if ($value === '0' || $value === 0 || $value === '') return false;
                return (bool)$value;
            case self::TYPE_SELECT:
                $value = (string)$value;
                if (!in_array($value, $options, true)) {
                    throw new InvalidArgumentException("select value '$value' not in options");
                }
                return $value;
            case self::TYPE_MULTISELECT:
                // 接受 array / 逗号分隔字符串;每项 trim + 去空;若 options 非空则必须在其中
                if (is_string($value)) {
                    $value = $value === '' ? array() : explode(',', $value);
                } elseif (!is_array($value)) {
                    $value = array();
                }
                $arr = array_values(array_unique(array_filter(
                    array_map('trim', array_map('strval', $value)),
                    function ($v) { return $v !== ''; }
                )));
                if ($options !== null && count($options) > 0) {
                    foreach ($arr as $v) {
                        if (!in_array($v, $options, true)) {
                            throw new InvalidArgumentException(
                                "multiselect value '$v' not in options"
                            );
                        }
                    }
                }
                return $arr;
        }
        return null;
    }
}
```

**文件**: `app/code/community/XFE/Carrier/Domain/CustomFieldCollection.php`

```php
final class XFE_Carrier_Domain_CustomFieldCollection implements IteratorAggregate, Countable
{
    /** @var XFE_Carrier_Domain_CustomField[] */
    private $fields = array();

    public function add(XFE_Carrier_Domain_CustomField $field)
    {
        if (isset($this->fields[$field->getKey()])) {
            throw new DomainException("duplicate key: " . $field->getKey());
        }
        $this->fields[$field->getKey()] = $field;
        return $this;
    }

    public function has($key) { return isset($this->fields[$key]); }
    public function get($key)  { return $this->fields[$key] ?? null; }
    public function remove($key) { unset($this->fields[$key]); return $this; }

    public function toArray()   // [key => field.toArray()]
    {
        $out = array();
        foreach ($this->fields as $k => $f) { $out[$k] = $f->toArray(); }
        return $out;
    }

    public static function fromArray(array $data)
    {
        $coll = new self();
        foreach ($data as $key => $spec) {
            $coll->add(new XFE_Carrier_Domain_CustomField(
                $key,
                isset($spec['label'])   ? $spec['label']   : $key,
                isset($spec['type'])    ? $spec['type']    : 'text',
                isset($spec['value'])   ? $spec['value']   : null,
                isset($spec['options']) ? (array)$spec['options'] : null
            ));
        }
        return $coll;
    }

    public function getIterator() { return new ArrayIterator(array_values($this->fields)); }
    public function count()       { return count($this->fields); }
}
```

### 4.2 Codec

**文件**: `app/code/community/XFE/Carrier/Domain/CustomFieldCodec.php`

```php
final class XFE_Carrier_Domain_CustomFieldCodec
{
    /**
     * 字符串(TEXT 列原文) → 值对象集合。空 / 无效 → 空集合。
     */
    public static function decode($jsonString)
    {
        if ($jsonString === null || $jsonString === '') {
            return new XFE_Carrier_Domain_CustomFieldCollection();
        }
        $data = json_decode((string)$jsonString, true);
        if (!is_array($data)) {
            return new XFE_Carrier_Domain_CustomFieldCollection();
        }
        // 只取 [key => {label,type,value,options?}] 形态,过滤掉 list 形态
        $filtered = array();
        foreach ($data as $k => $v) {
            if (is_string($k) && is_array($v)) {
                $filtered[$k] = $v;
            }
        }
        return XFE_Carrier_Domain_CustomFieldCollection::fromArray($filtered);
    }

    /**
     * 值对象集合 → 字符串(用于持久化)。空集合写 "{}"。
     */
    public static function encode(XFE_Carrier_Domain_CustomFieldCollection $coll)
    {
        return json_encode(
            (object)$coll->toArray(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
```

---

## 5. Service 层

### 5.1 接口

`XFE_Carrier_Model_Service_Account_CustomFieldServiceInterface`
与
`XFE_Carrier_Model_Service_FtpAccount_CustomFieldServiceInterface`

```php
interface XFE_Carrier_Model_Service_Account_CustomFieldServiceInterface
{
    /**
     * 读单个字段值(供业务模块调用)。
     * @param int $accountId
     * @param string $key
     * @return string|int|float|bool|null
     */
    public function getValue($accountId, $key);

    /**
     * 读整个集合(供 Facade 透传)。
     */
    public function getCollection($accountId);

    /**
     * 用 raw POST 数据(键值对列表)更新该账号的自定义字段。
     * Controller 调入口。空集合 = 清空。
     */
    public function applyFromPost($accountId, array $post);
}
```

### 5.2 实现

`XFE_Carrier_Model_Service_Account_CustomFieldService`
`XFE_Carrier_Model_Service_FtpAccount_CustomFieldService`

两个类结构对称,差别只是底层的 Model 工厂别名。

```php
final class XFE_Carrier_Model_Service_Account_CustomFieldService
    implements XFE_Carrier_Model_Service_Account_CustomFieldServiceInterface
{
    public function getValue($accountId, $key)
    {
        $coll = $this->getCollection((int)$accountId);
        $field = $coll->get($key);
        return $field ? $field->getValue() : null;
    }

    public function getCollection($accountId)
    {
        $model = Mage::getModel('xfe_carrier/carrier_account')->load((int)$accountId);
        if (!$model->getId()) {
            return new XFE_Carrier_Domain_CustomFieldCollection();
        }
        return XFE_Carrier_Domain_CustomFieldCodec::decode($model->getCustomFieldsJson());
    }

    public function applyFromPost($accountId, array $post)
    {
        $coll = $this->_buildFromPost($post);
        $model = Mage::getModel('xfe_carrier/carrier_account')->load((int)$accountId);
        if (!$model->getId()) {
            Mage::throwException('Account not found');
        }
        $model->setCustomFieldsJson(
            XFE_Carrier_Domain_CustomFieldCodec::encode($coll)
        );
        $model->save();
        return $coll;
    }

    /**
     * POST 形态(来自 phtml 模板的键值对编辑器):
     *   custom_fields[key][label]   = string
     *   custom_fields[key][type]    = text|number|select|boolean
     *   custom_fields[key][value]   = string
     *   custom_fields[key][options] = string (逗号分隔,仅 type=select)
     * 删除某一行时,前端不提交该 key;后端视为"已删除"。
     */
    private function _buildFromPost(array $post)
    {
        $coll = new XFE_Carrier_Domain_CustomFieldCollection();
        if (!isset($post['custom_fields']) || !is_array($post['custom_fields'])) {
            return $coll;
        }
        foreach ($post['custom_fields'] as $key => $spec) {
            if (!is_array($spec)) continue;
            $type    = isset($spec['type'])  ? $spec['type']  : 'text';
            $label   = isset($spec['label']) ? $spec['label'] : $key;
            $value   = isset($spec['value']) ? $spec['value'] : null;
            $options = null;
            if (($type === XFE_Carrier_Domain_CustomField::TYPE_SELECT
                    || $type === XFE_Carrier_Domain_CustomField::TYPE_MULTISELECT)
                && isset($spec['options'])
            ) {
                $options = array_filter(array_map('trim',
                    explode(',', (string)$spec['options'])));
            }
            $coll->add(new XFE_Carrier_Domain_CustomField(
                $key, $label, $type, $value, $options
            ));
        }
        return $coll;
    }
}
```

### 5.3 注册入口

`XFE_Carrier_Model_Service_Registry` 新增两个工厂方法:

```php
public static function accountCustomFieldService()    { /* singleton */ }
public static function ftpAccountCustomFieldService() { /* singleton */ }
```

---

## 6. Model / Resource 层

### 6.1 模型

`XFE_Carrier_Model_Carrier_Account` 与 `XFE_Carrier_Model_Carrier_FtpAccount`
**只**加一对 getter/setter,**不**做反序列化(留给 Service):

```php
public function getCustomFieldsJson()    { return $this->getData('custom_fields_json'); }
public function setCustomFieldsJson($v)  { return $this->setData('custom_fields_json', $v); }
```

并实现一个**便捷访问器**(让业务模块拿到 Model 后可以直接读单个值):

```php
/**
 * 读单个自定义字段。Service 是首选入口;这个方法仅用于 Model 已经在
 * 内存里、想读一下值的场景(避免再一次 load 自身)。
 *
 * @param string $key
 * @return string|int|float|bool|null
 */
public function getCustomField($key)
{
    $coll = XFE_Carrier_Domain_CustomFieldCodec::decode($this->getCustomFieldsJson());
    $field = $coll->get($key);
    return $field ? $field->getValue() : null;
}
```

`FtpAccount` 同理。

### 6.2 Resource

`XFE_Carrier_Model_Resource_Carrier_Account` / `..._FtpAccount`
**不需要改**——`custom_fields_json` 是普通 `TEXT` 列,Mage Resource 自动序列化。

---

## 7. Controller / Block / Template

### 7.1 Controller

`saveAccountAction` 增加一步:

```php
$account->addData($data);
$account->save();

if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
    XFE_Carrier_Model_Service_Registry::accountCustomFieldService()
        ->applyFromPost($accountId, $data);
}
```

`saveFtpAccountAction` 同理调 ftp Account Service。

### 7.2 Block

`XFE_Carrier_Block_Adminhtml_Carrier_Account_Edit_Form`
新增一个 fieldset:

```php
$fieldset = $form->addFieldset('custom_fields_fieldset', array(
    'legend' => $helper->__('自定义字段'),
    'note'   => $helper->__(
        '用于保存各承运商私有参数,数据以 JSON 整体存储。'
        . ' key 由英文/数字/下划线组成,value 类型决定了输入框形态。'
    ),
));

// 不在这里加具体字段,而是用一个自定义类型 'custom_fields_editor'
// 由 renderer 渲染键值对表格编辑器。
$fieldset->addType('custom_fields_editor',
    'XFE_Carrier_Block_Adminhtml_Carrier_Account_Edit_Form_CustomFields');

$fieldset->addField('custom_fields', 'custom_fields_editor', array(
    'name'  => 'custom_fields',
    'label' => $helper->__('键值对列表'),
    'title' => $helper->__('键值对列表'),
));
```

Renderer 路径:
`app/code/community/XFE/Carrier/Block/Adminhtml/Carrier/Account/Edit/Form/CustomFields.php`

Renderer 负责渲染一个 `<table>`(key / label / type / value / options 列),
并在底部放"+ 添加字段"按钮。前端 JS(`xfe_shippingrule/js/custom-field-editor.js`,
如有则复用、无则新建)负责增删行。

### 7.3 模板

模板路径:
`app/design/adminhtml/default/default/template/xfe_carrier/carrier/account/edit/custom_fields.phtml`

服务端拿到 `$account->getCustomFieldsJson()`(原文 JSON 字符串),
JS 端用 `JSON.parse` 拆成数组,渲染到表格。

FTP 账号用对称的路径(暂时复用同一套 phtml + JS,通过容器传入 `entity_type` 区分)。

### 7.4 Layout

`xfecarrier.xml` 的 `adminhtml_carrier_editaccount` 段改为:

```xml
<adminhtml_carrier_editaccount>
    <reference name="content">
        <block type="xfe_carrier/adminhtml_carrier_account_edit" name="carrier_account_edit">
            <block type="xfe_carrier/adminhtml_carrier_account_edit_form"
                   name="carrier_account_edit_form"
                   as="form" />
        </block>
    </reference>
</adminhtml_carrier_editaccount>
```

把表单挂到 container 的 `as="form"`,跟现有 Magento 习惯一致。

FTP 账号同样改造 `adminhtml_carrier_editftpaccount`。

---

## 8. Importer / Exporter

CSV 模板新增两列(放在 `note` 之后):

| 列名 | 形态 |
|------|------|
| `custom_fields_json` | JSON 字符串(双引号转义后整体作为一个 CSV 字段) |

`Account_Importer::parseRow()` 解析时 `json_decode` 后直接 setData;
`Account_Exporter::exportRow()` 写出时 `json_encode(..., JSON_UNESCAPED_UNICODE)`。
FTP 同样改造。

---

## 9. 事件

新增 1 个事件,载荷只含 `account_id` / `key`,**不**含 `value`:

```php
Mage::dispatchEvent('xfe_carrier_account_custom_field_changed', array(
    'account_id' => $accountId,
    'key'        => $key,
    'action'     => 'added' | 'updated' | 'removed',
));
```

凭据保护:**绝对不要**把 `value` 写进 event payload。

事件契约文档同步在 [`carrier-observer-events.md`](./carrier-observer-events.md) 里追加条目。

---

## 10. 与现有代码的兼容性

| 现有代码 | 影响 | 做法 |
|---------|------|------|
| `CredentialResolver::resolveAccount()` | 返回的账号 Model 增加 `getCustomField()` | 业务模块直接用 |
| `Account_Importer` / `Account_Exporter` | CSV 模板多一列 | 透传 `custom_fields_json` 字段 |
| `CarrierController::saveAction()`(批量保存) | `accounts_data` 里可能不包含 `custom_fields` | Controller 单独调 Service 处理 |
| `CredentialResolverInterface` | **不变更签名** | 新增 `getCustomField` 是 Model 的方法,不在接口里 |

---

## 11. 测试

新增:
- `app/code/community/XFE/Carrier/Test/Domain/CustomFieldTest.php`
- `app/code/community/XFE/Carrier/Test/Domain/CustomFieldCodecTest.php`
- `app/code/community/XFE/Carrier/Test/Domain/CustomFieldCollectionTest.php`
- `app/code/community/XFE/Carrier/Test/Service/Account/CustomFieldServiceTest.php`
- `app/code/community/XFE/Carrier/Test/Service/FtpAccount/CustomFieldServiceTest.php`

覆盖:
- `key` 非法 / 重复 → 抛异常
- `type` 非法 → 抛异常
- `select` 无 options → 抛异常
- `value` 类型不匹配 → coerce 规则
- `Codec` 往返(encode → decode → 等价)
- 空 / 非法 JSON → 空集合
- Service.getValue() / applyFromPost() 全链路

---

## 12. 验收标准

1. ✅ 两张账号表都新增 `custom_fields_json TEXT NULL` 列(upgrade 脚本 + 兼容旧数据)
2. ✅ `XFE_Carrier_Domain_CustomField` / `CustomFieldCollection` / `CustomFieldCodec` 三个 L1 类存在
3. ✅ `Carrier_Account` / `Carrier_FtpAccount` Model 提供 `getCustomField($key)` 便捷访问器
4. ✅ Service 注册入口 `Registry::accountCustomFieldService()` / `ftpAccountCustomFieldService()` 可用
5. ✅ 账号编辑页面渲染出"自定义字段"fieldset,支持增/删/改行
6. ✅ 保存后:`custom_fields_json` 列存的是合法 JSON 对象(键为字段 key,值为 `{label,type,value,options?}`)
7. ✅ `Account_Importer` / `Account_Exporter` CSV 模板新增 `custom_fields_json` 列
8. ✅ 所有新增文件 `php -l` 通过
9. ✅ L1 Domain 不引用任何 `Mage_*` 类
10. ✅ 文档齐全:本文件 + ADR + `findings.md` + `progress.md`

---

## 13. 关联文档

- [`carrier-facade.md`](./carrier-facade.md) — Facade / Resolver 形态
- [`carrier-observer-events.md`](./carrier-observer-events.md) — 事件契约
- [`carrier-account-import-export.md`](./carrier-account-import-export.md) — CSV 导入导出
- [`decisions/0004-carrier-account-custom-fields-json.md`](./decisions/0004-carrier-account-custom-fields-json.md) — 决策记录
- [`decisions/0005-custom-field-multiselect.md`](./decisions/0005-custom-field-multiselect.md) — multiselect 决策记录
