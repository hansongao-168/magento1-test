# 0030. Resource 级规则的 Grid 每行「+ 添加规则」弹窗入口

- 状态：Accepted
- 日期：2026-09-21
- 决策者：hanson.gao + AI 助手

## 背景

ADR 0029 在 3 个 Resource Grid（Logo / Account / FtpAccount）顶部工具条加了「+ 添加规则」按钮，但只能加 carrier 级规则。

实际业务场景：账号管理列表里有 N 个账号，需要针对某个具体账号加规则。用户反馈「点击第 2 个，就可以添加第 2 个账号管理的规则」。

## 现状问题

### 1. `xfe_carrier_carrier_rule` 表缺 `logo_id` 列

- `account_id`：1.0.6-1.0.7 加（可用）
- `ftp_account_id`：1.0.9-1.0.10 加（可用）
- `logo_id`：【未加】

`editRuleAction()` 接收 `account_id` / `ftp_account_id` 参数写入 model，但 `logo_id` 由于只能通过 `addData($data)` 写入 model，但表中没有该列，保存时会被丢弃。

### 2. `Logo/Rules/Grid` collection 过滤不准

只按 `module_code=logo` 过滤，没有按 `logo_id` 过滤。意味着某个 logo 的规则列表会显示该 carrier 下所有 logo 级规则（错误）。

### 3. 按钮仅在 Grid 顶部

原 ADR 0029 的「+ 添加规则」在 content-header，只能加 carrier 级规则。用户要求「点击某行就为该行加」。

## 决策

### 1. DB：加 `logo_id` 列

新建 `upgrade-1.0.15-1.0.16.php`，在 `xfe_carrier_carrier_rule` 表加 `logo_id INT UNSIGNED NULL DEFAULT NULL` + 索引 + FK（幂等检查）。

### 2. Controller：接收并保存 `logo_id`

- `editRuleAction()` 接收 `logo_id`，写入 model
- `saveRuleAction()` 保存后 redirect 支持 logo_id 走回 editLogo 页面

### 3. Resource Rules Grid collection：按 resource_id 过滤

- `Logo/Rules/Grid` 加 `addFieldToFilter('logo_id', (int)$logo->getId())`
- `Account/Rules/Grid` 已有。
- `FtpAccount/Rules/Grid` 已有。

### 4. Resource Grid 每行加「+ 添加规则」按钮

新建一个共享的 Column Renderer（`XFE_Carrier_Block_Adminhtml_Carrier_Grid_Column_Renderer_AddRule`），输出一个绿色「+ 添加规则」按钮，onclick 调用 `XFE_CarrierRuleModal.open(...)`。

不复用 Magento action column 的 select 下拉：用户要求「点击某行就能加」，需要平铺按钮。

### 5. 幂等与向后兼容

- DB 升级幂等（tableColumnExists 检查）
- 已有规则数据 logo_id 全为 NULL （默认 carrier 级）不受影响
- Resource Rules Grid 之前的 collection 过滤表达式会被加索引提速（FK 加后查询依然 OK）

## 备选方案

### A. 在 action 列加一项（会被 select 化）

最少代码，但 3 个 action 会变成 select 下拉，UX 略差。
拒绝原因：用户原语「点击第 2 个」表示期望直接可点。

### B. 在 Grid 外面插入按钮列（用 JS 注入）

全部用 prototype 改 DOM，侵入大，不适合长期维护。
拒绝原因：必要的复杂度超过业务价值。

### C. 选 B：自定义 Column Renderer

清洁、平铺、与 Magento action column 规范兼容（filter=false, sortable=false）。选用。

## 后果

- 正面：3 个 Resource Grid 都可以「点某行 → 加某行的规则」；Resource Rules Grid 能准确列出「该 resource 的规则」
- 负面：多 1 个 schema 字段 + 1 个 column renderer。升级脚本幂等可重跑。

## 影响面

- `app/code/community/XFE/Carrier/sql/xfe_carrier_setup/upgrade-1.0.15-1.0.16.php` （新建）
- `app/code/community/XFE/Carrier/etc/config.xml`（version 1.0.19 → 1.0.20）
- `app/code/community/XFE/Carrier/controllers/Adminhtml/CarrierController.php`（editRuleAction + saveRuleAction）
- `app/code/community/XFE/Carrier/Block/Adminhtml/Carrier/Edit/Tab/Logo/Rules/Grid.php`（collection filter）
- `app/code/community/XFE/Carrier/Block/Adminhtml/Carrier/Edit/Tab/Logo/Grid.php`（add add_rule column）
- `app/code/community/XFE/Carrier/Block/Adminhtml/Carrier/Edit/Tab/Account/Grid.php`（add add_rule column）
- `app/code/community/XFE/Carrier/Block/Adminhtml/Carrier/Edit/Tab/FtpAccount/Grid.php`（add add_rule column）
- `app/code/community/XFE/Carrier/Block/Adminhtml/Carrier/Grid/Column/Renderer/AddRule.php`（新建）
- `docs/architecture/carrier-rule-conditions.md` （补充 §6.8）
- `task_plan.md` / `progress.md`
