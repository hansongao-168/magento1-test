# 0010. 测试运行时升级到 jsdom(自写 stub -> jsdom)

- 状态:Accepted
- 日期:2026-09-14
- 决策者:AI 助手(经用户确认)
- 关联文档:
  - [`0009-js-extract-and-tests.md`](./0009-js-extract-and-tests.md)
  - [`../xfe-carrier-custom-attribute-form-switcher-js.md`](../xfe-carrier-custom-attribute-form-switcher-js.md)

---

## 背景

ADR 0009 选用了"自写 mini test runner + minimal DOM stub"跑测试,主要考虑是"零新依赖"。
在 32 个用例范围内 stub 工作良好,但有以下局限:

1. **不支持 `addEventListener` 实际触发事件** — stub 只把回调存起来,从不调
2. **不支持 `dispatchEvent` / `new Event()`** — 没法测 `bind()` 实际联动流程
3. **不支持 `getComputedStyle` / 真实 layout** — 后续如需测显示效果不可行
4. **`select.value` setter 行为简化** — `s.value = 'y'` 实际只是赋值,不会自动 mark `selected`
5. **innerHTML 解析 regex 简单粗暴** — 多层嵌套 / 注释 / CDATA 处理不了

后续若想:
- 测 `bind()` 端到端流程(field_type change → default_value 重建)
- 测 `restoreMultiselect` 真实 select-multiple 行为
- 与 prototype.js 兼容测试(若未来要 import real prototype 跑 Magento UI)

需要更真实的浏览器 DOM。

## 决策

**安装 `jsdom` 作为 devDependency,用 jsdom 替换 minimal stub。**

- 保留自写 mini runner 的 describe/it/assert(测试组织风格不动)
- 替换 DOM 实现:`run-tests.js` 用 `new JSDOM('<!doctype html>...')` 拿 `window` / `document`
- 测试代码本身几乎不动(只换底层 DOM 实现)

### 实施

```js
const { JSDOM } = require('jsdom');
const dom = new JSDOM('<!doctype html><html><body></body></html>');
const { window } = dom;

// 把 window 的 document / Element 暴露到 vm context
const ctx = vm.createContext({
    window,
    document: window.document,
    console
});

vm.runInContext(jsCode, ctx);
```

### 新增 bind() 端到端用例

| 用例 | 预期 |
|---|---|
| `field_type` 从 `text` 切到 `boolean`,`default_value` 变为 Yes/No radio,`memo='hello'` 留存 | 通过 |
| `field_type=select` 时改 `options_csv`,`default_value` 自动重建选项 | 通过 |
| 编辑回显:`field_type=select`,`default_value=文本 input`,bind 后变为 select 且当前值已 selected | 通过 |

---

## 备选方案

### A. 保持 stub + 补缺失 API(不引入 npm 依赖)

- 优点:0 依赖
- 缺点:stub 越来越复杂,本质是"重写一个 mini jsdom",得不偿失
- 否决:违反 DRY

### B. ✅ jsdom(本决策)

- 优点:成熟 / 标准 / 覆盖率广 / 文档多
- 缺点:增加 ~30MB 依赖,CI 首次安装稍慢
- 采纳

### C. happy-dom(更轻量)

- 优点:比 jsdom 轻量 ~10x,启动更快
- 缺点:覆盖率略低于 jsdom;若未来要测 prototype.js 兼容 happy-dom 不够
- 备选(未来若 jsdom 太重可切换)

### D. Puppeteer / Playwright(真浏览器)

- 优点:最真实
- 缺点:本地需装 Chromium / Firefox ~150MB;CI 装更麻烦;纯 DOM 测试 overkill
- 否决:杀鸡用牛刀

---

## 后果

### 正面

- ✅ 可以测 `bind()` 端到端流程(触发事件 → 验证 DOM 变化)
- ✅ 复用真实 jsdom 行为,测试结果更可靠
- ✅ 后续要做 prototype.js 兼容测试,jsdom 能跑 prototype

### 负面

- ⚠️ npm install 增加 ~30MB 依赖
- ⚠️ node_modules 增长可能影响仓库体积(已 .gitignore node_modules 通常没事)
- ⚠️ jsdom 与真浏览器仍有 1-2% 差异(如 CSS layout),但 DOM 测试不受影响

---

## 实施检查清单

- [ ] `npm install jsdom --save-dev`
- [ ] `package.json` devDependencies 加 `jsdom`
- [ ] `tests/js/run-tests.js` 用 jsdom 替换 stub
- [ ] 新增 3 个 `bind()` 端到端用例
- [ ] 跑全部测试 35+ passed
- [ ] 更新架构文档 `xfe-carrier-custom-attribute-form-switcher-js.md` §3.4 加"CI(可选)实现"
