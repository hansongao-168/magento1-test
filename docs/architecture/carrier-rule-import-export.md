# 承运商规则批量导入与导出

## 1. 目的

为 `XFE_Carrier` 模块增加**承运商规则**的批量导入与批量导出能力，便于在多个环境（生产/测试/新站点）之间迁移规则配置。

本改动属于 `XFE_Carrier` 模块内部行为扩展，不新增数据库字段，不改变规则与条件树的持久化结构，也不改变其他模块对 `XFE_Carrier` 的调用方式。

## 2. 设计范围

### 2.1 功能入口

在承运商列表页 `XFE_Carrier_Block_Adminhtml_Carrier`（Grid Container）的按钮区新增两个按钮：

- **规则批量导入**：跳转到规则导入上传页。
- **规则批量导出**：导出全部承运商的规则为 CSV 附件。

规则被分散在承运商/账号编辑页管理，没有独立全局规则管理页，因此将导入/导出入口统一放在承运商列表页，管理全局规则，最贴合现有 UI。

### 2.2 CSV 格式（每行一条规则）

| 列 | 必填 | 说明 |
|----|------|------|
| `carrier_code` | 是 | 承运商标识代码，用于定位 `carrier_id`；无法定位则跳过该行 |
| `module_code` | 否 | 关联模块代码（`logo`/`account` 等，来自 `carrier_modules.xml`） |
| `name` | 是 | 规则名称；同一承运商下作为去重键（upsert） |
| `description` | 否 | 规则描述 |
| `status` | 否 | 1 启用 / 0 禁用，默认 1 |
| `is_cancel_on_failure` | 否 | 0/1，默认 0 |
| `sort_order` | 否 | 排序，默认 0 |
| `priority` | 否 | 解析优先级，默认 0 |
| `account_code` | 否 | 账号编号（`account_no`），用于绑定规则到具体账号；定位不到则留空 |
| `conditions_json` | 否 | 条件树 JSON 字符串；为空表示匹配所有 |

### 2.3 条件树序列化

条件树形状与资源模型加载出的 `conditions_data` 完全一致：

```json
[
  {"aggregator": "all", "conditions": [
      {"attribute": "country_code", "operator": "==", "value": "US"}
  ]}
]
```

嵌套子组为 `{"type": "group", "aggregator": "any", "conditions": [...]}`。导入时解析为数组后交给规则模型持久化；导出时由规则模型 `getConditionsData()` 直接 JSON 编码。`conditions_json` 解析失败则该行跳过并报错。

## 3. 导入服务

新增 `XFE_Carrier_Model_Service_Rule_Importer`，职责：

- 解析 CSV（复用 `fgetcsv` 读取逻辑，与 `XFE_Carrier_Model_Service_Importer` 风格一致）。
- 每条规则通过 `carrier_code` 定位承运商，通过 `account_code`（`account_no`）定位账号。
- **Upsert 语义**：同一承运商下已存在同名规则则更新其字段与条件树，否则新增。
- 通过 `XFE_Carrier_Model_Carrier_Rule` 模型持久化（模型 `_afterSave()` 负责写条件树），不重复实现条件树写入逻辑。
- 返回 `XFE_Carrier_Model_Service_Rule_Importer_Result`（`created`/`updated`/`skipped`/`errors`）。

依赖方向（单向）：

```
Rule_Importer ─▶ Carrier_Rule（DB 实体）
            ─▶ Carrier      （通过 code 定位，仅读取）
            ─▶ Carrier_Account（通过 account_no 定位，仅读取）
```

## 4. 导出服务

新增 `XFE_Carrier_Model_Service_Rule_Exporter`，职责：

- 加载全部承运商规则（`carrier_rule` 集合），条件树通过每条规则模型的 `getConditionsData()` 读取。该方法自带兜底：集合行未加载条件树时自动触发 `afterLoad()` 补全（见 `carrier-rule-conditions.md` §4.3），因此不依赖任何集合级 `loadConditions()` 方法。
- 生成 CSV 行：每行一条规则，`carrier_code` 由 `carrier_id` 反查承运商 `code`，`account_code` 由 `account_id` 反查账号 `account_no`。
- 返回 CSV 字符串，交由 Controller 流式输出为附件下载。

导出不含 `rule_id` 等系统自增字段，保证导入到新环境时干净重建。

## 5. Controller 动作

`XFE_Carrier_Adminhtml_CarrierController` 新增：

- `ruleImportAction()`：渲染规则导入上传页。
- `ruleImportPostAction()`：校验 `form_key`，调用 `Rule_Importer::importUpload()`，注册结果并跳转结果页。
- `ruleImportResultAction()`：渲染导入结果页。
- `ruleDownloadTemplateAction()`：流式输出规则导入 CSV 模板。
- `ruleExportAction()`：调用 `Rule_Exporter`，流式输出全部规则 CSV。

## 6. Block 与模板

- `XFE_Carrier_Block_Adminhtml_Carrier_Rule_Import`（容器，模板 `xfe_carrier/carrier/rule/import/container.phtml`）
- `XFE_Carrier_Block_Adminhtml_Carrier_Rule_Import_Form`（上传表单，模板 `.../form.phtml`）
- `XFE_Carrier_Block_Adminhtml_Carrier_Rule_Import_Result`（结果页，模板 `.../result.phtml`）

布局 handle 注册于 `app/design/adminhtml/default/default/layout/xfecarrier.xml`。

## 7. 依赖与边界

- 规则模型负责读取/写入条件树；导入导出服务不自行拼接条件树 SQL。
- 导入服务依赖承运商/账号仅用于**读取定位**，不修改它们。
- 导出使用 UTF-8 with BOM，确保 Excel 直接打开中文不乱码。
- 不新增数据库升级脚本。
- **PHP 版本兼容**：`fputcsv` / `fgetcsv` 的 `$escape` 参数存在跨版本差异——PHP ≥ 8.4 中空字符串表示"禁用转义"（不传会 Deprecated），PHP < 8.4 中空字符串会被拒绝（`escape must be a character`，导致读写失败）。导入/导出服务封装 `_fputcsv()` / `_fgetcsv()` 帮助方法，按 `PHP_VERSION_ID >= 80400` 决定是否传空字符串，保证 PHP 7.3（生产环境）与 PHP 8.5（开发环境）均可正确工作。

## 8. 验证要求

- 覆盖导入：合法新增、同名更新、缺失 `carrier_code`/`name`、`carrier_code` 不存在、`conditions_json` 非法、空文件。
- 覆盖导出：全部字段、嵌套条件组、空条件树。
- 覆盖 round-trip：导出的 CSV 可通过导入功能完整还原（含 BOM、逗号/引号 JSON 字段）。
- 新增/修改 PHP 文件执行 `php -l`；独立测试在 PHP 7.3 与 PHP 8.5 下均通过。

## 8. 验证要求

- 覆盖导入：合法新增、同名更新、缺失 `carrier_code`/`name`、`carrier_code` 不存在、`conditions_json` 非法、空文件。
- 覆盖导出：全部字段、嵌套条件组、空条件树。
- 新增/修改 PHP 文件执行 `php -l`。
