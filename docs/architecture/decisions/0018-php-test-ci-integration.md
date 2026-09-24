# 0018. XFE_Injection PHP 测试 CI 集成

- 状态:Proposed
- 日期:2026-09-17
- 决策者:AI 助手(经用户确认)

## 关联文档

- [`0011-ci-integration.md`](./0011-ci-integration.md) — 已有的 JS 测试 CI(本 ADR 是其 PHP 扩展)
- [`../ci-php-integration.md`](../ci-php-integration.md) — 本 ADR 对应的开发指南
- [`../injection-architecture.md`](../injection-architecture.md) — XFE_Injection 模块架构
- [`../injection-api.md`](../injection-api.md) — XFE_Injection 公共契约

## 背景

XFE_Injection 模块经过 7 个阶段开发,目前有 156 个 assertions(单元 71 + 集成 36 + 集成 49),全部本地通过。

**关键发现:XFE_Injection 测试完全独立于 Magento 引导**:

- autoloader 自给自足(读 `app/code/community/XFE/Injection/**/*.php` 直接 require)
- `Mage` 类在测试中用 `class_exists('Mage', false)` 做防御检查,无硬依赖
- 集成测试用 mock 替换 `XFE_Carrier_Service_Account_CredentialViaInjection`,不触发真实 DB

**当前 CI 状态**(基于 ADR 0011):
- `.gitlab-ci.yml` 已存在,但只跑 JS 测试(40 用例 jsdom)
- `.github/workflows` 为空(ADR 0011 提到要补,但当时只测 JS 故未实施)

**风险**:
- 改了 `XFE_Injection` 或 `XFE_Carrier` 的 PHP 代码,CI 不会拦截回归
- 156 个本地测试断言无人在远端保护

## 决策

**扩展现有 CI 配置文件,新增纯 PHP 测试 job**:

### 1. `.gitlab-ci.yml` 加 `php-tests` job

- image: `php:8.3-cli-alpine`(`XFE_Injection` 测试不需要 Magento,纯 PHP 即可)
- script: `php tests/php/run-tests.php`(统一入口,内部依次跑 3 个测试文件)
- timeout: 3 分钟(纯 PHP 跑 156 断言 < 5 秒)
- 与 `js-tests` 并列在 `test` stage

### 2. 新增 `.github/workflows/php-tests.yml`

- 触发:push(master, codex/**)+ pull_request(target=master)
- Node:不装(纯 PHP 测试)
- 步骤:checkout → setup-php(8.3)→ 跑 tests/php/run-tests.php
- timeout: 3 分钟

### 3. 新增统一入口 `tests/php/run-tests.php`

- 单一入口运行所有 XFE_Injection 测试(单元 + 集成)
- 输出合并的断言总数 + 失败数
- 退出码:0 全过 / 1 有失败
- CI 与本地开发者共享同一入口,行为一致

### 镜像版本选择

| PHP 版本 | 选择理由 |
|----------|---------|
| **8.3** | 当前生产 PHP 8.3 / 8.5;8.3 是稳定版(LTS 风格),CI 兼容性最好 |
| 8.5(可选) | 本地开发用 8.5;CI 暂用 8.3 主线 |

不在 CI 中做多版本矩阵(避免 CI 时间翻倍);如需兼容旧 PHP,后续单独建 job。

## 备选方案

### A. ✅ 扩展现有 CI 配置 + 新增统一入口(本决策)

- 优点:与 ADR 0011 风格一致;统一入口便于后续扩展;PHP 镜像比 Node 镜像略大但启动 < 3 秒
- 缺点:增加 1 个 YAML 文件 + 1 个 PHP 入口 + 更新 GitLab CI
- 采纳

### B. 仅扩展 GitLab CI,不做 GitHub Actions

- 优点:省 1 个 YAML
- 缺点:GitHub PR 无 PHP 测试护栏
- 否决:项目 remote 在 GitHub,GitHub Actions 是主战场(ADR 0011 已表态)

### C. 在 CI 中引导完整 Magento + PHP 测试套件

- 优点:能测集成场景(真实 DB、Mage::dispatchEvent 等)
- 缺点:Magento 1.9 镜像巨大,启动慢,数据库初始化成本高;XFE_Injection 当前测试不需要这些
- 否决:YAGNI。当前测试纯 PHP 已覆盖 156 个断言,新增 Magento 引导得不偿失

### D. 在 CI 中只跑 `php -l` 语法检查,不跑功能测试

- 优点:极快(< 1 秒)
- 缺点:无回归保护;语法 OK 但逻辑错的代码仍能合并
- 否决:不满足"PR 必跑测"的护栏要求

## 后果

### 正面

- ✅ 每次 push / PR 自动跑 156 个 PHP 断言(与 40 个 JS 断言并列)
- ✅ PHP 回归在合并前被拦截
- ✅ 远端与本地共享 `tests/php/run-tests.php` 入口,行为一致
- ✅ CI 镜像 `php:8.3-cli-alpine` 启动约 1 秒,总 CI 时间 < 1 分钟

### 负面

- ⚠️ 增加 3 个文件(`.github/workflows/php-tests.yml`、更新 `.gitlab-ci.yml`、新增 `tests/php/run-tests.php`)
- ⚠️ 仅测 PHP 8.3 语法/语义;本地 PHP 8.5 的新特性(如 `#[\ReturnTypeWillChange]`)可能在 CI 失败
  - 缓解:XFE_Injection 模块 PHP 文件已避免使用 PHP 8.4+ 才有的语法

## 实施检查清单

- [ ] ADR 0018(本文档)
- [ ] 开发指南 `docs/architecture/ci-php-integration.md`
- [ ] `tests/php/run-tests.php` 统一入口
- [ ] `.gitlab-ci.yml` 加 `php-tests` job
- [ ] `.github/workflows/php-tests.yml` 新建
- [ ] 本地 dry-run `php tests/php/run-tests.php` 验证全过
- [ ] `docs/architecture/README.md` 索引更新
- [ ] `task_plan.md` / `progress.md` 同步

## 注意事项

- **PHP 镜像选型**:`php:8.3-cli-alpine` 是 CI 标准镜像;若团队统一升级 8.4 / 8.5,改一行即可
- **测试入口路径**:`tests/php/run-tests.php` 与 `tests/js/run-tests.js`(ADR 0010 命名一致)并列
- **autoloader**:测试文件自带 `spl_autoload_register`,CI 跑前无需 composer install
- **退出码语义**:0 = 全过(允许合并 PR),非 0 = 失败(阻止合并)
- **PHP 8.x 兼容**:CI 用 8.3;若代码用了 8.4+ 独有语法(`#[` 属性已有),会被 CI 拒收 → 触发本地版本对齐

## 后续可选

1. **测试覆盖率**:CI 输出 Xdebug 覆盖率报告(暂不做)
2. **多 PHP 版本矩阵**:`strategy.matrix.php: ['8.2', '8.3', '8.4']`(暂不做)
3. **Magento 集成测试**:CI 中引导完整 Magento + 真实 DB(仅当 XFE_Injection 引入真实 Mage 调用时再考虑)
