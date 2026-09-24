# 0029. 承运商 Logo / 账号 / FTP 列表增加「+ 添加规则」弹窗入口

- 状态：**Superseded by ADR 0031（2026-09-23）**
- 后续：iframe 方案在用户测试中暴露 chrome 隐藏 CSS / form 时序 / script 重初始化问题，已重构为 AJAX 注入式弹窗（详见 ADR 0031）。本文档保留作为历史参考。
- 日期：2026-09-21
- 决策者：AI 助手
- 关联模块：XFE_Carrier
- 关联文档：docs/architecture/carrier-rule-conditions.md

## 背景

承运商编辑页（adminhtml/carrier/edit）的 3 个子列表 —— Logo 列表、账号列表、FTP 账号列表 —— 当前只允许"为该资源添加 Logo / 账号 / FTP 账号"，但若用户希望为这些资源**附加一条规则**（module_code 分别为 logo / account / ftp），必须：

1. 先点开一条具体 Logo / 账号 / FTP 账号的「编辑」页，进入 Resource Edit 容器；
2. 在容器底部「规则设置」段落找到「+ 添加规则」按钮；
3. 跳转到完整的 Rule Edit 页面填写表单，保存后再跳回原 Resource Edit 页面。

这条路径对用户而言是 3 次跳转（列表 → 资源编辑 → 规则编辑 → 资源编辑 → 列表），与"为该资源添加 Logo / 账号"的一步操作体验差距明显。

## 决策

在三个父 Grid 块（Logo / Account / FtpAccount）的工具条上各加一个「+ 添加规则」按钮，**点击后弹出 iframe 弹窗**加载 `adminhtml/carrier/editRule`，保存成功后弹窗自动关闭并刷新父窗。

### 三个按钮的入口参数

| 父列表 | 入口 URL | module_code | 后端影响 |
|--------|---------|-------------|---------|
| Logo 列表 | `*/carrier/editRule?carrier_id=…&module_code=logo` | logo | 新建 carrier 级别的 logo 规则 |
| 账号列表 | `*/carrier/editRule?carrier_id=…&module_code=account` | account | 新建 carrier 级别的 account 规则 |
| FTP 列表 | `*/carrier/editRule?carrier_id=…&module_code=ftp` | ftp | 新建 carrier 级别的 ftp 规则 |

### 弹窗交互

- **打开**：JS 在 body 末尾插入 overlay + iframe（src = 上表中的 URL），iframe 内加载完整的 Rule Edit 页面（沿用现有 tabs / 条件构建器 / Save 按钮 / 后端表单）。
- **保存完成判定**：监听 iframe 的 `onload` 事件。保存成功后 `saveRuleAction` 通过 `_redirect("*/carrier/edit", …)` 跳到 Carrier Edit 页面，iframe 的 src 不再匹配 `editRule` 或 `saveRule`，视为"已保存"。
- **关闭与刷新**：检测到保存成功后立即调用 `XFE_CarrierRuleModal.close()` 销毁弹窗，并执行 `window.location.reload()` 让父窗（仍停留在 Carrier Edit 页面）重新渲染当前 Tab 的 Grid。
- **取消 / ESC / 背景点击**：用户在 iframe 内点 Back / 取消 / 直接关闭弹窗，父窗不刷新。

### 文件清单

| 文件 | 变更 |
|------|------|
| `skin/adminhtml/default/default/xfe_carrier/js/rule-modal.js` | 新建（XFE_CarrierRuleModal 命名空间） |
| `app/design/adminhtml/default/default/layout/xfecarrier.xml` | `adminhtml_carrier_edit` 节点 `<reference name="head">` 注册 skin_js |
| `app/code/community/XFE/Carrier/Block/Adminhtml/Carrier/Edit/Tab/Logo/Grid.php` | `_toHtml()` 工具条加「+ 添加规则」按钮 |
| `app/code/community/XFE/Carrier/Block/Adminhtml/Carrier/Edit/Tab/Account/Grid.php` | 同上 |
| `app/code/community/XFE/Carrier/Block/Adminhtml/Carrier/Edit/Tab/FtpAccount/Grid.php` | 同上 |
| `docs/architecture/carrier-rule-conditions.md` | 新增 §5「列表入口-弹窗」段落 |

## 备选方案

### A. XHR 注入 + 自渲染表单
直接用 AJAX 拉取 `editRule` 页面的 HTML，再用 JS 把表单抽出注入弹窗 DIV。**问题**：
- `editRule` 页面用 `varienTabs` + `condition-builder.js` 做条件构建器，表单提交时需要条件树 hidden input；XHR 注入后这些 JS 必须重挂载。
- 保存后 `_redirect` 会让原始表单跳走，需要拦截 submit + 自己写 AJAX saveAction。
- 工程量是 iframe 方案的 3-4 倍，且每次 Magento / 模板升级都需重新适配。

### B. 不加按钮，让用户继续走"先编辑资源"的路径
保持现状。**问题**：操作步骤数与父按钮的简单性不匹配，违背"承运商配置应能在一个页面内完成"的 UX 原则（参考 carrier-facade.md §3）。

### C. 改造 Resource Edit 页面让其本身支持"附加规则" inline 区块
在 Edit Account / Edit FTP / Edit Logo 容器内嵌入完整的条件构建器。**问题**：
- 条件构建器（`condition-builder.js`）是页面级单例，重复实例化会冲突。
- 一个 Resource Edit 页面塞两套表单（资源 + 规则）会让审核 / 调试成本翻倍。
- iframe + 弹窗已经能满足"附加规则"诉求，没必要这么重。

## 后果

### 正面
- 用户操作步骤从 5 步（列表 → 资源编辑 → 规则编辑 → 资源编辑 → 列表）缩短到 2 步（列表 → 弹窗 → 列表）。
- 零后端改动：editRule / saveRule 完全不变，复用现有表单 / 校验 / 权限 / 事件广播链路。
- iframe 弹窗让条件构建器、tabs、Save 按钮的 JS 全部在自身页面内运行，无需重新挂载。

### 负面
- iframe 弹窗样式与父窗 admin 主题略有视觉割裂（因为弹窗内是完整后台布局）。可通过 iframe CSS 微调缓解，但本次改动不做样式深度调整。
- 关闭弹窗后 `window.location.reload()` 会触发整个 Carrier Edit 页面重渲染（不仅是当前 Tab），1-2 秒等待。若用户开了 store switcher / 多 tab 编辑，状态可能丢失。
- `editRule` 表单在 iframe 内提交时若触发 JS 报错，错误仅显示在 iframe 内，父窗无法捕获。本次改动不做跨框架错误传递。

### 风险与回退
- 风险点集中在 iframe 弹窗 JS；如发现某个浏览器下弹窗无法正常关闭，最坏情况是用户手动 F5 刷新页面，规则仍然保存成功。
- 回退成本：删除 skin JS 文件 + layout XML 中一行 + 三个 Grid 块的按钮 HTML，约 10 分钟即可撤销。
### UX 细节补充（2026-09-21）

- **父窗滚动锁**：打开时记录 `window.pageYOffset`，把 `body.style.position=fixed; top=-scrollY`；关闭时还原 body 样式并 `window.scrollTo(0, prevTop)`。避免弹窗打开期间父窗被误滚。
- **焦点管理**：打开时记录 `document.activeElement` 作为 `_opener`，聚焦关闭按钮（便于键盘 Tab 到 ×）；关闭后 `_opener.focus()` 还原。若 `_opener` 已在 DOM 外（reloaded）则 try/catch 静默跳过。
- **同一时刻只允许一个弹窗**：`open()` 入口检测 `instance.opened`，若已有弹窗则 `contentWindow.focus()` 重新聚焦，不重复插入 DOM。
### iframe 去 chrome CSS 注入（2026-09-21 追加）

弹窗打开后，`iframe.onload` 触发时会向 iframe document 的 `<head>` 注入一段 CSS（命名空间 id `xfe-carrier-rule-modal-chrome-css`），隐藏 Magento 1.9 后台的顶栏 / 左侧菜单 / 通知 / 面包屑 / 页脚。

详细选择器列表、注入方式、幂等性保证、为什么不改 layout XML 的理由 → 见 `carrier-rule-conditions.md` §6.7。

**烟雾测试位置**：`tests/js/test-rule-modal.js`（jsdom，14 assertions）。
