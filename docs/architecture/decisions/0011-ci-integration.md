# 0011. CI 集成 — GitHub Actions + GitLab CI(测试运行时)

- 状态:Accepted
- 日期:2026-09-14
- 决策者:AI 助手(经用户确认)
- 关联文档:
  - [`0010-jsdom-test-runtime.md`](./0010-jsdom-test-runtime.md)
  - [`0009-js-extract-and-tests.md`](./0009-js-extract-and-tests.md)

---

## 背景

ADR 0010 引入了 `node tests/js/run-tests.js`(40 用例,jsdom 模式),但目前只在本地命令行跑。
项目使用 GitHub(remote: `hansongao-168/magento1-test`)— **没有现成 CI 配置**(`ls .github/workflows` 不存在)。

风险:
- 代码改了没人验证 → 测过的 40 用例可能悄悄变红
- 没有"PR 必跑测"的护栏

需求:**每次 push / PR 时自动跑 jsdom 测试**。

## 决策

**同时提供两个 CI 配置文件**(项目 owner 任选其一):
- `.github/workflows/js-tests.yml` — GitHub Actions
- `.gitlab-ci.yml` — GitLab CI

两者都做同样 3 件事:
1. 检出代码
2. 装 Node.js + npm ci
3. 跑 `node tests/js/run-tests.js`

### GitHub Actions 配置要点

- 触发:`push`(master,codex/**) + `pull_request`(target=master)
- Node.js 版本:`actions/setup-node@v4` + `node-version: '20'`(与本地一致)
- cache:`cache: 'npm'` 自动缓存 `~/.npm`
- 运行步骤:checkout → setup-node → npm ci → run tests
- timeout: 5 分钟(jsdom 启动 + 40 用例 < 2 分钟)

### GitLab CI 配置要点

- image:`node:20-alpine`(镜像小,启动快)
- stages:`test`(单 stage)
- cache:`paths: [node_modules/]`(跨 job 缓存)
- artifacts:无(jsdom 测试不需要产物)
- script:`npm ci && node tests/js/run-tests.js`

### 故意不做

- ❌ PHP 测试(Magento 1.9 在 PHP 5.6-7.3 范围,CI 镜像装 PHP + Magento 调试成本极高;且本次改动的核心逻辑都是 JS)
- ❌ 浏览器测试(Puppeteer / Playwright 需 Chromium,镜像大)
- ❌ 多 Node 版本矩阵(只测 20 与本地一致;后续如需兼容再加 matrix)

---

## 备选方案

### A. Travis CI / CircleCI(老牌 CI)

- 优点:成熟
- 缺点:本项目不在 GitHub Marketplace 主推之列,文档生态不如 GH Actions
- 否决:项目用 GitHub,优先 GH Actions

### B. ✅ GitHub Actions + GitLab CI 双提供(本决策)

- 优点:覆盖项目常用两个平台;按需启用
- 缺点:维护两份配置(差异极小)
- 采纳

### C. 只用 GitHub Actions

- 优点:单份配置
- 缺点:GitLab 用户无法用
- 否决:GitLab 也是 Magento 1 项目常见 CI

### D. Husky + lint-staged 提交前本地检查

- 优点:不依赖远端 CI
- 缺点:不能强制,提交者可以 `--no-verify` 跳过
- 否决:CI 是更强的护栏

---

## 后果

### 正面

- ✅ 每次 push / PR 自动跑 40 个 jsdom 用例
- ✅ PR 合并前可看到 ✓ / ✗ 状态
- ✅ 测试在 CI 上首次跑通即视为"绿灯"基线

### 负面

- ⚠️ 增加 2 个 YAML 配置文件(~30 行 + ~25 行)
- ⚠️ 首次跑 CI 约 60-90 秒(jsdom 安装 + 启动)
- ⚠️ 后续若要扩到 PHP 测试,需要 PHP runner image,体积更大

---

## 实施检查清单

- [ ] ADR 0011(本文档)
- [ ] `.github/workflows/js-tests.yml` — GitHub Actions
- [ ] `.gitlab-ci.yml` — GitLab CI
- [ ] 测试在本地 `act`(GitHub Actions 本地跑)验证 YAML 合法
- [ ] 文档 / progress.md 更新

---

## 注意事项

- **GitHub Actions 权限**:`contents: read` 是默认权限,够用
- **npm ci 走 cache**:GitHub Actions 自动 cache `~/.npm`,无需手动配
- **超时**:`timeout-minutes: 5`,防止某次卡死
- **分支策略**:项目若用 `codex/**` 前缀,需要把 trigger 加上;默认只监听 master / main
