# 承运商 — 自定义属性管理(Custom Attribute Management)

> 主题:在 `XFE_Carrier` 模块下,新增"**自定义属性**"中央管控机制——
> 4 个分类(承运商信息 / 账号 / FTP / LOGO)各自有**独立**的属性集,
> 由**系统管理员**在后台增删改、批量导入导出。
> 4 个分类的实体在编辑时**只能**从已登记属性中挑选并填值,
> 严格禁用"自由 key"。`is_required` 字段在 save 时**强校验**。
>
> 状态:**提案**(2026-09-07)
>
> 适用范围:`XFE_Carrier` 模块内的 4 个实体(承运商 / 账号 / FTP 账号 / LOGO)。
>
> 配套文档:
> - [`carrier-account-custom-fields.md`](./carrier-account-custom-fields.md) — Per-row JSON 现状(仅账号 / FTP 账号)
> - [`carrier-observer-events.md`](./carrier-observer-events.md) — 事件契约
> - [`decisions/0004-carrier-account-custom-fields-json.md`](./decisions/0004-carrier-account-custom-fields-json.md) — Per-row JSON 选型 ADR
> - [`decisions/0006-custom-attribute-management.md`](./decisions/0006-custom-attribute-management.md) — 本次选型 ADR

---

## 1. 背景与现状

### 1.1 问题

当前 `XFE_Carrier` 模块:

| 实体 | 表 | 自定义字段列 |
|------|---|--------------|
| 承运商信息 | `xfe_carrier_carrier` | **无** |
| 账号 | `xfe_carrier_carrier_account` | `custom_fields_json TEXT`(1.0.14 加) |
| FTP 账号 | `xfe_carrier_carrier_ftp_account` | `custom_fields_json TEXT`(1.0.14 加) |
| LOGO | `xfe_carrier_carrier_logo` | **无** |

**当前痛点**:

1. **承运商信息(LOGO)无法扩展**——任何额外属性都得 ALTER TABLE 加列。
2. **账号 / FTP 账号的"自由 key"模式**造成命名漂移、候选项不统一(详见 ADR 0006 §背景)。
3. **缺中央管控**——"哪些字段合法 / 哪些字段必填"散落在每个账号里,运营 / 业务模块都不知所云。
4. **缺批量运维**——没有"批量导入属性"工具,新环境 / 新客户都得手敲几十条记录。

### 1.2 目标(用户明确要求)

1. ✅ 新增**"自定义属性"管理菜单**:独立列表 + 添加 + 编辑 + 删除 + 批量导出 + 批量导入。
2. ✅ 4 个分类(承运商信息 / 账号 / FTP / LOGO)**各自独立**的属性集——同一 `field_key` 在不同分类下含义可以不同。
3. ✅ 4 个分类的实体编辑页**只能**从已登记属性下拉选择——**严格禁用自由 key**。
4. ✅ `is_required` 在 save 时**强校验**——少填则保存失败 + 红色错误提示。
5. ✅ 4 个分类的"未登记属性"补齐数据列(承运商 / LOGO 也要 ALTER TABLE)。

### 1.3 与现状的关系(关键)

| 实体 | 现状 | 新增 | 关系 |
|------|------|------|------|
| 承运商 (`xfe_carrier_carrier`) | 固定列(`name` / `note` / `module_code` ...) | ALTER TABLE 加 `custom_fields_json TEXT` | **复用** per-row JSON 形态 |
| 账号 (`xfe_carrier_carrier_account`) | 已有 `custom_fields_json`(1.0.14) | 改造编辑页:只允许从已登记 key 选 | **保留** 列,只改 UI + 校验 |
| FTP 账号 (`xfe_carrier_carrier_ftp_account`) | 已有 `custom_fields_json`(1.0.14) | 同上 | 同上 |
| LOGO (`xfe_carrier_carrier_logo`) | 固定列(`label` / `logo_type` / `path` / `sort_order`) | ALTER TABLE 加 `custom_fields_json TEXT` | **复用** per-row JSON 形态 |

**关键不变量**:
- **per-row JSON 仍是"真值来源"**——4 个分类都用同一形态 `{ key: {label,type,value,options?} }`。
- **全局属性表是"schema 管控"**——4 个分类在同一张表里按 `entity_type` 区分(见 §3.1)。
- **严格模式**:运营在编辑页**不能**自由加 key;但**已存在**的"未登记" key 数据仍能读 / 显示(否则会丢数据)。

---

## 2. 设计原则

### 2.1 单向依赖

```
L4  Controller / Block / 后台菜单(4 个分类独立 Block,但共用基础抽象)
        ↓
L3  Service (CustomAttributeService, 4 个分类统一入口;Registry 注册)
        ↓
L2  Model / Resource (CustomAttribute Model + Resource)
        ↓
L1  Domain (CustomAttribute 值对象 + Collection,复用 CustomField 类型常量)
```

- L1 复用 `XFE_Carrier_Domain_CustomField` 的 `ALLOWED_TYPES` + `coerceValue()`。
- L2 Resource 直接读写 `xfe_carrier_custom_attribute` 表。
- L3 Service 是**唯一**可以同时操作"全局属性表" + "各实体 per-row JSON"的层。
- L4 严格走 Service,**不**自己 `Mage::getModel('xfe_carrier/custom_attribute')`、不写 SQL。

### 2.2 模块边界

- `XFE_Carrier` 内部:完全自治。
- 其他模块:
  - **可以读** `CustomAttributeService::getActiveDefs($entityType)` 拿"该分类的合法属性";
  - **不应该**直接读 `xfe_carrier_custom_attribute` 表(走 Service);
  - **不应该**写全局属性表(只有系统管理员通过后台菜单)。

### 2.3 关键决策(详见 ADR 0006)

| 决策点 | 选择 | 理由 |
|--------|------|------|
| 1 张表 vs 4 张表 | **1 张** `xfe_carrier_custom_attribute` + `entity_type` 列 | 4 张表结构同质,1 张表减少迁移 / 维护成本 |
| `entity_type` 取值 | 4 选 1:`carrier` / `account` / `ftp_account` / `logo` | 与 4 个分类一一对应 |
| 跨分类重名 | **允许**(同一 `field_key` 在 `carrier` 和 `account` 下可以同时存在,语义独立) | 业务上"分类 A 的 `weight`"和"分类 B 的 `weight`"通常无关 |
| 编辑页严格模式 | **完全禁止**未登记 key | 用户明确要求 |
| `is_required` 校验 | **save 时强校验**,失败抛 `Mage::throwException` | 用户明确要求 |
| 旧数据兼容(per-row JSON 里的"未登记" key) | **读时按原样显示**,不允许编辑;**写时拒绝**保存 | 防止丢数据,也不破坏严格模式 |
| 菜单归属 | `系统 → 承运商管理 → 自定义属性` | 与现有菜单同根 |
| 必填校验时机 | Service 层(`applyFromPost` 入口) | 任何 Controller / Importer 都走 Service,自动覆盖 |
| 批量导入 CSV 模板 | 10 列 + `entity_type` 列(11 列) | 按分类过滤,一个文件可包含 4 分类的属性 |
| 删除属性 | 软删除(`is_active=0`)+ 唯一索引 `uk_entity_type_field_key_active` | 旧数据保留;防止同 key 重复激活 |

---

## 3. 数据契约

### 3.1 新表 `xfe_carrier_custom_attribute`

```sql
CREATE TABLE xfe_carrier_custom_attribute (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT
                    COMMENT 'PK',
    entity_type     VARCHAR(16)  NOT NULL
                    COMMENT '所属分类: carrier | account | ftp_account | logo',
    field_key       VARCHAR(64)  NOT NULL
                    COMMENT '字段 key, 英文/数字/下划线, 1~64 字符',
    label           VARCHAR(64)  NOT NULL
                    COMMENT '展示名(中文)',
    field_type      VARCHAR(16)  NOT NULL
                    COMMENT 'text | number | select | multiselect | boolean',
    options_csv     TEXT NULL DEFAULT NULL
                    COMMENT '候选项, 逗号分隔, 仅 select/multiselect',
    default_value   TEXT NULL DEFAULT NULL
                    COMMENT '默认值, JSON 序列化(scalar 或 multiselect 数组)',
    is_required     TINYINT(1)   NOT NULL DEFAULT 0
                    COMMENT '是否必填(实体编辑时强校验)',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1
                    COMMENT '软删除标记',
    sort_order      INT NOT NULL DEFAULT 0
                    COMMENT '显示顺序(下拉框顺序)',
    description     TEXT NULL DEFAULT NULL
                    COMMENT '字段说明',
    created_at      DATETIME NULL DEFAULT NULL,
    updated_at      DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_entity_type_field_key_active (entity_type, field_key, is_active)
                    COMMENT '同一 (entity_type, field_key) 在 is_active=1 时唯一',
    KEY idx_entity_type_active (entity_type, is_active)
                    COMMENT '按分类查活跃属性'
) ENGINE=InnoDB DEFAULT CHARSET=utf8
  COMMENT='承运商自定义属性全局定义(4 分类共享表)';
```

**索引设计**:
- `uk_entity_type_field_key_active (entity_type, field_key, is_active)`:唯一约束,**必须**包含 `is_active`(否则软删除后同名无法重激活)。
- `idx_entity_type_active (entity_type, is_active)`:按分类 + 活跃状态查(账号编辑页热点路径)。

**两个新增数据列**(`ALTER TABLE`):

```sql
-- 承运商本身
ALTER TABLE xfe_carrier_carrier
  ADD COLUMN custom_fields_json TEXT NULL DEFAULT NULL
  COMMENT '自定义属性值, JSON 格式'
  AFTER note;

-- LOGO
ALTER TABLE xfe_carrier_carrier_logo
  ADD COLUMN custom_fields_json TEXT NULL DEFAULT NULL
  COMMENT '自定义属性值, JSON 格式'
  AFTER sort_order;
```

账号 / FTP 账号的 `custom_fields_json` 列已在 1.0.14 加,本方案**不修改**。

### 3.2 4 个 `entity_type` 枚举

| 值 | 含义 | 对应实体 | 对应表 |
|----|------|----------|--------|
| `carrier` | 承运商信息 | `XFE_Carrier_Model_Carrier` | `xfe_carrier_carrier` |
| `account` | 账号 | `XFE_Carrier_Model_Carrier_Account` | `xfe_carrier_carrier_account` |
| `ftp_account` | FTP 账号 | `XFE_Carrier_Model_Carrier_FtpAccount` | `xfe_carrier_carrier_ftp_account` |
| `logo` | LOGO | `XFE_Carrier_Model_Carrier_Logo` | `xfe_carrier_carrier_logo` |

### 3.3 迁移脚本

新文件:

- `app/code/community/XFE/Carrier/sql/xfe_carrier_setup/upgrade-1.0.14-1.0.15.php`
  - 建 `xfe_carrier_custom_attribute` 表
  - ALTER `xfe_carrier_carrier` 加 `custom_fields_json` 列
  - ALTER `xfe_carrier_carrier_logo` 加 `custom_fields_json` 列
- `app/code/community/XFE/Carrier/etc/config.xml` version 1.0.14 → 1.0.15
- `app/code/community/XFE/Carrier/sql/xfe_carrier_setup/README.md` 迁移表新增条目

---

## 4. Domain 层

### 4.1 新增值对象 `XFE_Carrier_Domain_CustomAttribute`

**文件**:`app/code/community/XFE/Carrier/Domain/CustomAttribute.php`

表示一条"自定义属性定义",从 DB 加载后存为该对象的实例。**不引用任何 Mage_* 类**。

```php
final class XFE_Carrier_Domain_CustomAttribute
{
    const ENTITY_TYPE_CARRIER     = 'carrier';
    const ENTITY_TYPE_ACCOUNT     = 'account';
    const ENTITY_TYPE_FTP_ACCOUNT = 'ftp_account';
    const ENTITY_TYPE_LOGO        = 'logo';
    const ALLOWED_ENTITY_TYPES = array(
        self::ENTITY_TYPE_CARRIER, self::ENTITY_TYPE_ACCOUNT,
        self::ENTITY_TYPE_FTP_ACCOUNT, self::ENTITY_TYPE_LOGO,
    );

    private $id;
    private $entityType;        // ENTITY_TYPE_*
    private $fieldKey;          // /^[a-z0-9_]{1,64}$/
    private $label;             // 1~64 字符
    private $fieldType;         // CustomField::TYPE_*
    private $options;           // string[]|null (select/multiselect)
    private $defaultValue;      // scalar | string[] | null
    private $isRequired;
    private $isActive;
    private $sortOrder;
    private $description;       // string|null

    public function __construct(...) { /* 校验 + 不可变 */ }

    public function getId() / getEntityType() / getFieldKey() / getLabel() / ...
    public function getOptions() : ?array
    public function getDefaultValue()        // 已 coerce 到 type 对应 PHP 标量
    public function isActive() : bool
    public function isRequired() : bool

    public function toCustomFieldArray() : array
    {
        // 复用 CustomField::__construct 形态,供 CustomFieldCollection::fromArray
        return array(
            'label'   => $this->label,
            'type'    => $this->fieldType,
            'options' => $this->options,
            'value'   => $this->defaultValue,
        );
    }
}
```

### 4.2 新增集合 `XFE_Carrier_Domain_CustomAttributeCollection`

**文件**:`app/code/community/XFE/Carrier/Domain/CustomAttributeCollection.php`

`IteratorAggregate + Countable`,**同 entity_type + field_key 不能重复**(由 `add()` 强制),按 `sort_order` 升序 + `field_key` 字母序排序。

```php
final class XFE_Carrier_Domain_CustomAttributeCollection
    implements IteratorAggregate, Countable
{
    public function add(CustomAttribute $attr) : self
    public function has(string $fieldKey) : bool
    public function get(string $fieldKey) : ?CustomAttribute
    public function remove(string $fieldKey) : self
    public function filterActive() : self
    public function getKeys() : array
    public function getRequiredKeys() : array      // is_required=true 的 field_key 列表
    public function toArray() : array              // [field_key => CustomAttribute]
    public function toCustomFieldArray() : array   // [field_key => {label,type,options,value}]

    public static function fromArray(array $rows) : self
}
```

### 4.3 复用 `XFE_Carrier_Domain_CustomField`

**不修改**现有 `CustomField` 类——`CustomAttribute::toCustomFieldArray()` 输出形态
**正好**与 `CustomField::__construct()` 兼容,继续用 `coerceValue` 复用既有逻辑。

---

## 5. Service 层

### 5.1 新增 `XFE_Carrier_Model_Service_CustomAttributeService`

**文件**:`app/code/community/XFE/Carrier/Model/Service/CustomAttributeService.php`

**注册入口**:`XFE_Carrier_Model_Service_Registry::customAttributeService()`

| 方法 | 用途 |
|------|------|
| `getAllDefs(string $entityType) : CustomAttributeCollection` | 全量(含停用) |
| `getActiveDefs(string $entityType) : CustomAttributeCollection` | 仅 `is_active=1` |
| `getRequiredDefs(string $entityType) : CustomAttributeCollection` | 仅 `is_required=1` |
| `findByKey(string $entityType, string $fieldKey) : ?CustomAttribute` | 单条查询 |
| `getOptionsForDropdown(string $entityType) : array` | `[field_key => label]` 给前端下拉 |
| `createDef(array $post) : CustomAttribute` | 后台新增 |
| `updateDef(int $id, array $post) : CustomAttribute` | 后台编辑 |
| `softDeleteDef(int $id) : bool` | 软删除(`is_active=0`) |
| `activateDef(int $id) : bool` | 重新激活(`is_active=1`,仅在同 entity_type 无同名活动记录时允许) |
| `importFromCsv(string $filePath) : Importer\Result` | CSV 导入(详见 §8) |
| `exportToCsv(string\|null $entityType = null) : string` | CSV 导出(`null` = 全部 4 分类) |

**关键校验**(`createDef` / `updateDef`):
- `entity_type` 必须在 `ALLOWED_ENTITY_TYPES` 内
- `field_key` 必须匹配 `/^[a-z0-9_]{1,64}$/`
- `field_type` 必须匹配 `ALLOWED_TYPES`
- `field_type=select` 时 `options_csv` 非空
- `default_value` 按 `field_type` `coerce` 失败 → 抛异常
- 唯一索引冲突(同 `(entity_type, field_key, is_active=1)`) → 抛异常

### 5.2 复用 `XFE_Carrier_Model_Service_Account_CustomFieldService` 改造

**`applyFromPost($accountId, array $post)` 增加"全局属性表校验"步骤**:

| 步骤 | 当前(1.0.14) | 改造后(1.0.15) |
|------|---------------|-----------------|
| 1. 解析 POST 为 `CustomFieldCollection` | ✓ | 不变 |
| 2. 加载全局属性表 | — | **新增**:`customAttributeService()->getActiveDefs('account')` |
| 3. **严格校验** | — | **新增**:<br>① POST 提交的 key **必须**在全局表中;<br>② `is_required` 字段 value 不能为空;<br>③ value 必须在 options 内(select / multiselect 固定模式) |
| 4. **同步元数据** | POST 自带 | **改造**:用全局表的最新 `label` / `type` / `options` **覆盖** POST 自带的(POST 仅决定 `key` + `value`);POST 自带 label / type / options **不再生效** |
| 5. 保存 + dispatch 事件 | ✓ | 不变 |

**FTP 账号对称**(`applyFromPost` + `entity_type='ftp_account'`)。

### 5.3 新增承运商 / LOGO 的 `applyFromPost`

**新建** 2 个 Service(对称结构):

- `XFE_Carrier_Model_Service_Carrier_CustomAttributeApplier` — 处理承运商编辑
- `XFE_Carrier_Model_Service_Logo_CustomAttributeApplier` — 处理 LOGO 编辑

两者都用 `CustomAttributeService` 校验 + 同步元数据。

或者更轻量:把 `applyFromPost` 抽象到 `XFE_Carrier_Model_Service_CustomAttributeApplierAbstract`,4 个实体共用一个基类(参考 `CustomFieldServiceAbstract`)。

### 5.4 4 个编辑页 Service 入口一览

| 实体 | Service | entity_type |
|------|---------|-------------|
| 承运商 | `XFE_Carrier_Model_Service_Carrier_CustomAttributeApplier` | `carrier` |
| 账号 | `XFE_Carrier_Model_Service_Account_CustomFieldService::applyFromPost` | `account` |
| FTP 账号 | `XFE_Carrier_Model_Service_FtpAccount_CustomFieldService::applyFromPost` | `ftp_account` |
| LOGO | `XFE_Carrier_Model_Service_Logo_CustomAttributeApplier` | `logo` |

**统一校验规则**(基类抽象):
- key 必须在 `getActiveDefs($entityType)` 内
- `is_required` 字段 value 非空
- value 必须在 options 内

---

## 6. Model / Resource 层

### 6.1 Model `XFE_Carrier_Model_CustomAttribute`

**文件**:`app/code/community/XFE/Carrier/Model/CustomAttribute.php`

继承 `Mage_Core_Model_Abstract`,`init('xfe_carrier/custom_attribute', 'id')`,提供每个字段的 getter/setter。

提供 `getDomain() : CustomAttribute` 把 Mage Model 包装为不可变 Domain 对象。

### 6.2 Resource `XFE_Carrier_Model_Resource_CustomAttribute`

**文件**:`app/code/community/XFE/Carrier/Model/Resource/CustomAttribute.php`

继承 `Mage_Core_Model_Resource_Db_Abstract`,`_construct()` 指定 `xfe_carrier_custom_attribute` 表 / `id` 字段。

### 6.3 Resource Collection `XFE_Carrier_Model_Resource_CustomAttribute_Collection`

**文件**:`app/code/community/XFE/Carrier/Model/Resource/CustomAttribute/Collection.php`

继承 `Mage_Core_Model_Resource_Db_Collection_Abstract`,提供:
- `addEntityTypeFilter($entityType)`
- `addIsActiveFilter()`
- `addIsRequiredFilter()`
- `setOrderByDisplay()` — `ORDER BY sort_order ASC, field_key ASC`

### 6.4 4 个实体的 Model 改造

| Model | 加方法 |
|-------|--------|
| `XFE_Carrier_Model_Carrier` | `getCustomFieldsJson()` / `setCustomFieldsJson()` / `getCustomField($key)` |
| `XFE_Carrier_Model_Carrier_Account` | 已有(1.0.14)|
| `XFE_Carrier_Model_Carrier_FtpAccount` | 已有(1.0.14) |
| `XFE_Carrier_Model_Carrier_Logo` | `getCustomFieldsJson()` / `setCustomFieldsJson()` / `getCustomField($key)` |

### 6.5 config.xml 注册

```xml
<global>
    <models>
        <xfe_carrier>
            <custom_attribute>...</custom_attribute>
        </xfe_carrier>
    </models>
    <resources>
        <xfe_carrier_setup>
            <setup><module>XFE_Carrier</module></setup>
            <connection><use>core_setup</use></connection>
        </xfe_carrier_setup>
    </resources>
</global>
```

---

## 7. Controller / Block / Template / Menu

### 7.1 后台菜单

`app/code/community/XFE/Carrier/etc/adminhtml.xml` 在 `xfe_carrier` 菜单下新增:

```xml
<children>
    <!-- ... 现有项 (账号 / FTP账号 / 规则 / 承运商 / LOGO) ... -->
    <custom_attribute translate="title" module="xfe_carrier">
        <title>自定义属性</title>
        <sort_order>50</sort_order>
        <action>adminhtml/carrier/customAttribute</action>
    </custom_attribute>
</children>
```

`sort_order=50` 排在常规子菜单前,系统管理员一眼看到。

**Controller 路径**:`/admin/carrier/customAttribute/...`。

### 7.2 列表页 — 4 分类筛选

列表页(`/admin/carrier/customAttribute/`)默认**显示全部 4 分类**的属性(网格列加 `entity_type` 列),
顶部提供 4 个 Tab 过滤器:

```
┌─────────────────────────────────────────────────────────────┐
│  [全部] [承运商信息] [账号] [FTP] [LOGO]                    │
├─────────────────────────────────────────────────────────────┤
│  [+ 新增] [批量导入] [批量导出]                              │
├─────────────────────────────────────────────────────────────┤
│  ID │ 分类 │ Key │ 展示名 │ 类型 │ 必填 │ 启用 │ 排序 │ 操作 │
│  ... │ ... │ ... │ ...    │ ...  │ ... │ ...  │ ...  │ ...  │
└─────────────────────────────────────────────────────────────┘
```

新增 / 编辑 / 删除时**强制**选 `entity_type`(下拉单选 4 选 1)。

### 7.3 Controller `XFE_Carrier_Adminhtml_CustomAttributeController`

继承 `Mage_Adminhtml_Controller_Action`,提供:

| Action | URL | 行为 |
|--------|-----|------|
| `indexAction` | `/admin/carrier/customAttribute/` | 列表(支持 ?entity_type= 过滤) |
| `newAction` | `/admin/carrier/customAttribute/new` | 渲染新增表单 |
| `editAction` | `/admin/carrier/customAttribute/edit/id/{id}` | 渲染编辑表单 |
| `saveAction` | POST | 创建 / 更新 |
| `deleteAction` | `/admin/carrier/customAttribute/delete/id/{id}` | 软删除 |
| `activateAction` | `/admin/carrier/customAttribute/activate/id/{id}` | 重新激活 |
| `importAction` | GET | 渲染上传表单 |
| `importPostAction` | POST | 处理 CSV 上传 |
| `exportAction` | GET | 下载 CSV(支持 ?entity_type= 过滤) |

**权限**:`_isAllowed()` 必须返回 `true`(系统管理员);非管理员 403。

### 7.4 Block 列表(grid)

`XFE_Carrier_Block_Adminhtml_CustomAttribute_Grid` 继承 `Mage_Adminhtml_Block_Widget_Grid`:

| 列 | 来源 | 排序 | 过滤 |
|----|------|------|------|
| ID | `id` | ✓ | ✓ |
| 分类 | `entity_type` | ✗ | ✓(下拉) |
| Key | `field_key` | ✓ | ✓ |
| 展示名 | `label` | ✓ | ✓ |
| 类型 | `field_type` | ✗ | ✓(下拉) |
| 必填 | `is_required` | ✗ | ✓(下拉) |
| 启用 | `is_active` | ✗ | ✓(下拉) |
| 排序 | `sort_order` | ✓ | ✓ |
| 操作 | — | — | 编辑 / 启停 / 删除 |

### 7.5 Block 编辑表单

`XFE_Carrier_Block_Adminhtml_CustomAttribute_Edit_Form`:

| 字段 | 控件 | 必填 | 校验 |
|------|------|------|------|
| `entity_type` | select(创建时 4 选 1,编辑时禁用) | ✓ | 必须在 `ALLOWED_ENTITY_TYPES` |
| `field_key` | text(创建时可改,编辑时禁用) | ✓ | `/^[a-z0-9_]{1,64}$/` |
| `label` | text | ✓ | 1~64 字符 |
| `field_type` | select | ✓ | — |
| `options_csv` | text(逗号分隔) | 条件必填 | — |
| `default_value` | text(按 type 切换:multiselect 用 textarea 每行一项) | 否 | — |
| `is_required` | checkbox | 否 | — |
| `is_active` | checkbox | 否 | — |
| `sort_order` | number | 否 | — |
| `description` | textarea | 否 | — |

### 7.6 Template

`app/design/adminhtml/default/default/template/xfe_carrier/custom_attribute/`:

| 文件 | 用途 |
|------|------|
| `grid/container.phtml` | 列表页(含 Tab 过滤器 + [+ 新增] / [批量导入] / [批量导出] 按钮) |
| `edit/form.phtml` | 编辑表单 |
| `import/container.phtml` | 导入上传页 |
| `import/form.phtml` | 导入上传表单 |

### 7.7 Layout

`xfecarrier.xml` 新增:

```xml
<adminhtml_carrier_customattribute>
    <reference name="content">
        <block type="xfe_carrier/adminhtml_custom_attribute" name="custom_attribute" />
    </reference>
</adminhtml_carrier_customattribute>
<adminhtml_carrier_customattribute_new>
    <reference name="content">
        <block type="xfe_carrier/adminhtml_custom_attribute_edit" name="custom_attribute_edit" />
    </reference>
</adminhtml_carrier_customattribute_new>
<adminhtml_carrier_customattribute_edit>
    <reference name="content">
        <block type="xfe_carrier/adminhtml_custom_attribute_edit" name="custom_attribute_edit" />
    </reference>
</adminhtml_carrier_customattribute_edit>
<adminhtml_carrier_customattribute_import>
    <reference name="content">
        <block type="xfe_carrier/adminhtml_custom_attribute_import" name="custom_attribute_import" />
    </reference>
</adminhtml_carrier_customattribute_import>
```

### 7.8 4 个实体编辑页 Block 改造

| 实体编辑页 | 改造内容 |
|-----------|----------|
| 承运商 (`XFE_Carrier_Block_Adminhtml_Carrier_Edit_Form` 增字段) | 新增 fieldset "自定义属性",渲染:已登记属性下拉 + 值输入 |
| 账号 (`XFE_Carrier_Block_Adminhtml_Carrier_Account_Edit_Form`) | 改造 `custom_fields.phtml` 模板:**下拉选 key** 而非自由输入;**[+ 添加新字段] 按钮移除**(严格模式) |
| FTP 账号 (`XFE_Carrier_Block_Adminhtml_Carrier_FtpAccount_Edit_Form`) | 同上 |
| LOGO (`XFE_Carrier_Block_Adminhtml_Carrier_Logo_Edit`) | 新增 fieldset "自定义属性" |

**改造后 UI 形态**(账号页示例):

```
┌─────────────────────────────────────────────────────────────────┐
│ 自定义属性                                                       │
├─────────────────────────────────────────────────────────────────┤
│ 字段(下拉):                                                      │
│ ┌──────────────────────────────────────┬────────────────────┐  │
│ │ warehouse_code (仓库代码) [text]      │ 值: [WH-001     ]  │  │
│ │ max_weight_kg (最大承重)  [number] ✓必 │ 值: [20          ] │  │
│ │ service_level (服务等级)  [select]    │ 值: [express    ▾] │  │
│ │ [+ 添加字段(只能从下拉选)]            │                    │  │
│ └──────────────────────────────────────┴────────────────────┘  │
│ 提示: 必填字段(*)不填将无法保存                                 │
└─────────────────────────────────────────────────────────────────┘
```

**必填字段** UI 用红色 `*` 标识;save 校验失败时 `Mage::throwException` 在编辑页顶部显示具体缺失字段。

---

## 8. Importer / Exporter

### 8.1 新增 3 个 Service

- `XFE_Carrier_Model_Service_CustomAttribute_Importer`
- `XFE_Carrier_Model_Service_CustomAttribute_Exporter`
- `XFE_Carrier_Model_Service_CustomAttribute_Importer_Result`

(与现有 `Account/Importer` + `Account/Importer/Result` 对称)

### 8.2 CSV 列定义(11 列)

| 列名 | 必填 | 形态 |
|------|------|------|
| `entity_type` | ✓ | `carrier` / `account` / `ftp_account` / `logo` |
| `field_key` | ✓ | `/^[a-z0-9_]{1,64}$/` |
| `label` | ✓ | 1~64 字符 |
| `field_type` | ✓ | `text` / `number` / `select` / `multiselect` / `boolean` |
| `options_csv` | 条件 | 逗号分隔 |
| `default_value` | 否 | string(数字/布尔按字面);multiselect 用 `\|` 分隔(参见 ADR 0005) |
| `is_required` | 否 | `0` / `1`(默认 0) |
| `is_active` | 否 | `0` / `1`(默认 1) |
| `sort_order` | 否 | int(默认 0) |
| `description` | 否 | string |

**导入行为**:
- `(entity_type, field_key)` 已存在且 `is_active=1` → **更新**(upsert)
- `(entity_type, field_key)` 已存在但 `is_active=0` → **激活**并更新
- `(entity_type, field_key)` 不存在 → **新建**

**冲突处理**:同 `(entity_type, field_key)` 在 CSV 多次出现 → 取最后一次,记入 Result 的 warnings。

**导出行为**:
- 默认导出全部 4 分类(`?entity_type=carrier` 仅导 `carrier` 分类)
- 文件名:`xfe_carrier_custom_attribute_{entityType 或 all}_{timestamp}.csv`

### 8.3 4 个实体编辑器的现有 Im/Ex

**不变**:账号 / FTP 账号 / 规则 / 承运商 / LOGO 各自的 `Importer` / `Exporter` 仍按 per-row JSON 透传(`custom_fields_json` 列),**不**做全局属性校验——这些是"数据导入导出",与"schema 管控"是两层。

---

## 9. 事件

### 9.1 新增 4 个事件

| 事件名 | 触发时机 | Payload |
|--------|----------|---------|
| `xfe_carrier_custom_attribute_created` | Controller::saveAction() 创建 | `def_id`, `entity_type`, `field_key` |
| `xfe_carrier_custom_attribute_updated` | Controller::saveAction() 更新 | `def_id`, `entity_type`, `field_key`, `changed_fields`(array) |
| `xfe_carrier_custom_attribute_deactivated` | Controller::deleteAction() | `def_id`, `entity_type`, `field_key` |
| `xfe_carrier_custom_attribute_activated` | Controller::activateAction() | `def_id`, `entity_type`, `field_key` |

**不**在 payload 中带 `default_value` / `options`(可能含枚举/凭据,避免滥用)。

### 9.2 复用现有事件

`xfe_carrier_account_custom_field_changed` (carrier-account-custom-fields §9) **不变**——账号字段值变更仍发该事件。

承运商 / LOGO / FTP 账号若需要类似事件,后续可补。

---

## 10. 测试

### 10.1 单元测试

| 文件 | 覆盖 |
|------|------|
| `Test/Domain/CustomAttributeTest.php` | 构造校验、entity_type 校验、is_required 校验、toCustomFieldArray 输出 |
| `Test/Domain/CustomAttributeCollectionTest.php` | add/has/get/remove、filterActive、getRequiredKeys、fromArray、排序、跨 entity_type 隔离 |
| `Test/Service/CustomAttributeServiceTest.php` | createDef / updateDef / softDeleteDef / activateDef 的 happy path + 异常路径 |
| `Test/Service/CustomFieldServiceAbstractStrictModeTest.php` | 严格模式:未登记 key 拒绝、必填字段缺失拒绝、value 越界拒绝、label/type 来自全局表 |

### 10.2 集成测试(需 Magento bootstrap)

- Service 与 DB 的真实读写(4 个 entity_type 各自场景)
- 唯一索引 `uk_entity_type_field_key_active` 的冲突行为
- 软删除 + 重激活的 4 分类行为
- `applyFromPost` 4 个实体的端到端测试

### 10.3 验收(浏览器)

- 后台菜单"自定义属性"可见
- 4 分类 Tab 过滤正常(全部 / 承运商信息 / 账号 / FTP / LOGO)
- 创建 `warehouse_code` (text, account) → 账号编辑页下拉里出现
- 账号编辑页:**未登记 key 不能提交**(严格模式生效)
- 必填字段不填 → 保存失败,顶部红色错误提示具体字段
- 承运商 / LOGO 编辑页能填写已登记的自定义属性
- CSV 导出(全部) → 看到 11 列
- CSV 导入(3 行,1 新 1 更新 1 激活) → 全成功
- 旧数据兼容:历史账号的 per-row JSON 里有"未登记"的 key 仍能显示(只读)

---

## 11. 兼容性

| 现有代码 | 影响 | 做法 |
|---------|------|------|
| `CustomFieldService::applyFromPost()` | 改造:增加严格校验 | 见 §5.2 |
| 账号 / FTP 账号编辑页模板 `custom_fields.phtml` | 改造:下拉选 key 而非自由输入;移除 "[+ 添加新字段]";新增"必填 * 提示" | 见 §7.8 |
| 旧账号 per-row JSON 里**未登记**的 key | **完全保留** | 读时按原样显示(只读);写时拒绝新提交 |
| `CredentialResolver::resolveAccount()` 业务模块读 `getCustomField($key)` | **不变** | 仍读 per-row JSON,值永远在 |
| `Account/Importer` / `Exporter` | **不变** | per-row JSON 列透传 |
| 承运商 / LOGO 表的固定列 | **不变** | 新增 `custom_fields_json` 列共存 |

**升级步骤**(对运营):
1. 升级代码后,先到"自定义属性"菜单**手动登记**目前实际在用的字段(从现有 per-row JSON 里导出识别)
2. 登记完成后,4 个编辑页的"自由 key"模式自动关闭
3. 旧数据里"未登记"的 key 仍能显示(只读),运营可以选择补登记,或导出后清理

---

## 12. 验收标准

1. ✅ 新表 `xfe_carrier_custom_attribute` 创建成功
2. ✅ `xfe_carrier_carrier` / `xfe_carrier_carrier_logo` 都加 `custom_fields_json TEXT NULL` 列
3. ✅ `XFE_Carrier_Domain_CustomAttribute` / `CustomAttributeCollection` 两个 L1 类存在且 `php -l` 通过
4. ✅ `XFE_Carrier_Model_Service_CustomAttributeService` 实现所有 §5.1 方法
5. ✅ 后台菜单"自定义属性"可见,4 分类 Tab 过滤正常
6. ✅ 创建 / 编辑 / 启停 / 删除 / 批量导入 / 批量导出 6 个流程全跑通
7. ✅ 4 个编辑页(承运商 / 账号 / FTP / LOGO)只能下拉选已登记 key
8. ✅ 未登记 key 在编辑页**无法提交**(严格模式)
9. ✅ 必填字段不填 → save 失败,顶部红色错误
10. ✅ 字段定义停用后,旧数据仍能显示
11. ✅ 旧账号里"未登记"的 key 仍能显示(只读)
12. ✅ CSV 导入 / 导出全跑通(11 列)
13. ✅ 所有新增文件 `php -l` 通过
14. ✅ L1 Domain 不引用任何 `Mage_*` 类
15. ✅ 文档齐全:本文件 + ADR 0006 + `findings.md` + `progress.md`

---

## 13. 关联文档

- [`carrier-account-custom-fields.md`](./carrier-account-custom-fields.md) — Per-row JSON 现状(账号 / FTP)
- [`carrier-observer-events.md`](./carrier-observer-events.md) — 事件契约
- [`decisions/0004-carrier-account-custom-fields-json.md`](./decisions/0004-carrier-account-custom-fields-json.md) — JSON 选型 ADR
- [`decisions/0005-custom-field-multiselect.md`](./decisions/0005-custom-field-multiselect.md) — multiselect ADR
- [`decisions/0006-custom-attribute-management.md`](./decisions/0006-custom-attribute-management.md) — **本方案选型 ADR**
