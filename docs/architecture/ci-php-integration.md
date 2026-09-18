# CI 集成 PHP 测试开发指南

> 主题：把 XFE_Injection 的 156 个 PHP 测试断言接入 CI（GitLab CI + GitHub Actions），
> 实现"PR 必跑测"的护栏。

> 状态：Draft（2026-09-17）

> 关联文档：
> - [decisions/0018-php-test-ci-integration.md](./decisions/0018-php-test-ci-integration.md) — 本指南对应 ADR
> - [decisions/0011-ci-integration.md](./decisions/0011-ci-integration.md) — 已有的 JS 测试 CI（ADR 0018 是其 PHP 扩展）
> - [injection-architecture.md](./injection-architecture.md) — XFE_Injection 模块架构

---

## 1. 目标与范围

### 1.1 业务目标

让 XFE_Injection 模块的 156 个测试断言在每次 push / PR 时自动跑通，
远端拦截 PHP 回归。本轮只覆盖 XFE_Injection 的纯 PHP 测试套件。

### 1.2 本轮范围

| 项目 | 是否在本轮 |
|------|----------|
| 统一入口 `tests/php/run-tests.php`（跑 3 个测试文件） | ✅ |
| `.gitlab-ci.yml` 加 `php-tests` job | ✅ |
| `.github/workflows/php-tests.yml` 新建 | ✅ |
| `docs/architecture/README.md` 索引更新 | ✅ |
| `task_plan.md` / `progress.md` 同步 | ✅ |
| PHP 多版本矩阵（8.2/8.3/8.4） | ❌ 下一轮 |
| Magento 集成测试 | ❌ YAGNI |
| 测试覆盖率报告（Xdebug） | ❌ YAGNI |
| XFE_Carrier 等其他模块的 PHP 测试接入 | ❌ 下一轮 |

---

## 2. 关键事实

### 2.1 测试可独立 PHP 跑

XFE_Injection 测试**完全独立于 Magento 引导**：

```php
// 测试文件自带 autoloader（例：Test/Unit/InjectionTest.php:20）
spl_autoload_register(function ($class) {
    $projectRoot = realpath(__DIR__ . '/../../../../../../../');
    // 直接 require app/code/community/XFE/<Class>.php
    // 无 Mage::run()、无 Varien_Autoload、无数据库
});
```

`Mage` 仅做防御检查：
```php
if (!class_exists('Mage', false)) { return array(); } // 单测无 Mage 时安全返回
```

所以 CI 镜像只需要 **纯 PHP CLI**，不需要 Magento。

### 2.2 测试套件统计

| 测试文件 | 类型 | 断言数 | 跑时 |
|---------|------|--------|------|
| `app/code/community/XFE/Injection/Test/Unit/InjectionTest.php` | 单元 | 71 | < 1s |
| `app/code/community/XFE/Injection/Test/Integration/CarrierLogisticTest.php` | 集成 | 36 | < 1s |
| `app/code/community/XFE/Injection/Test/Integration/DocumentUploadTest.php` | 集成 | 49 | < 1s |
| **合计** | — | **156** | **< 3s** |

---

## 3. 实施清单

### 3.1 统一入口

文件：`tests/php/run-tests.php`

行为：
- 顺序跑 3 个测试文件（单元 → 集成）
- 汇总断言总数 + 失败数
- 退出码：0 全过 / 1 有失败
- 输出人类可读的进度日志（CI 控制台友好）

### 3.2 GitLab CI

文件：`.gitlab-ci.yml`（已存在，需更新）

变更：
- 在 `test` stage 新增 `php-tests` job
- 镜像：`php:8.3-cli-alpine`
- 超时：3 分钟
- script：`php tests/php/run-tests.php`
- 与 `js-tests` 并列，不互相阻塞

### 3.3 GitHub Actions

文件：`.github/workflows/php-tests.yml`（新建）

触发：
- `push`：master / main / codex/**
- `pull_request`：target=master / main

步骤：
1. `actions/checkout@v4`
2. `shivammathur/setup-php@v2` + `php-version: '8.3'`
3. `php tests/php/run-tests.php`

超时：3 分钟。

### 3.4 文档同步

- `docs/architecture/README.md` 加 1 行（ADR 0018 + 开发指南）
- `task_plan.md` 阶段 7 选项 3 标记完成
- `progress.md` 追加完成段

---

## 4. 验证清单

### 4.1 本地 dry-run

```bash
php tests/php/run-tests.php
# 预期：
#   === Unit: InjectionTest ===
#     ... 71 PASS ...
#   === Integration: CarrierLogisticTest ===
#     ... 36 PASS ...
#   === Integration: DocumentUploadTest ===
#     ... 49 PASS ...
#   ========================================
#   Total: 156 assertions, 0 failures
#   ========================================
# 退出码: 0
```

### 4.2 YAML 合法性

- `.gitlab-ci.yml`：用 GitLab 自带 Lint API 或 `yamllint` 验证
- `.github/workflows/php-tests.yml`：用 GitHub Actions 自带 Lint 或本地 `act` 验证

### 4.3 CI 触发验证

- 在 PR 中推 1 个 commit（即使无 PHP 改动），应触发 `php-tests` job
- 在 `codex/**` 分支 push，应触发
- job 跑通后 PR 显示 ✓ 状态

### 4.4 失败拦截验证（可选）

故意在测试中加 1 个 `ok(false, '故意失败')`，CI 应报红且阻止合并。

---

## 5. 风险与回滚

### 5.1 风险

| 风险 | 缓解 |
|------|------|
| CI PHP 镜像与本地 PHP 8.5 行为差异（语法/弃用警告） | 测试文件避免使用 PHP 8.4+ 独有语法；CI 失败即触发本地版本对齐 |
| GitLab / GitHub Actions YAML 语法差异 | 按各自平台文档写最小可用配置；优先测 GitLab（项目当前默认 CI） |
| 入口脚本对路径假设硬编码 | 用 `__DIR__` 推项目根，与测试自身 autoloader 一致 |

### 5.2 回滚方案

1. 删除 `.github/workflows/php-tests.yml`
2. 从 `.gitlab-ci.yml` 移除 `php-tests` job
3. 删除 `tests/php/run-tests.php`
4. 删除 ADR 0018 与本指南

回滚成本：4 步约 2 分钟。

---

## 6. 后续工作

1. **PHP 多版本矩阵**（按需）：在 GH Actions 加 `matrix.php: ['8.2', '8.3']`
2. **其他模块测试接入**：`XFE_Carrier` 等模块若有自给自足的 PHP 测试，可同样接入
3. **覆盖率报告**（按需）：CI 输出 Xdebug coverage → Codecov / Coveralls
4. **Magento 集成测试**（仅当需要）：CI 镜像改为 `php + mysql + magento1` 复合镜像

---

## 7. 修订记录

| 日期 | 变更 | 作者 |
|------|------|------|
| 2026-09-17 | 初版：定义 XFE_Injection PHP 测试 CI 集成最小化方案 | hanson.gao + AI 助手 |