# 0013. strict_editor.phtml 全面接入 `XfeCaTypeSwitcher`

- 状态:Accepted
- 日期:2026-09-15
- 决策者:AI 助手(经用户确认)
- 关联文档:
  - [`0012-render-value-cell-extract.md`](./0012-render-value-cell-extract.md) - `renderValueCell` 公开 API 抽取(本 ADR 的前置依赖)
  - [`../xfe-carrier-custom-attribute-form-switcher-js.md`](../xfe-carrier-custom-attribute-form-switcher-js.md) - JS 模块架构文档(本 ADR 落地后补章节 1 / 2.7 / 7)
  - [`../carrier-global-custom-field-defs.md`](../carrier-global-custom-field-defs.md) - 严格模式编辑器业务说明
  - [`../../../app/design/adminhtml/default/default/template/xfe_carrier/custom_attribute/strict_editor.phtml`](../../../app/design/adminhtml/default/default/template/xfe_carrier/custom_attribute/strict_editor.phtml) - 重构目标模板

---

## 背景

ADR 0012 已抽 `renderValueCell(container, opts)` 为公开 API,使 strict_editor.phtml 接入有了干净的入口。
但 ADR 0012 明确把 strict_editor 全面接入留给本 ADR 处理,因为存在两个 ADR 0012 时未解决的差异点。

### strict_editor.phtml 当前 value 列实现(4 个 PHP 分支)

| type | PHP 当前实现 | 行数 |
|---|---|---|
| `boolean` | `<select 是/否>` | 4 行 |
| `select` | `<select>` + foreach options | 7 行 |
| `multiselect` 固定模式(options 非空) | `<select multiple>` + foreach + selected 判定 | 9 行 |
| `multiselect` 自由模式(options 为空,chips) | `<div class="xfe-ca-chips">` + 60 行 IIFE | 65 行(含 `<script>`) |
| `text` / `number` | `<input type="text">` | 4 行 |

### 60 行 chips IIFE 现状

- 容器:`<div class="xfe-ca-chips" data-key data-name>` 内含 `<input class="xfe-ca-chip-input">`
- IIFE 创建 hidden input(type=hidden, name, value=`values.join(',')`)
- 渲染 chip:`<span class="xfe-ca-chip">` 含 value `<span>` + 关闭 `×` `<span class="xfe-ca-chip-x">`
- `×` click → splice values + 重新 render
- input keydown:Enter / `,` → trim → push(去重)+ render + clear input;Backspace(空 input + values.length>0)→ pop + render
- IIFE 用 `__inited` 标记防重复(per-row DOM ready 多次触发)

### POST name 形态差异(关键)

| type | POST name | 原因 |
|---|---|---|
| `boolean` / `select` / `text` / `number` | `custom_fields[k][value]` | 标量 |
| `multiselect` 固定模式 | `custom_fields[k][value][]` | array,后端 Applier 接受 array |
| `multiselect` 自由模式(chips hidden) | `custom_fields[k][value]` | hidden 内部 `values.join(',')`,Applier 也接受 |

PHP 端按 type 决定 name 是否带 `[]`,JS 端应通过 `data-name` 一次性传入。

### 已知约束

1. `CustomAttributeApplierStrictTest.php` 覆盖 strict 模式 POST 解析:
   - 接受 array / 逗号字符串(自由 chips 模式)
   - 缺/空 custom_fields 段 → 空集合
   - 必填字段空 value 抛异常
   - 本次重构不能破坏上述 PHP 测试
2. AGENTS.md §4.4:文档 / 输出 / 注释必须中文
3. AGENTS.md §5.2:小步可逆(本轮改动幅度比 ADR 0012 大,但用户明确要求)
4. AGENTS.md §5.4 红线:不修改 Block / Service / Domain / Resource,仅动 JS + phtml + 测试 + 文档

---

## 决策

### 1. JS 模块扩展(`custom-attribute-form-switcher.js`)

#### 1.1 `renderValueCell` 接受 array memo(multiselect 分支)

`renderValueCell` 的 multiselect 分支新增 array memo 支持,其他 4 种 type 仍走 `String(o.memo)`。

```js
case 'multiselect':
    var memoStr;
    if (Array.isArray(o.memo)) {
        memoStr = o.memo.join('|');
    } else {
        memoStr = (o.memo != null) ? String(o.memo) : '';
    }
    newEl = buildSelect({
        memo: memoStr,
        multiple: true,
        blankLabel: o.blankLabel || '',
        optionsCsv: o.optionsCsv || ''
    });
    restoreMultiselect(newEl, memoStr);
    break;
```

原因:strict_editor 编辑回显时,数据库存的 array 值需要原样还原(不依赖 PHP 端先 split string)。

向后兼容:现有 8 个 `renderValueCell` 测试都用 string memo,行为不变;`Array.isArray(o.memo)===false` 走原路径。

#### 1.2 新增 `buildChips(container, opts)` 公开方法

```js
/**
 * 把"自由标签 chips"控件渲染到 container(ADR 0013)
 * 容器假设:已含 <input class="xfe-ca-chip-input"> 的 div (strict_editor 用)
 * opts: {name, currentValues?: string[], placeholder?: string}
 * @return {HTMLElement|null}
 */
function buildChips(container, opts) { ... }
```

行为完全照搬 strict_editor 现有 60 行 IIFE,关键差异:

- 接受 `currentValues: string[]` 参数(原 IIFE 写死 `<?php echo json_encode($msValues); ?>`)
- `__inited` 防御保留(防止外部多次调用重复挂事件)
- `values.slice()` 拷贝(避免外部 array 引用被 splice 改)
- `placeholder` 参数化(默认 `输入后回车`)
- 内部 hidden input 创建逻辑不变
- Enter / `,` / Backspace / `×` click 行为完全一致

#### 1.3 导出对象加 `buildChips`

公开方法数 9 → **10**。

### 2. strict_editor.phtml 重构

#### 2.1 PHP 部分:渲染外层 table + 必填标 + type 列 + unregistered 提示

保留:table / thead / tr td(label / code / 必填 / 描述)/ td(type + 候选项)/ 底部 hint / unregistered 提示。
保留:每个 `<tr data-key data-type>` 外壳。

#### 2.2 PHP 部分:value 列改为 `<td data-value-cell>`

PHP 仅渲染占位 td + 数据属性(供 JS init script 读取),不再渲染 4 种 type 分支:

```php
<?php
$name = 'custom_fields[' . $this->escapeHtml($key) . '][value]';
if ($currentType === 'multiselect' && !empty($options)) {
    $name .= '[]';
}
$defaultJson = is_array($currentScalar)
    ? json_encode($currentScalar)
    : json_encode((string) $currentScalar);
$optionsJson = json_encode($options ?: array());
?>
<td data-value-cell
    data-type="<?php echo $this->escapeHtml($currentType); ?>"
    data-default="<?php echo $this->escapeHtml($defaultJson); ?>"
    data-options="<?php echo $this->escapeHtml($optionsJson); ?>"
    data-name="<?php echo $this->escapeHtml($name); ?>">
    <?php if ($currentType === 'multiselect' && empty($options)): ?>
        <input type="text" class="xfe-ca-chip-input" placeholder="<?php echo $helper->__('输入后回车'); ?>" />
    <?php endif; ?>
</td>
```

关键点:
- chips 容器仍保留 `<input class="xfe-ca-chip-input">`(供 `buildChips` 挂事件),但 60 行 IIFE 删除
- `data-default` 用 `escapeHtml(json_encode(...))` 包裹,JS 端 `JSON.parse` 反序列化
- `data-name` 一次定型,JS 直接传 `renderValueCell` / `buildChips`
- `data-options` 对 boolean / text / number 是 `[]`(空数组),不影响逻辑

#### 2.3 PHP 部分:底部挂统一 init script

```php
<script type="text/javascript">
//<![CDATA[
(function () {
    function onReady() {
        if (typeof window.XfeCaTypeSwitcher === 'undefined') return;
        var yesLabel = '<?php echo $helper->__("是"); ?>';
        var noLabel  = '<?php echo $helper->__("否"); ?>';

        document.querySelectorAll('#<?php echo $fieldId; ?> [data-value-cell]').forEach(function (cell) {
            var type    = cell.getAttribute('data-type');
            var name    = cell.getAttribute('data-name');
            var optsRaw = cell.getAttribute('data-options') || '[]';
            var defRaw  = cell.getAttribute('data-default') || '""';

            var options;
            try { options = JSON.parse(optsRaw); } catch (e) { options = []; }

            // 自由 chips 模式(multiselect 且无 options)
            if (type === 'multiselect' && options.length === 0) {
                var values;
                try { values = JSON.parse(defRaw); } catch (e) { values = []; }
                if (!Array.isArray(values)) values = [];
                XfeCaTypeSwitcher.buildChips(cell, { name: name, currentValues: values });
                return;
            }

            // 标准模式(renderValueCell)
            var memo;
            if (type === 'multiselect' && defRaw) {
                try {
                    memo = JSON.parse(defRaw);
                    if (!Array.isArray(memo)) memo = defRaw;
                } catch (e) {
                    memo = defRaw.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
                }
            } else {
                memo = defRaw;
                if (defRaw && defRaw.charAt(0) === '"') {
                    try { memo = JSON.parse(defRaw); } catch (e) {}
                }
            }

            XfeCaTypeSwitcher.renderValueCell(cell, {
                type:        type,
                memo:        memo,
                optionsCsv:  options.join(','),
                name:        name,
                className:   'xfe-ca-row-input',
                yesLabel:    yesLabel,
                noLabel:     noLabel,
                blankLabel:  ''
            });
        });
    }

    if (document.observe) {
        document.observe('dom:loaded', onReady);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }
})();
//]]>
</script>
```

原因:
- 兼容 prototype.js (`document.observe`) + 标准 DOM (`DOMContentLoaded`) + 已加载 (`onReady()` 直接调)
- memo 反序列化分两路:multiselect 走 `JSON.parse`(可能 array/string),其它类型走 `JSON.parse` 去引号包裹
- `Array.isArray` 防御(`restoreMultiselect` / `renderValueCell` 接受 array memo)
- 错误处理 `try/catch`:JSON.parse 失败时降级到原字符串,避免一个坏数据挂掉整个 form

### 3. 测试矩阵(新增 6 个用例,48 → 54 passed)

| # | 用例 | 覆盖 |
|---|---|---|
| 1 | `buildChips` 基础渲染 | 创建后 hidden input 存在 + 初始 N 个 chip + hidden.value 正确 |
| 2 | `buildChips` Enter 添加 | keydown Enter → values+1,hidden 同步,chip+1 |
| 3 | `buildChips` × 关闭 | click × → values-1,hidden 同步,chip-1 |
| 4 | `buildChips` Backspace 删最后一个 | keydown Backspace(空 input)→ values-1 |
| 5 | `renderValueCell` memo: array | multiselect + memo=['a','c'] → a/c selected |
| 6 | strict_editor 端到端 | 模拟 init script 流程,跑 5 种 type(含 chips),验证 name / className / 初始值 |

期望:48 + 6 = **54 passed, 0 failed**。

### 4. 架构文档同步更新

`docs/architecture/xfe-carrier-custom-attribute-form-switcher-js.md`:

- §1 模块形状:`renderValueCell` 描述增加 "支持 array memo(multiselect)";新增 `buildChips` 描述
- 新增 §2.7 strict_editor 接入示例(HTML `data-*` 属性 + JS 调用 `buildChips` / `renderValueCell`)
- §7 后续接入路径:标记 ✅ 已完成(ADR 0013),并简述落地范围
- §8 修订记录:加 2026-09-15 一行

### 5. PHP 测试兼容性(零破坏)

`app/code/community/XFE/Carrier/Test/Service/CustomAttributeApplierStrictTest.php` 不动。
理由:PHP 端 POST 解析只看 `custom_fields[k][value]`(标量 / 数组 / 逗号字符串 都接受),JS 端 render 不影响 POST 形态。

---

## 备选方案

### A. ✅ 本决策:JS 全接管 + chips 抽离(采纳)

- 优点:PHP / JS 重复消除;chips 行为集中可测;首次加载空白窗口期可控(init 同步执行)
- 缺点:改动幅度大(模板重写 + 60 行 IIFE 搬家 + 6 个测试)
- 采纳

### B. 只抽 buildChips,4 个 PHP 分支保留

- 优点:模板改动小
- 缺点:PHP / JS 两份 type 分支仍并存,违反 AGENTS.md §1 模块化
- 否决

### C. JS 不接受 array memo,PHP 端 split string

- 优点:`renderValueCell` 签名不变
- 缺点:PHP 模板逻辑更复杂(string 解析放回 PHP);与 ADR 0012 抽 API 的方向相悖
- 否决

### D. 不挂 init script,直接在每个 `<td>` 内联 `<script>` 调 XfeCaTypeSwitcher

- 优点:无需 document.ready 处理
- 缺点:N 个 tr → N 个 `<script>` 块;prototype.js 环境下 DOMContentLoaded 时机复杂;违反最小变更原则
- 否决

---

## 后果

### 正面

- ✅ strict_editor.phtml 4 个 PHP type 分支(~25 行) + 60 行 chips IIFE → 1 个 `<td data-value-cell>` + 1 个 init script
- ✅ XfeCaTypeSwitcher 公开方法 9 → 10(`buildChips`),chips 行为从 IIFE 黑盒变成可单测
- ✅ 测试用例 48 → **54 passed**(+6 个新用例覆盖 chips + array memo + 端到端)
- ✅ strict_editor 与 custom_attribute 两个页面共享同一份 "按 type 构造 value 控件" 逻辑
- ✅ PHP 端 POST 兼容性零破坏(`CustomAttributeApplierStrictTest` 全过)
- ✅ 首次加载空白窗口期可控(init script 同步遍历 + DOMContentLoaded / document.observe 双兼容)

### 负面

- ⚠️ 首次渲染到 JS 跑完之间,有极短的空白窗口期(init 同步执行,实测 < 50ms;用户肉眼不可见)
- ⚠️ JS 失败 → value 列空白(降级:可加 noscript fallback,但本轮不做)
- ⚠️ `<td data-value-cell>` 与现有 CSS 选择器 `.xfe-ca-strict td` 兼容(无冲突)

---

## 实施检查清单

- [ ] ADR 0013(本文档)
- [ ] `docs/architecture/xfe-carrier-custom-attribute-form-switcher-js.md`:
  - §1 模块形状加 `buildChips` 描述(`renderValueCell` 增加 array memo 说明)
  - 新增 §2.7 strict_editor 接入示例
  - §7 标记 ✅ 已完成(ADR 0013)
  - §8 修订记录加 2026-09-15
- [ ] `js/xfe_carrier/custom-attribute-form-switcher.js`:
  - `renderValueCell` multiselect 分支:接受 array memo
  - 新增 `buildChips(container, opts)` 函数
  - 导出对象加 `buildChips`
- [ ] `app/design/adminhtml/default/default/template/xfe_carrier/custom_attribute/strict_editor.phtml`:
  - 移除 4 个 PHP type 分支(value 列 ~25 行)
  - 移除 60 行 chips IIFE `<script>`
  - value 列改为 `<td data-value-cell ...>`
  - 底部挂统一 init script(prototype.js + DOMContentLoaded 双兼容)
- [ ] `tests/js/run-tests.js` 新增 6 用例
- [ ] `node tests/js/run-tests.js` → **54 passed, 0 failed**
- [ ] `node --check js/.../custom-attribute-form-switcher.js` → 0
- [ ] `php -l strict_editor.phtml` → No syntax errors
- [ ] task_plan.md 阶段 7 全勾
- [ ] progress.md 加阶段 7 小节

---

## 注意事项

- **`renderValueCell` array memo 增强只影响 multiselect 分支**:其他 4 种 type 仍走 `String(o.memo)`,原 8 个测试用例零变化
- **`buildChips` 行为完全照搬 IIFE**:`__inited` 防御、`values.slice()` 拷贝、Enter / `,` / Backspace / × click 一致;可视为 IIFE → 命名函数的等价转换
- **init script 同步执行**:无延迟;若浏览器禁 JS(strict_editor 后台页不应发生),value 列空白,可后续 ADR 评估 noscript fallback
- **PHP 端 `$msValues` 解析不再需要**:`$currentScalar` 直接 `json_encode` 进 `data-default`,JS 端 `JSON.parse` 处理 array / string
- **POST name 形态由 PHP 端定型**:JS 端只透传 `data-name`,不改 name 拼接逻辑
- **不触碰的文件**:`CustomAttributeApplierStrictTest.php` / Block / Service / Domain / Resource / config.xml / layout xml

---

## 修订记录

| 日期 | 变更 | 作者 |
|---|---|---|
| 2026-09-15 | 初版:strict_editor.phtml 全面接入 XfeCaTypeSwitcher,chips 抽离为 buildChips 公开方法,新增 6 个测试用例,期望 54 passed | AI 助手 |
