# 承运商账号与 FTP账号批量导入导出

## 1. 目的

为 `XFE_Carrier` 模块增加**账号（Account）**与 **FTP账号（FtpAccount）** 的批量导入与批量导出能力，便于在多个环境（生产/测试/新站点）之间迁移账号配置。

本改动属于 `XFE_Carrier` 模块内部行为扩展，不新增数据库字段，不改变账号表的持久化结构，也不改变其他模块对 `XFE_Carrier` 的调用方式。

## 2. 设计范围

### 2.1 功能入口

参照已有的"规则批量导入导出"，在承运商列表页 `XFE_Carrier_Block_Adminhtml_Carrier`（Grid Container）的按钮区新增四个按钮：

- **账号批量导入**：跳转到账号导入上传页。
- **账号批量导出**：导出全部承运商的账号为 CSV 附件。
- **FTP账号批量导入**：跳转到 FTP账号导入上传页。
- **FTP账号批量导出**：导出全部承运商的 FTP账号为 CSV 附件。

账号与 FTP账号均被分散在承运商编辑页的子 Tab 中管理，没有独立的全局账号管理页，因此将导入/导出入口统一放在承运商列表页，最贴合现有 UI（与规则导入导出一致）。

### 2.2 账号 CSV 格式（每行一条账号）

| 列 | 必填 | 说明 |
|----|------|------|
| `carrier_code` | 是 | 承运商标识代码，用于定位 `carrier_id`；无法定位则跳过该行 |
| `account_name` | 是 | 账号名称；同一承运商下与 `account_no` 共同作为去重键（upsert） |
| `account_no` | 是 | 账号编号；同一承运商下与 `account_name` 共同作为去重键 |
| `api_key` | 否 | API Key |
| `api_secret` | 否 | API Secret |
| `username` | 否 | 登录用户名 |
| `password` | 否 | 登录密码 |
| `endpoint_url` | 否 | API 端点 URL |
| `status` | 否 | 1 启用 / 0 禁用，默认 1 |
| `sort_order` | 否 | 排序，默认 0 |
| `note` | 否 | 备注 |

> 敏感字段（`api_key` / `api_secret` / `password`）在导入导出中按原样保存/导出。导出功能面向环境迁移场景，保留这些字段是迁移的前提；如需脱敏请在导出后自行处理文件。

### 2.3 FTP账号 CSV 格式（每行一条 FTP账号）

| 列 | 必填 | 说明 |
|----|------|------|
| `carrier_code` | 是 | 承运商标识代码，用于定位 `carrier_id`；无法定位则跳过该行 |
| `account_name` | 是 | 账号名称；同一承运商下与 `account_no` 共同作为去重键（upsert） |
| `account_no` | 否 | 账号编号 |
| `protocol` | 否 | `ftp`/`sftp`/`ftps`，默认 `ftp` |
| `host` | 是 | FTP 主机 |
| `port` | 否 | 端口，默认 21 |
| `username` | 否 | 登录用户名 |
| `password` | 否 | 登录密码 |
| `remote_path` | 否 | 远程路径 |
| `mode` | 否 | `passive`/`active`，默认 `passive` |
| `encoding` | 否 | 字符编码，默认 `UTF-8` |
| `status` | 否 | 1 启用 / 0 禁用，默认 1 |
| `sort_order` | 否 | 排序，默认 0 |
| `note` | 否 | 备注 |

## 3. 导入服务

### 3.1 账号导入

新增 `XFE_Carrier_Model_Service_Account_Importer`，职责：

- 解析 CSV（复用 `fgetcsv` 读取逻辑，与 `XFE_Carrier_Model_Service_Rule_Importer` 风格一致）。
- 每条记录通过 `carrier_code` 定位承运商。
- **Upsert 语义**：同一承运商下 `account_name` 与 `account_no` 均相同的记录更新其字段，否则新增。
- 通过 `XFE_Carrier_Model_Carrier_Account` 模型持久化（模型的 `_beforeSave()` 负责维护 `created_at` / `updated_at`）。
- 返回 `XFE_Carrier_Model_Service_Account_Importer_Result`（`created`/`updated`/`skipped`/`errors`）。

依赖方向（单向）：

```
Account_Importer ─▶ Carrier_Account（DB 实体，写入）
                 ─▶ Carrier        （通过 code 定位，仅读取）
```

### 3.2 FTP账号导入

新增 `XFE_Carrier_Model_Service_FtpAccount_Importer`，职责同上，但写入 `XFE_Carrier_Model_Carrier_FtpAccount`，字段集合为 2.3 节所列。

返回 `XFE_Carrier_Model_Service_FtpAccount_Importer_Result`。

依赖方向（单向）：

```
FtpAccount_Importer ─▶ Carrier_FtpAccount（DB 实体，写入）
                    ─▶ Carrier            （通过 code 定位，仅读取）
```

## 4. 导出服务

### 4.1 账号导出

新增 `XFE_Carrier_Model_Service_Account_Exporter`，职责：

- 加载全部承运商账号（`xfe_carrier/carrier_account` 集合）。
- 生成 CSV 行：每行一条账号，`carrier_code` 由 `carrier_id` 反查承运商 `code`。
- 返回 CSV 字符串，交由 Controller 流式输出为附件下载。

导出不含 `account_id` 等系统自增字段，保证导入到新环境时干净重建。

### 4.2 FTP账号导出

新增 `XFE_Carrier_Model_Service_FtpAccount_Exporter`，职责同上，加载 `xfe_carrier/carrier_ftp_account` 集合，字段集合为 2.3 节所列。

## 5. Controller 动作

`XFE_Carrier_Adminhtml_CarrierController` 新增（每个实体各一套，前缀 `account` / `ftpAccount`）：

- `accountImportAction()`：渲染账号导入上传页。
- `accountImportPostAction()`：校验 `form_key`，调用 `Account_Importer::importUpload()`，注册结果并跳转结果页。
- `accountImportResultAction()`：渲染导入结果页。
- `accountDownloadTemplateAction()`：流式输出账号导入 CSV 模板。
- `accountExportAction()`：调用 `Account_Exporter`，流式输出全部账号 CSV。
- `ftpAccountImportAction()` 等：FTP账号同名动作。

## 6. Block 与模板

- `XFE_Carrier_Block_Adminhtml_Carrier_Account_Import`（容器，模板 `xfe_carrier/carrier/account/import/container.phtml`）
- `XFE_Carrier_Block_Adminhtml_Carrier_Account_Import_Form`（上传表单，模板 `.../form.phtml`）
- `XFE_Carrier_Block_Adminhtml_Carrier_Account_Import_Result`（结果页，模板 `.../result.phtml`）
- FTP账号对应：`XFE_Carrier_Block_Adminhtml_Carrier_FtpAccount_Import` 及其 `Form` / `Result`，模板置于 `xfe_carrier/carrier/ftpaccount/import/`。

布局 handle 注册于 `app/design/adminhtml/default/default/layout/xfecarrier.xml`。

## 7. 依赖与边界

- 导入导出服务只依赖 `Carrier`（读取定位 code）、`Carrier_Account` / `Carrier_FtpAccount`（DB 实体），不修改承运商。
- 导出使用 UTF-8 with BOM，确保 Excel 直接打开中文不乱码。
- 不新增数据库升级脚本。
- **PHP 版本兼容**：`fputcsv` / `fgetcsv` 的 `$escape` 参数存在跨版本差异——PHP ≥ 8.4 中空字符串表示"禁用转义"（不传会 Deprecated），PHP < 8.4 中空字符串会被拒绝。导入/导出服务封装 `_fputcsv()` / `_fgetcsv()` 帮助方法，按 `PHP_VERSION_ID >= 80400` 决定是否传空字符串，保证 PHP 7.3（生产环境）与 PHP 8.5（开发环境）均可正确工作。

## 8. 验证要求

- 覆盖导入：合法新增、同名更新、缺失 `carrier_code`、`carrier_code` 不存在、空文件。
- 覆盖导出：全部字段。
- 覆盖 round-trip：导出的 CSV 可通过导入功能完整还原（含 BOM）。
- 新增/修改 PHP 文件执行 `php -l`。
