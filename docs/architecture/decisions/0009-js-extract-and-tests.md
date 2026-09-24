# 0009. 自定义属性表单联动 JS 抽取与单测

- 状态:Accepted
- 日期:2026-09-14
- 决策者:AI 助手(经用户确认)
- 关联文档:
  - [`../carrier-custom-attribute-form-types.md`](../carrier-custom-attribute-form-types.md)
  - [`0008-custom-attribute-form-ux.md`](./0008-custom-attribute-form-ux.md)

---

## 背景

ADR 0008 把 `switchCustomAttributeType()` 内联在模板 `type_switcher.phtml` 里。
后续若想:
1. 在多个模块复用这套联动逻辑
2. 维护期回归验证(切 type / 改 options_csv 后 `default_value` 仍然按预期渲染)
3. Code Review 时能在不打开浏览器的环境下预览行为

需要一个独立 JS 文件 + 可执行的测试套件。

## 决策

**两步走**:

1. **JS 抽取**:把 4 个纯函数 + 1 个 bind 入口抽到 `js/xfe_carrier/custom-attribute-form-switcher.js`,
   通过 `<reference name="head">` 的 `<action method="addItem">` 注册到 head,
   模板 `type_switcher.phtml` 简化为"加载 JS + 调 `bind()`"。
2. **测试栈**:**自写 mini test runner**(~80 行),不用 QUnit / Jasmine。

### JS 模块形状

```js
var XfeCaTypeSwitcher = (function () {
    'use strict';
    return {
        parseOptionsCsv: function (s) { ... },
        buildTextInput:  function (opts) { ... },
        buildSelect:     function (opts) { ... },
        buildBooleanRadios: function (opts) { ... },
        readCurrentValue: function (el) { ... },
        restoreMultiselect: function (sel, memo) { ... },
        setRowVisible:    function (el, visible) { ... },
        bind:             function (opts) { ... }
    };
}());
```

- 全部方法挂在一个 `XfeCaTypeSwitcher` 全局对象上(prototype.js 风格,无 ES6 import)
- 纯函数无 DOM 依赖,直接可测
- `bind(opts)` 是副作用入口,接受 `{fieldTypeEl, optionsEl, defaultEl}`,调用方传 Magento `$()` 取出的元素
- 测试时 `bind` 也可被 mock,只测纯函数

### 单元测试形状

`tests/js/custom-attribute-form-switcher.html`:

```html
<!doctype html>
<script src="../../js/prototype/prototype.js"></script> <!-- 或本地 prototype 镜像 -->
<script src="../../js/xfe_carrier/custom-attribute-form-switcher.js"></script>
<script>
    // ~80 行 mini runner:
    // - describe(name, fn) / it(name, fn) / assert.equal / assert.deepEqual
    // - DOM 测试用 document.createElement
    // - 渲染结果到 <body> 顶部:绿条 / 红条 + 失败原因
</script>
```

测试用例覆盖:

| 函数 | 用例 |
|---|---|
| `parseOptionsCsv` | 空字符串 / 单项 / 多项 / 前后空白 / 空项过滤 |
| `buildTextInput` | memo 为空 / memo 有值 / 带 validate-number |
| `buildSelect` | single + blank 选项 / multiple 不含 blank / options 空数组 |
| `buildBooleanRadios` | memo=`1` / memo=`0` / memo=空 |
| `readCurrentValue` | text input / select / select-multiple / boolean radio 容器 |
| `restoreMultiselect` | 单选 / 多选 / 空 memo |
| `setRowVisible` | 显示 / 隐藏 |

跑测试:浏览器打开 `tests/js/custom-attribute-form-switcher.html`,顶部出现绿条 + 通过数即可。

---

## 备选方案

### A. QUnit(经典 xUnit 风格)

- 优点:成熟 / 文档多 / 团队认知度高
- 缺点:Magento 1.9 项目里没装,需 CDN 或本地镜像;集成进 CI 复杂
- 否决:引入新依赖违反「零新依赖」原则

### B. Jasmine(BDD 风格)

- 优点:可独立跑 node + headless
- 缺点:同上需要 npm 安装;prototype.js 环境下全局命名空间污染
- 否决:同上

### C. ✅ 自写 mini runner(本决策)

- 优点:0 依赖 / 与 prototype.js 完美兼容 / 测试代码即文档 / 30-80 行足够
- 缺点:无高级特性(no async / no snapshot),但本项目 JS 函数全是同步 + 纯函数
- 采纳

### D. node + jsdom + 原生 assert

- 优点:命令行可跑 / 适合 CI
- 缺点:prototype.js 在 jsdom 下要 hack;项目无 npm 流程
- 否决:留作未来升级选项,本次不强求

---

## 后果

### 正面

- ✅ JS 与模板解耦,测试可在浏览器开 HTML 直接跑
- ✅ 后续若要把联动逻辑应用到其他属性编辑器,直接复用 `XfeCaTypeSwitcher.bind(...)`
- ✅ 改动可测,防止 prototype.js DOM 操作改坏了难以发现

### 负面

- ⚠️ 增加 2 个新文件(1 JS + 1 HTML)
- ⚠️ 测试用例需要持续维护,后续函数改动要同步更新用例
- ⚠️ 测试结果依赖浏览器打开,无 CI 自动化(可后续加 D 方案)

---

## 实施检查清单

- [ ] 新建 ADR 0009(本文档)
- [ ] 新建架构文档 `docs/architecture/xfe-carrier-custom-attribute-form-switcher-js.md`
- [ ] 新建 JS `js/xfe_carrier/custom-attribute-form-switcher.js`
- [ ] 简化 `type_switcher.phtml`(从 ~190 行 → ~30 行)
- [ ] 修改 `xfecarrier.xml`:挂 `<reference name="head">` 的 `addItem('js', ...)`
- [ ] 新建测试 `tests/js/custom-attribute-form-switcher.html`(mini runner + 8+ 用例)
- [ ] 浏览器打开测试页,绿条全过
- [ ] 后台手动验证联动仍然正常
