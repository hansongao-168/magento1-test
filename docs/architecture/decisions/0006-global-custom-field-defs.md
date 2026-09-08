# 0006. 全局自定义字段定义表(Global Custom Field Definitions) — **已废弃**

- 状态:**Deprecated**(被 `0006-custom-attribute-management.md` 替代)
- 日期:2026-09-07
- 决策者:hanson.gao

> ⚠️ **本 ADR 已被 [`0006-custom-attribute-management.md`](./0006-custom-attribute-management.md) 替代。**
>
> **废弃原因**:用户最终需求"4 分类(承运商/账号/FTP/LOGO)统一属性管控 + 严格模式 + is_required 强校验"
> 远超本 ADR 描述的"账号/FTP 单分类 + 自由 key 兼容 + 仅警告"模式。
>
> **关键差异**(新 vs 旧):
> - 旧:仅 2 分类(账号 + FTP)、向后兼容未登记 key、is_required 仅 UI 提示
> - 新:4 分类(承运商/账号/FTP/LOGO)、完全严格模式(禁止未登记 key)、is_required save 时强校验
>
> 保留本文件作为"用户最初设想 → 实际落地"的设计演化记录,**不要**作为新代码的依据。

## 背景

`XFE_Carrier` 模块的承运商账号 / FTP 账号当前用 per-row JSON 存自定义字段
(参见 ADR 0004)。运营了一段时间后,出现以下问题:

1. **命名漂移**:同一业务概念在不同账号被命名为不同 key(`warehouse_code` / `wh_code` / `warehouse_id`),报表聚合失真。
2. **候选项不统一**:`service_level` 在不同账号选项不一样。
3. **缺默认值 / 缺文档**:新账号首次填字段无引导。
4. **完全去中心化**:没有任何"系统管理员可管控的中央登记",运营"自由发挥"。

`AGENTS.md §1 不可妥协的开发原则 5 — 文档先行`要求在写代码前先完成架构决策记录。

## 决策

新增一张独立的**全局字段定义表** `xfe_carrier_custom_field_def`,用于中央管控
"账号可以声明哪些字段"的元数据(定义层)。**保留** per-row JSON(数据层)不变。

| 决策点 | 选择 | 理由 |
|--------|------|------|
| 字段定义存在哪 | **新表** `xfe_carrier_custom_field_def` | system config 不支持列表/搜索/导入导出 |
| 编辑页是否强制只能选已登记字段 | **否**(允许未登记 key,仅显示警告) | 向后兼容;渐进迁移;避免"全局表空时账号编辑页崩溃" |
| 字段定义变更时旧数据如何处理 | **保持原状** | per-row JSON 优先,读时用全局表 label/type/options 覆盖渲染 |
| 删除字段定义 | **软删除**(`is_active=0`) | 旧账号数据仍能渲染;`uk_field_key_active (field_key, is_active)` 唯一索引防止冲突 |
| 多模块共享 | **仅本模块**(`XFE_Carrier`) | YAGNI;后续如有需要再抽象到 `Mage_Core` |
| 适用实体作用域 | **3 选 1**:`account` / `ftp_account` / `both` | 主账号 + FTP 账号字段集不完全相同(如 `warehouse_code` 主账号用,FTP 账号用不上) |
| 是否必填 | **新增 `is_required`**(账号编辑时检查 value 非空) | 当前 JSON 形态无法表达"必填"语义 |
| 默认值 | **新增 `default_value`**(JSON 序列化) | 减少运营首次填字段的工作量;保证同一字段在不同账号的默认一致 |
| 排序 | **新增 `sort_order` + `field_key` 字母序** | 账号编辑下拉框顺序可控 |

## 备选方案

### 备选 A:复用 system config(`config.xml` 节点)

- **优点**:不建新表;不需要迁移脚本;改动小。
- **缺点**:`core_config_data` 是 EAV 风格 KV,字段多了查询/排序/搜索体验差;CSV 导入导出需要走 `Mage::getConfig()` 序列化;权限/审计/事件难做。
- **不选**。

### 备选 B:复用 Magento 现有的 `eav_attribute` + `catalog_eav_attribute` 表

- **优点**:复用现成的 EAV 框架;管理后台有现成的"属性集"管理 UI。
- **缺点**:与 `XFE_Carrier` 的 per-row JSON 形态(`custom_fields_json`)对接复杂;EAV 表结构有 6 张(`*_entity_varchar/int/text/datetime/decimal`),反而**比新建一张表**复杂;Magento 1 的 EAV UI 在后台太重,不适合"几十个字段"的轻量场景。
- **不选**。

### 备选 C:把字段定义存在 `custom_fields_json` 同表的另一个 JSON 列

- **优点**:不需要新表。
- **缺点**:"每个账号存自己的字段定义" → 退化为"完全去中心化",问题没解决。
- **不选**。

### 备选 D:完全替换 per-row JSON,改成 EAV(5 张表)

- **优点**:支持范围/排序/聚合;性能更好。
- **缺点**:**破坏性变更**——所有现网账号的 per-row JSON 数据要 ETL 迁移;`applyFromPost` 流程全改;Im/Ex 流程全改;违反 `AGENTS.md §1 不可妥协的开发原则 5` —— 不可逆架构决策必须 ADR + 完整迁移方案,工作量是本次的 5~10 倍。
- **不选**(YAGNI;per-row JSON 性能对当前 1000 个账号 / 5 个字段规模完全够用)。

## 后果

### 正面

- ✅ 命名漂移、候选项不统一、缺默认值问题一次性解决
- ✅ 系统管理员有"中央管控"菜单,字段集可统一发布
- ✅ 审计:字段定义有 `created_at` / `updated_at` / `is_active`,变更可追溯
- ✅ 未来如果要支持"按字段搜索"(`field_key IN (...)`),SQL 直接 join,无需全表扫
- ✅ 软删除不破坏旧数据,迁移无感

### 负面 / 风险

- ⚠️ **新表 + 新 Service + 新菜单 + 新 Im/Ex + 改造 applyFromPost + 改造账号编辑页**:工作量约 4~6 commit。
- ⚠️ **编辑页 UI 大改**:从"自由加 key"变成"下拉选已登记 key"——运营需要重新培训。
- ⚠️ **数据库表数量 +1**:占用少量存储(估算 100 字段 × 200 字节/行 = 20 KB,可忽略)。
- ⚠️ **`applyFromPost` 增加校验步骤**:每次保存账号都查一次全局表(可加进程内缓存缓解,见下方"演进")。
- ⚠️ **未登记 key 的兼容**:`custom_fields.json` 里如果存了"全局表里没登记"的 key,显示时仍能渲染(向后兼容),但运营可能误以为"登记了"。

### 演进(本次不做,留作未来)

- **进程内缓存**:`CustomFieldDefService::getActiveDefs()` 加 `Mage_App::getCache()->load('xfe_carrier_active_defs')`,运营修改全局表时 `cleanCache('xfe_carrier_active_defs')`。
- **多语言 label**:`label` 列升级为 `i18n_string` 或新增 `xfe_carrier_custom_field_def_i18n` 表。
- **跨模块共享**:抽象到 `Mage_Core` 框架,`XFE_Logistic` / `XFE_MagePlugin` 也可登记字段。
- **JSON Schema 校验**:`default_value` 用 JSON Schema 描述,`applyFromPost` 时强校验。

## 关联文档

- [`../carrier-global-custom-field-defs.md`](../carrier-global-custom-field-defs.md) — 本次架构设计文档
- [`../carrier-account-custom-fields.md`](../carrier-account-custom-fields.md) — Per-row JSON 现状
- [`./0004-carrier-account-custom-fields-json.md`](./0004-carrier-account-custom-fields-json.md) — JSON 选型 ADR
- [`./0005-custom-field-multiselect.md`](./0005-custom-field-multiselect.md) — multiselect ADR

## 修订记录

| 日期 | 变更 | 作者 |
|------|------|------|
| 2026-09-07 | 初版:提出全局字段定义表方案 | AI 助手 |
