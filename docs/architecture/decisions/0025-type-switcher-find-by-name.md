# 0025. type_switcher.phtml 用 name 查找兼容 id 前缀(小改 M)

- 状态：Accepted
- 日期：2026-09-18
- 决策者：AI 助手
- 关联 ADR：0022(options_csv 行编辑器)、0023(boolean label)、0024(boolean 行可见性)
- 关联模块：XFE_Carrier 自定义属性"新建/编辑"表单

## 背景

用户反馈(2026-09-18,在小改 L 修复 boolean 行可见性之后):

> 切换 type 时下列 / 多选 / 布尔型 没有弹出对应的数据

jsdom 单元测试模拟下 bind() 流程显示:`refresh(type)` 在切到 select/multiselect/boolean 时正确显示容器 + 填入行(默认 1 行空 row / 2 行 {0:否,1:是} 或解析后的 pairs)。但**真实浏览器**下容器仍看不到。

排查路径:
1. type_switcher.phtml 用 `document.getElementById('options_csv')` 查找 input — 假设 id 与 name 一致
2. 但某些 Magento 后台模板(`Varien_Data_Form`)在 form 容器 id 非默认情况下,可能给 field id 加前缀(如 `edit_form[options_csv]` 或 `edit_form_options_csv`)
3. id 找不到 → `optionsEl = null` → bind() 内部 `mountOptionsEditor({ optionsEl: null, ... })` → mountOptionsEditor line 622 `if (!optionsEl) return null` → **容器从未挂载到 DOM**
4. 切 type 时 `optEditor = null` → `optEditor.refresh(t)` 跳过 → 无任何视觉变化

## 决策

type_switcher.phtml 增加 `findByName(name)` 辅助函数,**优先用 `querySelector('[name=...]')` 查找**,fallback `getElementById`:

```js
function findByName(name) {
    return document.querySelector('select[name="' + name + '"]')
        || document.querySelector('input[name="' + name + '"]')
        || document.querySelector('textarea[name="' + name + '"]')
        || document.getElementById(name);
}
```

三种 type 的 input 都用这个 lookup:
- `field_type`(select)
- `options_csv`(text input)
- `default_value`(text input)

Form.php 的 `addField(name, type, ...)` 第二参 `name` 始终是 `'options_csv'` / `'default_value'` / `'field_type'` — **name 永远稳定**,只有 id 可能因 form 容器配置而变化。

## 后果

- 正面:兼容 Magento Form 任何 id 渲染策略(id = name / id 带前缀 / id 带 form id namespace)
- 负面:无 — fallback 保留 id 查找
- 兼容:旧 id = name 场景仍走 `getElementById`(无开销差异)

## 实施范围

| 文件 | 改动 |
|---|---|
| `app/design/adminhtml/default/default/template/xfe_carrier/custom_attribute/edit/form/type_switcher.phtml` | 加 findByName 辅助函数 + 三个 lookup 都用 |
| `tests/js/run-tests.js` | +1 个 describe 小改 M(4 个 it,覆盖 id 前缀场景下 text→select/boolean/multiselect 容器正确挂载) |
| `task_plan.md` | 阶段 18 / 小改 M |
| `docs/architecture/xfe-carrier-custom-attribute-form-switcher-js.md` | §8 修订记录 |

预期测试基线:JS 162 → 166 passed(+4),PHP 452 维持。
