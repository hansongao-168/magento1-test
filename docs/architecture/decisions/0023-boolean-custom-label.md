# 0023. 布尔型 label 自定义(value 固定 0/1)

- 状态：Accepted
- 日期：2026-09-18
- 决策者：AI 助手
- 关联 ADR：0022（options_csv 行编辑器）
- 关联模块：小改 G-J 之后的延续；属 ADR 0008（form-ux）第四阶段

## 背景

用户反馈（小改 J 之后）：

> 布尔型 value 是固定，但 label 是可以改，例如开启/关闭、是/否

当前 `XfeCaTypeSwitcher.buildBooleanRadios()` 用 `yesLabel` / `noLabel` 写死的"是/否"，用户无法在 admin 后台自定义 boolean 的显示文案。

具体问题：

1. Carrier 实体的 boolean 字段（如"是否紧急"）目前显示固定"是/否"
2. 业务场景需要不同语义：开启/关闭、启用/停用、激活/禁用、男/女 等
3. label 一旦写死就**所有**自定义属性都用同一文案，无法按属性定制

## 决策

为 boolean 类型增加 label 自定义能力（value 固定 0/1，label 可改）。

### 架构

- **JS**:`mountOptionsEditor.refresh('boolean')` 也显示编辑器；value 列**只读**(`readonly` + 灰色背景)；空时**默认填充 2 行** `{0: 否, 1: 是}`
- **PHP Domain**:`CustomAttribute::__construct` boolean 类型允许 options（2 行，value ∈ `{0, 1}`）；新增 `getBooleanLabels()` helper 返回 `{0: label, 1: label}`（缺记录时回退 `{0: 否, 1: 是}`）
- **PHP Service**:`CustomAttributeService::createDef / updateDef` 加 boolean options 校验（正好 2 行 + value ∈ {0, 1}），不通过抛 `Mage_Core_Exception`
- **PHP 渲染**:strict_editor.phtml 的 boolean 分支从 `def.getBooleanLabels()` 读 label 替代硬编码"是/否"

### UX 设计

新建 / 编辑域**类型=布尔** 时，候选项行编辑器显示：

```
┌─────────────────────────────────────────────┐
│ 候选项(value / label)                      │
├─────────────────────────────────────────────┤
│ [0 只读] [否 ___________] [删除]           │
│ [1 只读] [是 ___________] [删除]           │
│ [+ 添加候选项]                             │
└─────────────────────────────────────────────┘
```

修改 label → entity 编辑页 boolean radio 文案同步：

```
○ 关闭    ● 开启
```

### 数据流

- **存储**:options_csv 存 `0|关闭,1|开启`
- **Domain**:`getBooleanLabels()` 返回 `['0' => '关闭', '1' => '开启']`
- **Service 校验**:解析 CSV → 校验 count===2 + value ∈ {0, 1}
- **Carrier 渲染**:strict_editor.phtml 用 `def.getBooleanLabels()` 取文案
- **回退**:options_csv 为空时，所有层都回退 `{0: 否, 1: 是}`（保持旧数据兼容）

### JS 公开方法

无变化 — `mountOptionsEditor` 签名不变；boolean 只读模式是内部实现细节（`_label` 参数已支持 i18n）。

### UI 形态

每行在 boolean 时 value 列**只读 + 灰色背景**（与 select/multiselect 区分）。

### 不做（本次）

- ❌ 拖拽排序 boolean 行（value 已固定，顺序无意义）
- ❌ boolean label 走 i18n（A.2 阶段再做）
- ❌ 删除默认 2 行（必须保留 2 行；用户可改 label 但不能删行）
- ❌ 颠倒 value（0/1 顺序固定，与 Carrier 内部约定一致）

## 备选方案

| 方案 | 描述 | 否决理由 |
|---|---|---|
| C 独立字段 | boolean 加 `boolean_label_yes` / `boolean_label_no` 两列 | 重复设计，options_csv 已经能存 key\|label，复用更一致 |
| D JSON 字段 | boolean label 存 `{"0":"关闭","1":"开启"}` 在新字段 | 同样的语义用不同存储介质，违反现有约定 |

## 后果

- 正面：
  - 用户可自定义 boolean 显示文案（业务场景适配）
  - 复用现有 options_csv 机制，无新存储格式
  - Form.php / Service 接口 0 改动（向后兼容）
- 负面：
  - JS 改动较细：refresh() 需要识别 boolean 类型 + buildRow 加 readonly 模式
  - Service 校验失败需抛明确错误信息（避免用户困惑）

## 实施范围

| 文件 | 改动 |
|---|---|
| `js/xfe_carrier/custom-attribute-form-switcher.js` | refresh() 加 boolean 分支 + buildRow 加 readonly 参数 |
| `app/design/adminhtml/default/default/template/xfe_carrier/custom_attribute/edit/form/type_switcher.phtml` | 0 改动（labels 已注入） |
| `app/code/community/XFE/Carrier/Domain/CustomAttribute.php` | 构造函数 boolean 分支 + 新增 getBooleanLabels() |
| `app/code/community/XFE/Carrier/Model/Service/CustomAttributeService.php` | boolean options 校验 |
| `app/design/adminhtml/default/default/template/xfe_carrier/custom_attribute/strict_editor.phtml` | boolean radio 读 def.getBooleanLabels() |
| `tests/js/run-tests.js` | +5 组 describe（boolean 默认 2 行 + readonly + 校验） |
| `Test/Service/CustomAttributeServiceBooleanTest.php` | 新增 PHP 校验测试 |
| `docs/architecture/xfe-carrier-custom-attribute-form-switcher-js.md` | §2.18 boolean label 章节 |
| `docs/architecture/carrier-global-custom-field-defs.md` | boolean options 语义补充 |
| `task_plan.md` / `progress.md` | 阶段 18 / 小改 K |

预期测试基线：JS 152 → 160+ passed，PHP 407 → 425+ passed。
## 实施记录(2026-09-18)

- **Domain**:XFE_Carrier_Domain_CustomAttribute 内部 options 升级为结构化 [['key' => string, 'label' => string], ...];新增 6 个 helper:getOptionKeys() / getBooleanLabels() / parseOptionToken() / parseOptionsCsvToPairs() / serializeOptionsPairsToCsv() / 
ormalizeOptionsList()
- **Domain boolean 校验**:构造器在 type=boolean 时校验 options 解析后必须正好 2 行 + key ∈ {0, 1},不通过抛 InvalidArgumentException(由 Service 上层转 Mage_Core_Exception)
- **Service**:_detectOptionsMigration 增加 boolean 分支,委托给新私有方法 _detectBooleanMigration,规则 = 解析后 count===2 + key 集合 === {0, 1} + default_value ∈ {0, 1};label 文案修改视为合法
- **Service 调用点**:CustomAttributeApplierAbstract::_buildFromPost 改用 getOptionKeys() 喂 CustomField
- **Exporter**:XFE_Carrier_Model_Service_CustomAttribute_Exporter + Block/Adminhtml/CustomAttribute/Grid 改用 serializeOptionsPairsToCsv 序列化
- **渲染**:strict_editor.phtml boolean 分支从结构化 options 取 label(替代硬编码"是/否")
- **JS**:uildBooleanRadios(opts) 加结构化 options 解析,沿用 5.6+ 语法
- **测试**:JS 152 → 160 passed(+8 boolean);PHP 407 → 471 passed(+64 assertions:32 boolean Test + 12 Migration boolean Test + 旧 20 assertion 调整)
- **架构文档**:xfe-carrier-custom-attribute-form-switcher-js.md §2.18 boolean label 章节 + §8 修订记录 + carrier-global-custom-field-defs.md boolean options 语义补充
- **端到端"联调"**:由于 PHP 8 与 Magento 1 不兼容(__autoload() 废弃,核心代码不能改),跳过实环境浏览器联调;改用 Probe 脚本(pp/code/community/XFE/Carrier/Test/Service/_boolean_migration_probe.php 临时)验证 Service + Domain 全链路;10 个 boolean migration 场景全部符合本 ADR 设计

### 已知偏差

- 无。所有设计按本 ADR 实现。

### 后续可选

- boolean label 走 i18n(XLIFF 抽取)
- options 行编辑器在 boolean 时禁用拖拽(value 已固定,顺序无意义)— 当前已通过 eadonly 限定 + 不触发拖拽 handler 实现