# 承运商规则条件描述与订单创建时间 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在承运商规则列表显示条件描述，并增加订单创建时间条件。

**Architecture:** 保持现有规则持久化表不变，由规则模型提供统一条件描述格式化方法；订单上下文构建器注入 `order_created_at`，纯求值器负责时间比较语义；三个 Admin Grid 复用同一列配置。

**Tech Stack:** Magento 1.9 PHP、Prototype.js 条件编辑器、现有 Carrier 单元测试、PHP CLI 语法检查。

---

### Task 1: 条件描述与属性契约测试

**Files:**
- Create: `app/code/community/XFE/Carrier/Test/Model/RuleConditionDescriptionTest.php`
- Test: `app/code/community/XFE/Carrier/Test/Model/RuleConditionDescriptionTest.php`

- [ ] **Step 1: 写失败测试** 测试普通条件、嵌套组、空树、未知属性和 `is_null` 的文本输出，并测试 Helper 暴露 `order_created_at` 及其 `datetime` 元数据。
- [ ] **Step 2: 运行测试** 使用项目可用的 PHPUnit 入口执行该测试，确认实现缺失时按预期失败。
- [ ] **Step 3: 实现最小代码** 在规则模型增加递归描述格式化方法，在 Helper 增加属性和类型映射。
- [ ] **Step 4: 运行测试** 确认上述测试通过。

### Task 2: 订单创建时间上下文与求值测试

**Files:**
- Modify: `app/code/community/XFE/Carrier/Test/Service/Rule/OrderContextBuilderTest.php`
- Create: `app/code/community/XFE/Carrier/Test/Rule/OrderCreatedAtTest.php`

- [ ] **Step 1: 写失败测试** 断言订单 `created_at` 被映射到 `MatchContext`，并覆盖时间等于、前后比较、范围和非法值。
- [ ] **Step 2: 运行测试** 确认测试因缺少时间契约而失败。
- [ ] **Step 3: 实现最小代码** 在订单上下文构建器设置 `order_created_at`，在求值器增加时间解析和比较。
- [ ] **Step 4: 运行测试** 确认时间条件测试通过。

### Task 3: 三处规则列表列配置

**Files:**
- Modify: `app/code/community/XFE/Carrier/Block/Adminhtml/Carrier/Edit/Tab/Rules/Grid.php`
- Modify: `app/code/community/XFE/Carrier/Block/Adminhtml/Carrier/Edit/Tab/Account/Rules/Grid.php`
- Modify: `app/code/community/XFE/Carrier/Block/Adminhtml/Carrier/Edit/Tab/FtpAccount/Rules/Grid.php`

- [ ] **Step 1: 写静态验证检查** 确认三个 Grid 均存在条件描述列，且三处复用规则模型接口而不是复制格式化逻辑。
- [ ] **Step 2: 修改 Grid** 在“描述”列后增加“条件描述”列，使用 `rule_id` 作为过滤器索引或禁止排序，并使用 `frame_callback` 读取 `getConditionsDescription()`。
- [ ] **Step 3: 验证 PHP 语法** 对三个 Grid 执行 `php -l`。

### Task 4: 编辑器属性配置与整体验证

**Files:**
- Modify: `app/code/community/XFE/Carrier/Block/Adminhtml/Carrier/Rule/Edit/Tab/Conditions.php`
- Modify: `app/code/community/XFE/Carrier/Helper/Data.php`

- [ ] **Step 1: 更新编辑器元数据** 确认 `order_created_at` 进入属性下拉、类型映射和允许操作符集合。
- [ ] **Step 2: 执行测试与语法检查** 运行 Carrier 相关 PHPUnit 测试、三个 Grid 的 `php -l`，检查输出中无失败。
- [ ] **Step 3: 检查差异** 确认没有修改数据库升级脚本、Magento 核心或其他模块文件。

