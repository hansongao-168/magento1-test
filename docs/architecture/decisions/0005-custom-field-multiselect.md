# 0005. 自定义字段: 新增 multiselect 类型 + "自由标签输入"

- 状态: **Accepted**
- 日期: 2026-09-02
- 决策者: hanson.gao + AI 助手
- 关联 ADR: [0004-carrier-account-custom-fields-json.md](./0004-carrier-account-custom-fields-json.md)
- 关联章节: [carrier-account-custom-fields.md — §6 字段类型](../carrier-account-custom-fields.md)

## 背景

`XFE_Carrier_Domain_CustomField` 现有 4 种 `type`: `text` / `number` / `select` / `boolean`。
在 0004 ADR 中明确:**自定义字段"以 JSON 整列存储,不做按字段查询"**。但承运商业务的扩展字段
有一类常见形态 —— **多选标签** (multiselect / tag):

- "支持区域" = 多个区域(华东 / 华北 / 华南...)
- "适用服务等级" = 多个 service level(standard / express / economy)
- "可用 API 端点" = 多个 base URL(沙箱 + 生产 + 灾备)

如果只有 `select`(单选),用户被迫拆成多个键(`area_1` / `area_2` / `area_3`),
**违反"键有业务含义"原则**;如果强制每种组合都新建一行,会污染数据。

## 决策

新增 `type = 'multiselect'`,并采取**"双形态 UI"** 策略:

### 1. 新增类型

- 在 `XFE_Carrier_Domain_CustomField` 新增 `TYPE_MULTISELECT = 'multiselect'`,
  加入 `ALLOWED_TYPES`。
- `value` 形态: `string[]`(字符串数组,允许 `[]` 空数组)。`null` 在序列化时规范化为 `[]`。
- `options` 行为: **可选** —— 空数组时变"自由标签输入",非空时变"强制从 options 选"。

### 2. UI 双形态

由 **候选项(options)是否为空** 自动切换:

| 场景 | options | value UI | 适用 |
|------|---------|---------|------|
| A | `[]` (空) | chip + free input(自由添加/删除) | "支持区域"这类业务方自主声明的标签 |
| B | `['a','b','c']` (非空) | 原生 `<select multiple>`(Ctrl/Shift 多选) | "服务等级"这类必须从预设选的 |

**用户切换 type 时**,UI 跟着重新渲染 value 单元格(同现有 select / text / boolean 的处理方式)。

### 3. 序列化

- **JSON**: 整列存 `{"key":{"label":"...","type":"multiselect","value":["a","b"],"options":[]}}`,
  与现有 `text` / `number` 等**完全同构**,无需新 codec,`CustomFieldCodec` 零修改。
- **CSV (Importer/Exporter)**: `string[]` 用 **`|`** 分隔输出
  (e.g. `value|a|b|c`),与现有 `custom_fields_json` 列**分开**仍存 raw JSON。
  理由:`|` 不会与 JSON 引号 / 逗号冲突,且在 CSV 里靠字段加引号天然保护。
  **不**使用 `,` 是为了避免与 options 内含逗号产生歧义。

### 4. 后端校验规则

- **去重**: 同 key 重复提交,后一条覆盖前一条(用户已选 `dedupe_only`)。
- **不限制** value 个数上下限(YAGNI): 若未来有需要,再加 `min` / `max` 字段。
- **type=multiselect + value 含 options 外的元素**: 不抛错(允许场景 A 自由输入);
  但 **type=multiselect + options 非空** 时,value 必须 ⊆ options(与 `select` 一致)。

## 备选方案

### 方案 B: 始终强制从预定义 options 多选,不支持自由输入

- 优点: 实现最简,UI 只需要一个 `<select multiple>`。
- 缺点: 业务场景"支持区域"无法用,因为新地区不断新增,options 要不断维护。
  用户在 brainstorm 中明确选择 `free_input`,不采纳。

### 方案 C: 拆成两个独立 type, `multiselect_fixed` + `multiselect_free`

- 优点: Domain 模型更"纯"。
- 缺点: 多余的 type 名,只在 UI 上有区别,Domain 层完全同构;
  PHTML 编辑器要写两份,得不偿失。否决。

### 方案 D: value 存字符串,内部用 `,` 分隔

- 优点: 序列化最简。
- 缺点: 选项值若含 `,` 会歧义(虽然 `CustomField::coerceValue` 当前强制
  options 是简单字符串,但限制太死)。否决。

## 后果

- **正面**:
  - 业务方能声明"任意多选",覆盖了 5~6 个真实场景,不必再拆键。
  - JSON 存储零修改,所有现有 round-trip / Importer / Exporter 路径无回归。
  - 自由输入与强制选择由 options 单一字段控制,Domain 模型保持简洁。
- **负面**:
  - PHTML 编辑器要新增 chip input 的 JS(估算 +60 行代码)。
  - 自由输入存在"拼写不一致"风险(用户可能输"华东"和"华东 "两种值)—
    接受这一风险,在文档里提示"自由值由输入方自定规范"。

## 兼容性

- **对 0004 决策无影响**: 仍是单列 JSON,仍不做按字段查询。
- **向后兼容**: 现有 4 种 type 的 JSON 文档无须任何修改。
- **CSV 模板**会新增示例行,旧模板仍可被旧 Importer 解析(`multiselect` 行的
  value 会被解析为 `|` 分隔的字符串数组)。
