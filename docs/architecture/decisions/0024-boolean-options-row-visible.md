# 0024. boolean 类型 options_csv 行可见性 bug(小改 L)

- 状态：Accepted
- 日期：2026-09-18
- 决策者：AI 助手
- 关联 ADR：0023(布尔型 label 自定义)
- 关联模块：XFE_Carrier 自定义属性"新建/编辑"表单

## 背景

用户反馈(2026-09-18):

> 选择下列、多选、布尔型和以前没有分别

排查后发现:`XfeCaTypeSwitcher.bind()` 的 `switchType()` 中,`setRowVisible(optionsEl, t === 'select' || t === 'multiselect')` 在 type=**boolean** 时条件为 false → `setRowVisible(false)` 把 options_csv 整个 `<tr>` 隐藏。

但 `mountOptionsEditor` 内部 `optionsEl.parentNode.appendChild(container)` 把行编辑器容器 append 到 `<td>` 内 — 与 optionsEl 同在 `<tr>` 下。所以 boolean 时容器**与原 input 一起被父 tr 隐藏**。

## 决策

`switchType` 的行可见性条件补上 boolean:

```js
// 修改前
setRowVisible(optionsEl, t === 'select' || t === 'multiselect');
// 修改后(小改 L,2026-09-18)
setRowVisible(optionsEl, t === 'select' || t === 'multiselect' || t === 'boolean');
```

行编辑器容器的 input vs 容器切换逻辑交给 `refresh(type)` 内部处理(`container.style.display` + `optionsEl.style.display`),不与父行显隐耦合。

## 后果

- 正面:boolean / select / multiselect 切 type 时,行编辑器(候选项 key|label 编辑器)都正确显示
- 负面:无 — 修复纯加一项条件
- 兼容:text / number 类型 options_csv 行依然隐藏(行为不变)

## 实施范围

| 文件 | 改动 |
|---|---|
| `js/xfe_carrier/custom-attribute-form-switcher.js` | switchType() 中 setRowVisible 条件加 boolean |
| `tests/js/run-tests.js` | +2 个 it(小改 L:text->boolean 行保留;select->boolean 行保留) |
| `task_plan.md` | 阶段 18 / 小改 L |
| `docs/architecture/xfe-carrier-custom-attribute-form-switcher-js.md` | §2.18 + §8 修订记录 |

预期测试基线:JS 160 → 162 passed,PHP 452 维持。
