# 0006. 自定义属性管理(Custom Attribute Management)

- 状态:**Accepted**
- 日期:2026-09-07
- 决策者:hanson.gao
- 更新:2026-09-07 — 状态从 Proposed 升级为 Accepted(代码已按本 ADR 落地,详见 §落地)

## 背景

`XFE_Carrier` 模块的 4 个实体(承运商信息 / 账号 / FTP 账号 / LOGO)当前
缺乏统一的"自定义属性"中央管控:

| 实体 | 表 | 自定义字段列 | 现状 |
|------|---|--------------|------|
| 承运商信息 | `xfe_carrier_carrier` | **无** | 完全不能扩展 |
| 账号 | `xfe_carrier_carrier_account` | `custom_fields_json TEXT`(1.0.14) | per-row JSON,自由 key |
| FTP 账号 | `xfe_carrier_carrier_ftp_account` | `custom_fields_json TEXT`(1.0.14) | per-row JSON,自由 key |
| LOGO | `xfe_carrier_carrier_logo` | **无** | 完全不能扩展 |

**实际痛点**:
1. **承运商 / LOGO 无扩展点**——任何额外属性都得 ALTER TABLE 加列,频繁改动数据库 schema。
2. **账号 / FTP 账号 per-row JSON 是"完全去中心化"**——命名漂移、候选项不统一、缺默认值、缺文档(详见原 ADR 0006 §背景)。
3. **缺中央管控**——系统管理员无法集中定义"哪些字段合法 / 哪些必填"。
4. **缺批量运维**——新环境 / 新客户都得手敲几十条记录。

`AGENTS.md §1 不可妥协的开发原则 5 — 文档先行`要求在写代码前先完成架构决策记录。

## 决策

### 核心:1 张表覆盖 4 分类

新增一张独立的**全局属性定义表** `xfe_carrier_custom_attribute`,用 `entity_type`
列区分 4 个分类(承运商信息 / 账号 / FTP / LOGO)。

```
entity_type ∈ { carrier, account, ftp_account, logo }
同一 (entity_type, field_key) 在 is_active=1 时唯一
```

### 关键决策点

| 决策点 | 选择 | 备选 | 理由 |
|--------|------|------|------|
| 1 张表 vs 4 张表 | **1 张** + `entity_type` 列 | 4 张独立表 | 4 张表结构同质,1 张表减少迁移 / 维护成本;`uk_entity_type_field_key_active` 唯一索引保证同 key 不冲突 |
| 跨分类重名 | **允许** | 全局唯一 | 业务上"分类 A 的 `weight`"和"分类 B 的 `weight`"通常无关,强制全局唯一会反而麻烦 |
| 编辑页严格模式 | **完全禁止未登记 key** | 允许未登记 key + 警告 | **用户明确要求**"4 个分类只能显示已添加的属性" |
| `is_required` 校验 | **save 时强校验**(`Mage::throwException` 抛异常) | 仅 UI 提示 | **用户明确要求**必填 |
| 旧数据兼容(per-row JSON 里的"未登记" key) | **读时按原样显示(只读),写时拒绝** | 强制迁移 / 报错误后丢失 | 防止丢数据,也不破坏严格模式;运营可后续补登记或导出清理 |
| 菜单归属 | `系统 → 承运商管理 → 自定义属性` | 顶级菜单 / 放到 `Catalog` 下 | 与现有 `xfe_carrier` 菜单同根,权限统一 |
| 批量导入 CSV 模板 | 11 列(10 列 + `entity_type` 头列) | 一个分类一个 CSV 文件 | 一个文件覆盖 4 分类,运维更简单 |
| 删除属性 | **软删除**(`is_active=0`)+ 唯一索引包含 `is_active` | 硬删除 | 旧数据保留;防止同 key 重复激活冲突 |
| 是否必填 | **新增 `is_required`** | 不支持(per-row JSON 表达不出) | 用户明确要求 |
| 默认值 | **新增 `default_value`**(JSON 序列化) | 不支持 | 减少运营首次填字段的工作量 |
| 承运商 / LOGO 加列 | **复用 per-row JSON 形态**(`custom_fields_json TEXT`) | 新增独立 JSON Schema | 与账号 / FTP 保持一致,4 个实体共用 `CustomField` 值对象 |
| 跨模块共享 | **仅本模块**(`XFE_Carrier`) | 抽象到 `Mage_Core` | YAGNI;后续如有需要再抽象 |

### 数据库 schema 要点

```sql
-- 1. 新表
CREATE TABLE xfe_carrier_custom_attribute (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type     VARCHAR(16)  NOT NULL,    -- carrier | account | ftp_account | logo
    field_key       VARCHAR(64)  NOT NULL,    -- /^[a-z0-9_]{1,64}$/
    label           VARCHAR(64)  NOT NULL,    -- 1~64 字符
    field_type      VARCHAR(16)  NOT NULL,    -- text | number | select | multiselect | boolean
    options_csv     TEXT NULL,
    default_value   TEXT NULL,                -- JSON 序列化
    is_required     TINYINT(1)   NOT NULL DEFAULT 0,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order      INT NOT NULL DEFAULT 0,
    description     TEXT NULL,
    created_at      DATETIME NULL,
    updated_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_entity_type_field_key_active (entity_type, field_key, is_active),
    KEY idx_entity_type_active (entity_type, is_active)
);

-- 2. 承运商加列
ALTER TABLE xfe_carrier_carrier
  ADD COLUMN custom_fields_json TEXT NULL DEFAULT NULL
  AFTER note;

-- 3. LOGO 加列
ALTER TABLE xfe_carrier_carrier_logo
  ADD COLUMN custom_fields_json TEXT NULL DEFAULT NULL
  AFTER sort_order;
```

## 备选方案

### 备选 A:1 张表 + `entity_type`,但允许未登记 key(宽松模式)

- **优点**:不破坏现有 per-row JSON 流程,运营渐进迁移。
- **缺点**:**违反用户明确要求**("4 个分类只能显示已添加的属性")。
- **不选**。

### 备选 B:4 张表(每分类一张)

- **优点**:每张表结构简单;无 `entity_type` 列冗余;权限可细分。
- **缺点**:Service / Resource / Collection 各 4 份,代码量翻 4 倍;CSV 导入导出要按分类跑 4 次;唯一索引逻辑要重复 4 次。
- **不选**(违反 DRY;1 张表 + `entity_type` 列 + 唯一索引已经足够保证正确性)。

### 备选 C:复用 Magento 现有的 `eav_attribute` + `catalog_eav_attribute`

- **优点**:复用现成的 EAV 框架;管理后台有现成的"属性集"管理 UI。
- **缺点**:与 `XFE_Carrier` 的 per-row JSON 形态(`custom_fields_json`)对接复杂;EAV 表结构有 6 张(`*_entity_varchar/int/text/datetime/decimal`),反而**比新建一张表**复杂;Magento 1 的 EAV UI 在后台太重,不适合"几十个字段"的轻量场景。
- **不选**。

### 备选 D:把属性定义存在 `custom_fields_json` 同表的另一个 JSON 列(per-row 形态)

- **优点**:不需要新表。
- **缺点**:"每个实体存自己的属性定义" → 退化为"完全去中心化",问题没解决。
- **不选**。

### 备选 E:用 `core_config_data`(system config)存属性定义

- **优点**:不建新表;不需要迁移脚本。
- **缺点**:`core_config_data` 是 EAV 风格 KV,字段多了查询/排序/搜索体验差;CSV 导入导出需要走 `Mage::getConfig()` 序列化;权限/审计/事件难做。
- **不选**。

### 备选 F:把 per-row JSON 也替换成 EAV(5 张表) — 彻底重构

- **优点**:支持范围/排序/聚合;性能更好。
- **缺点**:**破坏性变更**——所有现网 4 个实体的 per-row JSON 数据要 ETL 迁移;`applyFromPost` 流程全改;Im/Ex 流程全改;工作量是本次的 5~10 倍。
- **不选**(YAGNI;per-row JSON 性能对当前 1000 个账号 / 5 个字段规模完全够用)。

## 后果

### 正面

- ✅ **4 个分类统一管控**——一次设计,所有实体受益
- ✅ **承运商 / LOGO 也能扩展**——不再需要 ALTER TABLE
- ✅ **命名漂移 / 候选项不统一 / 缺默认值**问题一次性解决
- ✅ **系统管理员有"中央管控"菜单**,字段集可统一发布
- ✅ **`is_required` 强校验**——保证数据完整性
- ✅ **审计**:字段定义有 `created_at` / `updated_at` / `is_active`,变更可追溯
- ✅ **批量导入 / 导出**——新环境 / 新客户运维成本降到 0
- ✅ **软删除**不破坏旧数据,迁移无感
- ✅ 1 张表 + `entity_type` 列,减少代码 / 迁移 / 维护成本

### 负面 / 风险

- ⚠️ **新表 + 4 个实体的编辑页 UI 大改 + 新菜单 + 新 Im/Ex + 改造 applyFromPost**——工作量约 6~8 commit。
- ⚠️ **账号 / FTP 账号编辑页从"自由加 key"变成"下拉选已登记 key"**——运营需要重新培训。
- ⚠️ **必填校验是阻塞性**——少填就 save 失败,需要运营先补齐自定义属性菜单的登记。
- ⚠️ **数据库表数量 +2**(1 新 + 2 ALTER)。
- ⚠️ **`applyFromPost` 4 个入口都增加校验步骤**——每次保存都查一次全局表(可加进程内缓存缓解,见下方"演进")。
- ⚠️ **承运商 / LOGO 旧数据迁移**——per-row JSON 列是 NULL,升级后无数据;运营需要重新填。
- ⚠️ **未登记 key 的兼容**:`custom_fields_json` 里如果存了"全局表里没登记"的 key,显示时仍能渲染(向后兼容),但运营可能误以为"登记了"。

### 演进(本次不做,留作未来)

- **进程内缓存**:`CustomAttributeService::getActiveDefs()` 加 `Mage_App::getCache()->load('xfe_carrier_active_defs')`,运营修改全局表时 `cleanCache('xfe_carrier_active_defs')`。
- **多语言 label**:`label` 列升级为 `i18n_string` 或新增 `xfe_carrier_custom_attribute_i18n` 表。
- **跨模块共享**:抽象到 `Mage_Core` 框架,`XFE_Logistic` / `XFE_MagePlugin` 也可登记属性。
- **JSON Schema 校验**:`default_value` 用 JSON Schema 描述,`applyFromPost` 时强校验。
- **属性分组**:增加 `attribute_group` 列,UI 按分组折叠显示。

## 关联文档

- [`../carrier-global-custom-field-defs.md`](../carrier-global-custom-field-defs.md) — 本次架构设计文档
- [`../carrier-account-custom-fields.md`](../carrier-account-custom-fields.md) — Per-row JSON 现状
- [`./0004-carrier-account-custom-fields-json.md`](./0004-carrier-account-custom-fields-json.md) — JSON 选型 ADR
- [`./0005-custom-field-multiselect.md`](./0005-custom-field-multiselect.md) — multiselect ADR

## 落地(2026-09-07)

代码已按本 ADR 完整实现,分 11 个原子 commit:

| Commit | 内容 |
|--------|------|
| Domain (1.0.15) | `CustomAttribute` + `CustomAttributeCollection` |
| DB 迁移 (1.0.14→1.0.15) | `xfe_carrier_custom_attribute` + `custom_fields_json` 加到承运商/LOGO |
| L2 Model / Resource / Collection | Mage 模型 + Resource + Resource/Collection |
| L3 Service | `CustomAttributeService` + Registry 注册 |
| Model 便捷访问器 | 承运商/LOGO Model 加 `setCustomFieldsJson` / `getCustomField()` |
| 严格模式 Applier | `CustomAttributeApplierAbstract` + Carrier/Logo/Account/FtpAccount 4 个具体类 |
| 后台 UI | Controller + Block + Template + Layout + 菜单 |
| 4 个编辑页 phtml 改造 | 账号/FTP/承运商/LOGO 只能下拉选 |
| Importer + Exporter + 路由 | 11 列 CSV upsert + 软删除激活 |
| 单测 | Domain + Service 严格模式 + Im/Ex 共 4 个测试文件,全部 ALL PASS |
| 文档 | 本文档 + `carrier-global-custom-field-defs.md`(本文件配套) |

旧版 `0006-global-custom-field-defs.md`(单分类、向后兼容、仅警告)已**废弃**,被本文件替代。

## 修订记录

| 日期 | 变更 | 作者 |
|------|------|------|
| 2026-09-07 | 初版:4 分类(承运商/账号/FTP/LOGO)统一属性管控,严格模式,必填校验 | AI 助手 |
| 2026-09-07 | 状态从 Proposed 升 Accepted;新增"落地"章节列举 11 个 commit | AI 助手 |
