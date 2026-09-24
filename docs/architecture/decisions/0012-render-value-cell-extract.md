# 0012. 抽 `renderValueCell` 公开 API + strict_editor.phtml 暂不接入

- 状态:Accepted
- 日期:2026-09-15
- 决策者:AI 助手(经用户确认)
- 关联文档:
  - [`0009-js-extract-and-tests.md`](./0009-js-extract-and-tests.md) — JS 抽取与单测
  - [`0010-jsdom-test-runtime.md`](./0010-jsdom-test-runtime.md) — jsdom 测试运行时
  - [`0011-ci-integration.md`](./0011-ci-integration.md) — CI 集成
  - [`../xfe-carrier-custom-attribute-form-switcher-js.md`](../xfe-carrier-custom-attribute-form-switcher-js.md) — JS 模块架构文档(本 ADR 落地后补章节)

---

## 背景

ADR 0009 / 0010 / 0011 完成后,`XfeCaTypeSwitcher` 已稳定 40 用例全过、CI 已接入。

用户提出的 follow-up:**复用 `XfeCaTypeSwitcher.bind` 给 `strict_editor.phtml`**。

但调研后发现两个表面相似、本质不同的场景:

| 维度 | custom_attribute(新增/编辑页) | strict_editor.phtml(严格模式编辑器) |
|---|---|---|
| `field_type` 来源 | 用户从 `<select>` 选(可改) | 从 `$def->getFieldType()` 读(只读) |
| 是否需要 `change` 联动 | **是**(`bind()` 的核心) | **否**(type 不可改) |
| value 控件生成时机 | `change` 触发 + 首次回显 | **仅**首次加载(PHP 预渲染) |
| value 控件 4 种 type 分支 | JS(`buildTextInput/buildSelect/buildBooleanRadios`) | PHP(模板里 4 个 `if/elseif` 分支) |
| 是否有 chips 自由标签 | 无 | 有(60 行 IIFE,strict_editor 独有 UX) |

**结论**:两个页面共享的是"按 type 构造 value 控件"这一段逻辑,**不共享** `bind()` 的"监听 change"部分。

直接给 strict_editor 调 `bind()` 是**强加语义不匹配的 API**(`fieldTypeEl` 在 strict_editor 没有),违反 AGENTS.md §3 模块边界。

---

## 决策

**分两步最小方案**:

### 本轮(本 ADR 落地)

1. **抽 `renderValueCell(container, opts)` 为公开 API** — 从 `bind()` 内部 `switchType` 抽出"按 type 构造 + 替换 DOM"逻辑,接受 `container` 作为挂载点,接受 `opts.type/opts.memo/opts.optionsCsv/opts.yesLabel/opts.noLabel/opts.blankLabel/opts.name/opts.className/opts.withNumberValidator`。
2. **`bind()` 重构为薄壳**:内部 `switchType` / `onOptionsChange` 改为调 `renderValueCell()`,消除 switch 重复。
3. **新增 8 个 `renderValueCell()` 单测**(5 种 type + name 重写 + 编辑回显 + boolean 防御),跑测试从 40 → **48 passed**。
4. **导出对象加 `renderValueCell: renderValueCell`**(向后兼容,原 8 个方法不动)。

### strict_editor.phtml 暂不接入(本轮不做)

**理由**:

1. **chips 自由标签是 strict_editor 独有 UX**(60 行 IIFE,Enter / `,` 添加,`×` 关闭,Backspace 删最后一个),不是 XfeCaTypeSwitcher 的语义;混入会污染现有模块边界
2. **POST 名称不同**:custom_attribute 用 `default_value`,strict_editor 用 `custom_fields[key][value]`,直接复用 buildXxx 会带错 `name` 属性
3. **className 不同**:custom_attribute 用 `input-text / select`,strict_editor 用 `xfe-ca-row-input`,前端 CSS 不可共用
4. **强制接入 = 破坏性改动**:strict_editor 的 value 列目前是 PHP 渲染;若改为 JS 渲染,首次加载到 JS 跑完之间有空白窗口期,且编辑回显要改为 `data-default` 读取,影响范围超出 JS 模块
5. **AGENTS.md §5.2 小步可逆**:本次只完成"API 抽取",让 strict_editor 接入有干净的入口;后续要走新 ADR(预计 ADR 0013)专门做"strict_editor 全面 JS 渲染 + chips 模块化",改动可控、可独立 review

### 后续(预计 ADR 0013,本轮不做)

- 抽 `buildChips(opts)` 进 XfeCaTypeSwitcher(把 strict_editor 的 60 行 IIFE 搬过来,接受 `name/currentValues` 参数)
- strict_editor.phtml 改为:PHP 只渲染外层 table + 必填标 + 未登记提示,value 列渲染 `<td data-value-cell data-key data-type data-default data-options data-name>`
- 模板底部挂 init script 遍历 `[data-value-cell]` 调 `renderValueCell`(multiselect 走 `buildChips`)
- 新增 `bindStrict(opts)` 或类似入口(可选)
- 新增 ~6 个 strict_editor 端到端用例

---

## 备选方案

### A. ✅ 本决策:抽 API + 暂不接 strict_editor(采纳)

- 优点:零行为破坏;48 用例全过;严格遵守"小步可逆"
- 缺点:strict_editor 仍用 PHP 渲染,代码上仍有 4 个 type 分支的 PHP 重复
- 采纳

### B. 一步到位:strict_editor 也改 JS 渲染

- 优点:彻底消除 PHP/JS 重复
- 缺点:本轮改动幅度大(模板重写 + 60 行 IIFE 搬家 + 编辑回显改 data 属性 + 新增 buildChips);违反小步可逆
- 否决:留到 ADR 0013 单独评估

### C. 不抽 API,只在 strict_editor 里把 4 个 PHP 分支复制一遍 JS 逻辑

- 优点:无
- 缺点:反向走 — 引入新重复,违反 AGENTS.md §1 模块化
- 否决

### D. 给 buildTextInput / buildSelect / buildBooleanRadios 加 `name` / `className` 参数

- 优点:不抽新 API,buildXxx 自身可适配 strict_editor
- 缺点:**breaking change**:现有 32 个 buildXxx 用例会因新参数破坏;语义混乱(默认参数 vs 可选参数难定)
- 否决:破坏现有测试,得不偿失

---

## 后果

### 正面

- ✅ `XfeCaTypeSwitcher` 新增 1 个公开方法 `renderValueCell`,语义明确("渲染到指定容器")
- ✅ `bind()` 内部 switch 重复消除,代码更短(原 60 行 switch → 1 行 `renderValueCell(...)`)
- ✅ 测试从 40 → 48(+8 个 `renderValueCell` 用例)
- ✅ 为 ADR 0013(strict_editor 接入)准备干净的入口
- ✅ 零行为破坏(原 40 用例不变)

### 负面

- ⚠️ 公开 API 数量从 8 → 9(微小,但要更新架构文档)
- ⚠️ strict_editor 仍未接入,4 个 PHP 分支与 JS `buildXxx` 仍是两份实现
- ⚠️ 文档 / progress.md / task_plan.md 同步更新成本

---

## 实施检查清单

- [ ] ADR 0012(本文档)
- [ ] `docs/architecture/xfe-carrier-custom-attribute-form-switcher-js.md` 补 §1 模块形状 + §7 后续接入路径
- [ ] `js/xfe_carrier/custom-attribute-form-switcher.js`:
  - 抽 `renderValueCell(container, opts)` 函数
  - `bind()` 内部 switch 改用 `renderValueCell`
  - 导出对象加 `renderValueCell`
- [ ] `tests/js/run-tests.js` 新增 `describe('renderValueCell()', ...)` 8 用例
- [ ] `node tests/js/run-tests.js` → **48 passed, 0 failed**
- [ ] `node --check js/.../custom-attribute-form-switcher.js` → 0
- [ ] task_plan.md 阶段 6 全勾
- [ ] progress.md 加阶段 6 小节

---

## 注意事项

- **bind() 重构不破坏行为**:`renderValueCell` 是 `switchType` 的子集提取,opts 字段映射保证 1:1 等价
- **测试矩阵**:5 种 type + name 重写 + 编辑回显 + boolean 防御,刚好 8 个用例;不与 bind() 端到端 8 个用例重叠
- **strict_editor 接入路径已在文档化**:后续 ADR 0013 按本文 §"后续"章节执行即可,无需再开设计讨论

---

## 修订记录

| 日期 | 变更 | 作者 |
|---|---|---|
| 2026-09-15 | 初版:抽 renderValueCell 公开 API + 记录 strict_editor 暂不接入的理由 | AI 助手 |
