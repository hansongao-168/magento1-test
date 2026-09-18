# XFE_Carrier 自定义属性表单联动 — JS 模块与单测

> 主题:`XfeCaTypeSwitcher` 全局对象(prototype.js 风格)的模块形状、
> 与 Magento layout 集成方式、单测约定。
>
> 状态:Accepted(2026-09-14)
>
> 关联文档:
> - [`carrier-custom-attribute-form-types.md`](./carrier-custom-attribute-form-types.md) — 联动矩阵
> - [`decisions/0008-custom-attribute-form-ux.md`](./decisions/0008-custom-attribute-form-ux.md) — 选型 ADR
> - [`decisions/0009-js-extract-and-tests.md`](./decisions/0009-js-extract-and-tests.md) — JS 抽取 + 单测选型 ADR
> - [`decisions/0012-render-value-cell-extract.md`](./decisions/0012-render-value-cell-extract.md) — renderValueCell 抽取 ADR(2026-09-15)
- [`decisions/0013-strict-editor-js-rendering.md`](./decisions/0013-strict-editor-js-rendering.md) — strict_editor 全面 JS 渲染 ADR(2026-09-15)

---

## 1. 模块形状

文件:`js/xfe_carrier/custom-attribute-form-switcher.js`

```
window.XfeCaTypeSwitcher = (function () {
    'use strict';

    /** 字符串 → 字符串[] — 拆 options_csv */
    function parseOptionsCsv(s) { ... }

    /** memo: string|null, withNumberValidator: bool → HTMLInputElement */
    function buildTextInput(opts) { ... }

    /** memo: string|null, multiple: bool → HTMLSelectElement */
    function buildSelect(opts) { ... }

    /** memo: '' | '0' | '1' → HTMLDivElement */
    function buildBooleanRadios(opts) { ... }

    /** Element → string — 跨 input / select / select-multiple / div 含 radio */
    function readCurrentValue(el) { ... }

    /** HTMLSelectElement × string|null → void */
    function restoreMultiselect(sel, memo) { ... }

    /** Element × bool → void — 切整行 <tr> 显隐 */
    function setRowVisible(el, visible) { ... }

    /** 把指定 type 的 value 控件渲染到 container(ADR 0012 抽取,供 strict_editor 等复用)
     *  opts: {type, memo?, optionsCsv?, yesLabel?, noLabel?, blankLabel?,
     *         withNumberValidator?, name?, className?}
     *  memo: multiselect 时支持 string|array(string 用 "|" 分隔,array 原样 join) — ADR 0013 增强 */
    function renderValueCell(container, opts) { ... }

    /** 把"自由标签 chips"控件渲染到 container(ADR 0013 抽取,strict_editor 自由 multiselect 用)
     *  容器假设:已含 <input class="xfe-ca-chip-input"> 的 div
     *  opts: {name?, currentValues?: string[], placeholder?: string}
     *  行为:创建 hidden input(承载 values.join(',')) + 渲染 chip + 挂 Enter/,/Backspace/× 事件
     *  防重复:`__inited` 标记,二次调用直接返回原 container */
    function buildChips(container, opts) { ... }

    /** 主入口 — {fieldTypeEl, optionsEl, defaultEl, yesLabel, noLabel, blankLabel} → void */
    function bind(opts) {
        // 监听 field_type / options_csv change
        // 首次跑一次同步当前 field_type
    }

    /** 找 <p id="FIELDID_note" class="note"> 元素并更新 textContent(小改 A)
     *  容器找不到 / noteText 为空 → no-op(沿用 Form Block 静态文案兜底) */
    function updateNote(fieldId, noteText) { ... }

    /** submit 前预校验(小改 B):返回 {ok, errors:[{field, message}]}
     *  校验规则:
     *    1. select 类型 → options_csv 必须有非空白值(否则 Domain 抛异常)
     *    2. boolean 类型 → default_value 必须 ∈ {'0', '1'}(避免非 0/1 误提交)
     *    3. multiselect 固定模式 → default_value 各项必须 ∈ options(防 stale 数据)
     *  不动 Server:服务端 Domain::__construct 仍是真权威,客户端预校验只是 UX 提示 */
    function validateForm(opts) { ... }

    return { parseOptionsCsv, buildTextInput, buildSelect, buildBooleanRadios,
             readCurrentValue, restoreMultiselect, setRowVisible,
             renderValueCell, buildChips, updateNote, validateForm, bind };
}());
```

约束:

- 不依赖任何 prototype.js API,纯浏览器 DOM — 这样可以脱离 Magento 跑测试
- 所有方法挂在一个全局对象 `XfeCaTypeSwitcher` 上,prototype.js 风格
- 暴露的 12 个方法都是可独立测试的纯函数 / 副作用函数(ADR 0009 → 8,ADR 0012 → 9,ADR 0013 → 10,小改 A → 11,小改 B → 12)
- IIFE 闭包,内部 helper(若有)不暴露

---

## 2. 与 Magento layout 集成

`app/design/adminhtml/default/default/layout/xfecarrier.xml` 在
`adminhtml_carrier_customattribute_new` 与 `_edit` 两个 handle 内:

```xml
<reference name="head">
    <action method="addItem">
        <type>js</type>
        <name>xfe_carrier/custom-attribute-form-switcher.js</name>
    </action>
</reference>
```

`app/design/adminhtml/default/default/template/xfe_carrier/custom_attribute/edit/form/type_switcher.phtml`
简化为:

```php
<script type="text/javascript">
//<![CDATA[
(function () {
    var yesLabel   = '<?php echo Mage::helper("xfe_carrier")->__("是"); ?>';
    var noLabel    = '<?php echo Mage::helper("xfe_carrier")->__("否"); ?>';
    var blankLabel = '<?php echo Mage::helper("xfe_carrier")->__("-- 请选择 --"); ?>';

    document.observe('dom:loaded', function () {
        if (typeof window.XfeCaTypeSwitcher !== 'undefined') {
            XfeCaTypeSwitcher.bind({
                fieldTypeEl: $('field_type'),
                optionsEl:   $('options_csv'),
                defaultEl:   $('default_value'),
                yesLabel: yesLabel,
                noLabel: noLabel,
                blankLabel: blankLabel
            });
        }
    });
})();
//]]>
</script>
```

---

## 2.5 测试运行时 — jsdom (ADR 0010)

2026-09-14 升级:测试运行时从 ADR 0009 时期的「自写 mini DOM stub」升级到 **jsdom**。

理由(详见 ADR 0010):

1. jsdom 真实实现 DOM API,支持 `addEventListener` / `dispatchEvent` 真实触发
2. 可测 `bind()` 端到端流程(切 field_type → 验证 default_value 重建)
3. 与真浏览器 99% 兼容,测试结果更可靠
4. jsdom 是 npm 生态标准,后续如需 prototype.js 兼容测试也能跑

安装:`npm install jsdom --save-dev`(已记录在 package.json devDependencies)
当前 jsdom 版本:29.1.1

测试用例数:从 32 增加到 40(新增 8 个 `bind()` 端到端用例)。

---

### 2.6 独立使用 `renderValueCell`(ADR 0012)

`renderValueCell(container, opts)` 是从 `bind()` 内部 `switchType` 抽出的"按 type 渲染 value 控件 + 替换 DOM"逻辑,
**不绑定任何事件**,只做"把控件塞进 container"一件事。典型用例:

```html
<!-- strict_editor 等只读 type 的页面可这样用: -->
<td data-value-cell data-type="text"
    data-default="some value"
    data-options=""
    data-name="custom_fields[some_key][value]"></td>
```

```js
// init script 末尾
var yesLabel   = "是";
var noLabel    = "否";
var blankLabel = "-- 请选择 --";
document.querySelectorAll("[data-value-cell]").forEach(function (cell) {
    XfeCaTypeSwitcher.renderValueCell(cell, {
        type:        cell.getAttribute("data-type"),
        memo:        cell.getAttribute("data-default"),
        optionsCsv:  cell.getAttribute("data-options") || "",
        name:        cell.getAttribute("data-name"),
        className:   "xfe-ca-row-input",
        yesLabel:    yesLabel,
        noLabel:     noLabel,
        blankLabel:  blankLabel
    });
});
```

> ⚠️ 本 ADR 0012 **未接入 strict_editor.phtml**(chips 自由标签是 strict_editor 独有 UX,需 ADR 0013 单独评估)。
> 上述示例是 **预期接口形状**,实际接入见 §7 后续接入路径。
>
> 👉 **已落地**:见 §2.7(ADR 0013 2026-09-15)

### 2.7 strict_editor.phtml 接入(ADR 0013 已落地)

`strict_editor.phtml`(全局自定义属性的"严格模式编辑器")与 `edit/form/type_switcher.phtml`
共享同一份 "按 type 构造 value 控件" 逻辑,但有两个差异点:

1. POST name 不同(`default_value` vs `custom_fields[key][value]`)
2. strict_editor 独有 chips 自由标签 UX(60 行 IIFE,Enter / `,` / Backspace / × 操作)

ADR 0013 把 chips 行为抽成 `buildChips(container, opts)` 公开方法,并把 strict_editor.phtml 改为:

- PHP 只渲染外层 table + 必填标 + type 列 + unregistered 提示
- value 列渲染 `<td data-value-cell data-type data-default data-options data-name>`
- 底部挂统一 init script(prototype.js `document.observe` + 标准 DOM `DOMContentLoaded` 双兼容)

#### strict_editor.phtml value 列 HTML 形态

```html
<tr data-key="color" data-type="multiselect">
    <td><strong>颜色</strong><code>(color)</code></td>
    <td>multiselect <div>候选项:红,绿,蓝</div></td>
    <td data-value-cell
        data-type="multiselect"
        data-default="[&quot;红&quot;,&quot;蓝&quot;]"
        data-options="[&quot;红&quot;,&quot;绿&quot;,&quot;蓝&quot;]"
        data-name="custom_fields[color][value][]"></td>
</tr>
```

自由 chips 模式(`data-options="[]"` 且 `data-type="multiselect"`)td 内额外含
`<input class="xfe-ca-chip-input">`(供 `buildChips` 挂事件)。

#### 模板底部 init script(init 同步执行,空白窗口期 < 50ms)

```js
(function () {
    function onReady() {
        if (typeof window.XfeCaTypeSwitcher === 'undefined') return;
        var yesLabel = '是';
        var noLabel  = '否';

        document.querySelectorAll('#<fieldId> [data-value-cell]').forEach(function (cell) {
            var type    = cell.getAttribute('data-type');
            var name    = cell.getAttribute('data-name');
            var optsRaw = cell.getAttribute('data-options') || '[]';
            var defRaw  = cell.getAttribute('data-default') || '""';

            var options;
            try { options = JSON.parse(optsRaw); } catch (e) { options = []; }

            // 自由 chips 模式
            if (type === 'multiselect' && options.length === 0) {
                var values;
                try { values = JSON.parse(defRaw); } catch (e) { values = []; }
                if (!Array.isArray(values)) values = [];
                XfeCaTypeSwitcher.buildChips(cell, { name: name, currentValues: values });
                return;
            }

            // 标准模式
            var memo;
            if (type === 'multiselect' && defRaw) {
                try { memo = JSON.parse(defRaw); if (!Array.isArray(memo)) memo = defRaw; }
                catch (e) {
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
```

#### POST name 形态(由 PHP 端定型)

| type | POST name | 备注 |
|---|---|---|
| `text` / `number` / `boolean` / `select` | `custom_fields[k][value]` | 标量 |
| `multiselect` 固定模式 | `custom_fields[k][value][]` | array,Applier 接受 |
| `multiselect` 自由模式(chips) | `custom_fields[k][value]` | hidden 内部 `values.join(',')`,Applier 也接受 |

PHP 端 `data-name` 一次定型,JS 端只透传。

### 2.8 动态 note 文案(小改 A,2026-09-17)

新增自定义属性页的 `options_csv` 与 `default_value` 两个字段的 `note`(Magento Varien 渲染为 `<p id="FIELDID_note" class="note">`)
原本是静态文案(`Form Block` 写死),用户在 type 之间切换时 note 不变。
本次小改后,JS 在 `switchType()` 内根据 `window.XfeCaFormNotes` 注入了 type-specific 提示。

#### PHP 注入(type_switcher.phtml 顶部 `<script>`)

```php
<script type="text/javascript">
window.XfeCaFormNotes = <?php echo json_encode(array(
    'default_value' => array(
        'text'        => Mage::helper('xfe_carrier')->__('任意字符串'),
        'number'      => Mage::helper('xfe_carrier')->__('数字(支持小数)'),
        'select'      => Mage::helper('xfe_carrier')->__('从上方"候选项"里选一个'),
        'multiselect' => Mage::helper('xfe_carrier')->__('从上方"候选项"里多选(按住 Ctrl/Shift)'),
        'boolean'     => Mage::helper('xfe_carrier')->__('选择 是 / 否'),
    ),
    'options_csv' => array(
        'select'      => Mage::helper('xfe_carrier')->__('必填,逗号分隔(如 红,绿,蓝)'),
        'multiselect' => Mage::helper('xfe_carrier')->__('可选,逗号分隔。留空 = 自由标签输入'),
    ),
)); ?>;
</script>
```

#### JS 行为

- 新增公开方法 `updateNote(fieldId, noteText)`:`document.getElementById(fieldId + '_note').textContent = noteText`
- `bind()` 的 `switchType()` 在 `setRowVisible` 之后调 `updateNote` 两次(对 `default_value` 与 `options_csv`)
- 文案来源:优先 `window.XfeCaFormNotes[fieldName][type]`,未注入或缺失 → 不替换(沿用 Form Block 静态 note 兜底)
- 严格模式编辑器(strict_editor.phtml)不受影响:它没有 `<p class="note">` 元素,`updateNote` 直接 no-op

#### 不破坏静态文案

- PHP 注入脚本在 `<head>` 末尾,JS 在 `DOMContentLoaded` 后才跑 → 服务端渲染的首屏仍是 Form Block 的静态 note
- JS 失败 / 禁 JS → 静态 note 仍然可见

### 2.9 submit 前客户端预校验(小改 B,2026-09-17)

`XfeCaTypeSwitcher.bind()` 在 hook 完 `field_type` / `options_csv` change 后,还会 hook form 的 `submit` 事件,
在提交前调 `validateForm(opts)` 做轻量校验,**失败则 `preventDefault()` 并在 form 顶部展示错误**。

#### 校验规则

| field_type | 规则 | 服务端对应 |
|---|---|---|
| `select` | `options_csv` 必须有 ≥1 个非空白值(用 `parseOptionsCsv` 解析) | `CustomField::__construct` 抛 `InvalidArgumentException` |
| `boolean` | `default_value` 必须是 `'0'` 或 `'1'` | `coerceValue()` 用 `(bool)` 强转,'yes'/'no' 会变 `true`,意外行为 |
| `multiselect`(固定模式,`options_csv` 非空) | `default_value` 各项必须 ∈ `options` | `coerceValue()` 抛 `InvalidArgumentException` |

#### 返回结构

```js
{
    ok: false,
    errors: [
        { field: 'options_csv', message: 'select 类型必须填写候选项' },
        { field: 'default_value', message: 'boolean 类型的默认值必须是 0 或 1' }
    ]
}
```

#### 错误展示

- 失败时在 form 顶部插入 `<ul class="xfe-ca-form-errors">`,内含每条错误的中文文案
- 同时给 errored 字段加 CSS class `xfe-ca-field-error`(红色边框)
- 用户修改字段后,`switchType()` / `onOptionsChange()` 会清掉对应的错误样式

#### 服务端真权威

客户端预校验**只是 UX 提示**,不替代 Domain 校验。Server `CustomAttributeService::_normalizePost` 仍然:
1. 通过 `new CustomAttribute(...)` 强制不变量
2. 失败 → `Mage_Core_Exception` → Controller 转 `Mage::getSingleton('adminhtml/session')->addError(...)` 提示

#### 不触碰的文件

- `Edit/Form.php` 字段定义 / `Domain/` / `Service/` / `Resource/` 都不动
- strict_editor 看不到 Form Block 字段,不受影响

### 2.10 options_csv 变更时服务端兼容性保护(小改 C,2026-09-17)

> **本节描述的服务端能力,是为了兜住 JS 端只能 UX 提示、不能强制迁移的缺口**。
> 修改落在 `Model/Service/CustomAttributeService.php`(L3,因应),
> 不动 `Domain/CustomField.php`(L1)和 `Model/Resource/CustomAttribute.php`(L2)。

#### 背景

当 admin 在编辑页修改 `options_csv`(给 select 增删候选项,或把 multiselect 从「自由输入」改成「固定选项」)
时,旧 `default_value` 可能落在新 options 之外,Domain 层的 `coerceValue()`(见 `Domain/CustomField.php` line 201-260)
会抛 `InvalidArgumentException` → `Mage_Core_Exception`,体验差(整条记录保存失败,数据丢失)。

JS 端(小改 B 的 `validateForm`)只能**事前**提示,**事中**已经无法阻止 — 用户可能已经把 form 提交出去了。
服务端必须具备"接收 POST 时检测 options 变化 → 按策略处理 default_value"的能力。

#### 三种迁移策略

| 策略 | select 类型 | multiselect 类型 | boolean / number / text 类型 |
|---|---|---|---|
| `reject`(默认) | 抛 `Mage_Core_Exception`,列出非法 default 值 | 同左 | 不触发(options 与此类型无关) |
| `auto_clean` | `default_value` 设为 `null` | `default_value` 过滤为「只在 options 内的项」,若全被过滤掉则置 `[]` | 同上,不动 |
| `set_null` | `default_value` 设为 `null` | `default_value` 设为 `[]` | 同上,不动 |

> 默认 `reject` 保持向后兼容 — 老代码没传 `migration_strategy` 字段时,行为与改动前一致(抛异常,防止静默丢数据)。

#### `updateDef()` 调用流程(关键片段)

```php
// updateDef($id, $post):
$model = Mage::getModel('xfe_carrier/custom_attribute')->load($id);
$normalized = $this->_normalizePost($post, $isNew = false);   // 已有
$normalized['entity_type'] = (string) $model->getData('entity_type');
$normalized['field_key']   = (string) $model->getData('field_key');

// NEW(小改 C):检测 options 迁移
if ((string) $model->getData('options_csv') !== $normalized['options_csv']) {
    list($status, $incompatible, $oldOpts) = $this->_detectOptionsMigration(
        $model,
        $normalized['options_csv'],
        $normalized['field_type'],
        $normalized['default_value']
    );
    if ($status === 'incompatible') {
        $strategy = isset($post['migration_strategy'])
            ? (string) $post['migration_strategy']
            : self::MIGRATION_STRATEGY_REJECT;   // 'reject'
        $normalized = $this->_applyMigrationStrategy(
            $normalized, $incompatible, $strategy, $normalized['field_type']
        );
    }
}

$this->_applyNormalizedToModel($model, $normalized);
$model->save();
```

#### 触发条件

仅当 **新 `options_csv` ≠ 旧 `options_csv`** 时才检测。其他字段(label / is_required / sort_order / description)的变更**不会**触发迁移检测 — 它们与 default_value 的合法性无关。

#### 服务端常量

`XFE_Carrier_Model_Service_CustomAttributeService` 新增 3 个类常量:

```php
const MIGRATION_STRATEGY_REJECT    = 'reject';      // 默认,抛异常
const MIGRATION_STRATEGY_AUTO_CLEAN = 'auto_clean'; // 自动清洗
const MIGRATION_STRATEGY_SET_NULL  = 'set_null';    // 置空
```

#### 私有方法

- `_detectOptionsMigration($model, $newOptionsCsv, $newFieldType, $newDefaultValue): array`
  返回 `[$status, $incompatible[], $oldOptions[]]`
  - `$status`: `'ok'` 或 `'incompatible'`
  - `$incompatible`: 不在新 options 内的值(select=单个字符串,multiselect=数组)
  - `$oldOptions`: 仅供调试/日志用

- `_applyMigrationStrategy($normalized, $incompatible, $strategy, $newFieldType): array`
  返回修改后的 `$normalized`,按策略处理 `$normalized['default_value']`

#### JS 端配合(可选,本轮不强求)

`XfeCaTypeSwitcher.bind()` 检测到 `options_csv` 变化时,可以 `confirm()` 弹窗让 admin 选 strategy,
然后塞进 hidden input `migration_strategy`。**后端完全能独立工作**,前端只是 UX 增强。

如果要实现,需要:
- type_switcher.phtml 注入 `window.XfeCaMigrationStrategies = ['reject', 'auto_clean', 'set_null']`
- 在 `switchType()` / `onOptionsChange()` 检测 options_csv 实际变化时弹 confirm
- 用户选完 → 设 hidden input → 走正常 submit

#### 不触碰的文件

- `Domain/CustomField.php`(L1)— Domain 不变量(`coerceValue()`)不动
- `Model/Resource/CustomAttribute.php`(L2)— 数据访问层不动
- `Edit/Form.php` / `Edit.php` Controller — 前端字段定义不动
- `tests/js/*` — JS 测试不动(本轮零 JS 改动)

#### 向后兼容保证

1. POST 没传 `migration_strategy` → 默认 `reject` → 行为与改动前一致
2. options_csv 没变 → 完全跳过迁移检测,零开销
3. 字段类型是 text/number/boolean → options 与 default 无关,跳过
4. select/multiselect 但 default 为 null / 空 → 没有不兼容值,跳过

---



### 2.11 options_csv 变更时 JS 弹窗(小改 D,2026-09-17,§2.10 的 JS 端配合)

> 本节配套 §2.10(服务端 `_detectOptionsMigration` / `_applyMigrationStrategy`)。
> **后端已就绪,本节只加 JS 端 UX 增强**。即使 JS 失败 / 禁 JS / 弹窗被忽略,
> 服务端默认 `reject` 策略仍能保证数据安全(老代码行为)。

#### 设计原则

1. **零新依赖**:沿用浏览器原生 `confirm()`,不引入模态库
2. **后端真权威**:JS 只是 UX 提示,不替代服务端校验
3. **2 选项而非 3 选项**:`auto_clean` 覆盖 90% 场景;`set_null` 用法更极端,
   用户先清空 default 再改 options_csv 也能达到同样效果,不需要弹窗兜底
4. **取消 = 还原 options_csv**:用户拒绝迁移策略 → 自动还原到改之前的值,
   避免后续提交被后端 reject

#### 触发条件

`bind()` 内 `onOptionsChange()` 检测到 **options_csv 实际变化**(去除空白后比较)
且 **新 options 与现有 default_value 不兼容**(复用 §2.10 的检测逻辑)时,弹窗。

```
options 变化 → 标准化(parseOptionsCsv) → 比较 oldArr / newArr
  ├─ 相同 → 不弹窗(可能只是空白格式调整)
  └─ 不同 → 检查 default_value 是否仍在 newArr 内
       ├─ 兼容 → 不弹窗
       └─ 不兼容 → confirm() 弹窗
```

#### 弹窗文案

从 `window.XfeCaMigrationLabels.confirm` 读取(由 type_switcher.phtml 注入),
默认值:

```
"修改候选项(options_csv)会导致现有默认值失效。
点 [确定] = 自动清洗(只保留合法项,丢弃非法)
点 [取消] = 恢复候选项(请重新编辑)"
```

#### 流程

```js
function onOptionsChange() {
    var oldCsv = optionsEl._lastSeenCsv || '';     // 内部记忆
    var newCsv = optionsEl.value || '';
    if (oldCsv === newCsv) return;

    var result = XfeCaTypeSwitcher.promptMigrationStrategy({
        oldCsv: oldCsv, newCsv: newCsv,
        type: fieldTypeEl.value,
        defaultValue: readCurrentValue(defaultEl)
    });

    if (result === 'cancel') {
        optionsEl.value = oldCsv;   // 还原
        return;
    }
    if (result === 'auto_clean') {
        setHiddenMigrationStrategy('auto_clean');
    }
    // 'none' → 用户已选兼容路径,不设 hidden input,后端走默认 reject(实际上 reject 不会触发,因为已兼容)

    // 重建 value cell(沿用原有逻辑)
    defaultEl = renderValueCell(defaultEl.parentNode, {
        type: fieldTypeEl.value,
        memo: readCurrentValue(defaultEl),
        optionsCsv: newCsv,
        blankLabel: blankLabel
    });
    optionsEl._lastSeenCsv = newCsv;  // 更新记忆
}
```

#### 新增公开方法

`XfeCaTypeSwitcher.promptMigrationStrategy({oldCsv, newCsv, type, defaultValue}): string`

返回:
- `'none'` — 兼容,不需要迁移
- `'auto_clean'` — 用户确认 auto_clean(已注入 hidden input)
- `'cancel'` — 用户取消(已还原 options_csv)

#### 辅助私有函数(模块内)

- `_csvEffective(arr)`:把 options 数组排序后字符串化,用于比较"实际变化"
- `_wouldBeIncompatible(type, optionsArr, defaultValue)`:复用 §2.10 的不兼容检测逻辑
- `_setHiddenMigrationStrategy(value, form)`:在 form 内设置 `<input type="hidden" name="migration_strategy" value="...">`(若已存在则改 value)

#### type_switcher.phtml 注入

```php
window.XfeCaMigrationLabels = <?php echo json_encode(array(
    'confirm' => Mage::helper('xfe_carrier')->__(
        '修改候选项(options_csv)会导致现有默认值失效。\n'
        . '点 [确定] = 自动清洗(只保留合法项,丢弃非法)\n'
        . '点 [取消] = 恢复候选项(请重新编辑)'
    ),
)); ?>;
```

#### 不触碰的文件

- `js/xfe_carrier/custom-attribute-form-switcher.js` — 只加新公开方法 + 改 onOptionsChange,
  既有 12 个公开方法签名不变(`parseOptionsCsv` / `buildTextInput` / `buildSelect` /
  `buildBooleanRadios` / `readCurrentValue` / `restoreMultiselect` / `setRowVisible` /
  `renderValueCell` / `buildChips` / `updateNote` / `validateForm` / `bind`)
- 后端:`CustomAttributeService.php` 上一轮(§2.10)已就绪,本轮不动
- 测试:`CustomAttributeServiceMigrationTest.php` 不动

#### 向后兼容保证

1. `XfeCaTypeSwitcher.promptMigrationStrategy` 是新增方法,老调用方零影响
2. `onOptionsChange` 行为扩展(检测变化 + 弹窗),原本没传 options 的 case 不影响
3. 没注入 `XfeCaMigrationLabels` → 用内置默认文案,优雅降级
4. JS 失败 / 禁 JS → 后端 reject 兜底(老代码行为不变)
5. confirm() 被屏蔽(自动化测试)→ 测试可注入 stub 覆盖;生产环境 confirm 一定可用

---

### 2.12 CI 扩展覆盖 Carrier 套件(小改 E,2026-09-17)

> 之前 ADR 0018(2026-09-17)只把 XFE_Injection 套件接进 CI。
> 本节把 XFE_Carrier 的 4 个独立可运行 PHP 测试也接入 CI 统一入口。
> 让 PR 检查覆盖所有 PHP 断言,不需要再单独跑 Carrier 测试。

#### 触发条件

只要 `.gitlab-ci.yml` 的 `php-tests` job 或 `.github/workflows/php-tests.yml` 的 `php-tests` job 跑,
就会自动跑扩展后的 `tests/php/run-tests.php`,覆盖全部 8 个套件:

```
XFE_Injection (4)
  ├─ Unit: InjectionTest                          71 assertions
  ├─ Integration: CarrierLogisticTest             42 assertions
  ├─ Integration: DocumentUploadTest              49 assertions
  └─ Integration: PodServiceMainPathTest          37 assertions

XFE_Carrier (4)  ← 本节扩展
  ├─ Carrier: CustomAttributeApplierStrict        37 assertions
  ├─ Carrier: CustomFieldServiceBuildFromPost     18 assertions
  ├─ Carrier: CustomAttributeImportExport         65 assertions
  └─ Carrier: CustomAttributeServiceMigration     48 assertions (小改 C)
────────────────────────────────────────────────────
合计: 367 assertions / 0 failures
```

#### 改动

`tests/php/run-tests.php`:

1. **套件列表** 从 3 → 8(注入 4 + Carrier 4)
2. **解析逻辑** 兼容两种输出格式:
   - 格式 A:`Total: N assertions, M failures`(Injection / Migration)
   - 格式 B:`ALL PASS` + `[PASS]` 行(老 Carrier 套件,统计 `[PASS]` 数)
3. **docstring** 更新套件清单 + 计数(367 assertions)
4. **跳过** Magento 强依赖测试(`OrderRuleResolverTest` / `QuoteRuleResolverTest`,
   需要 Magento 引导 + PHP 5.6 兼容)

#### CI 配置无需改动

- `.gitlab-ci.yml` 的 `php-tests` job 已调 `php tests/php/run-tests.php` → 自动覆盖
- `.github/workflows/php-tests.yml` 同上
- `php-tests` job 的 timeout 3 分钟足够跑全部 8 个套件(实测 < 3 秒)

#### 验证(本地)

```bash
$ php tests/php/run-tests.php
...
========================================
SUMMARY
========================================
  PASS  Unit: InjectionTest                         71 assertions, 0 failures
  PASS  Integration: CarrierLogisticTest            42 assertions, 0 failures
  PASS  Integration: DocumentUploadTest             49 assertions, 0 failures
  PASS  Integration: PodServiceMainPathTest         37 assertions, 0 failures
  PASS  Carrier: CustomAttributeApplierStrict       37 assertions, 0 failures
  PASS  Carrier: CustomFieldServiceBuildFromPost    18 assertions, 0 failures
  PASS  Carrier: CustomAttributeImportExport        65 assertions, 0 failures
  PASS  Carrier: CustomAttributeServiceMigration (小改 C)    48 assertions, 0 failures
========================================
Total: 367 assertions, 0 failures
========================================
$ echo $?
0
```

退出码 0 → CI 护栏通过。

#### 不触碰的文件

- `.gitlab-ci.yml` / `.github/workflows/php-tests.yml` — 不动(已自动覆盖)
- `OrderRuleResolverTest.php` / `QuoteRuleResolverTest.php` — 跳过(需要 Magento 引导)
- `tests/js/*` — JS 测试独立于 PHP runner,继续走 `js-tests` job

---

### 2.13 错误样式 CSS 视觉化(小改 F,2026-09-17)

> 配套 §2.9(JS submit 前预校验) + §2.10(服务端迁移检测)。
> JS 已经在 field 上加 `xfe-ca-field-error` class、在 form 顶部加 `<ul class="xfe-ca-form-errors">` 块,
> 但此前是 inline `style.cssText`。本节把所有错误样式收敛到 type_switcher.phtml 的 `<style>` 块,
> 单一来源,便于维护。

#### 设计原则

1. **CSS 单一来源**:不再用 JS inline `style.cssText`,全部走 CSS class
2. **就近加载**:样式直接写在 type_switcher.phtml 里,只在自定义属性编辑页生效,不影响其他页
3. **覆盖默认 focus 样式**:`!important` 确保 input/select 获得焦点时仍是红色边框
4. **复选 checkbox/radio**:CSS 默认样式不漂亮,只对 input/select/textarea 生效

#### 改动

`app/design/adminhtml/default/default/template/xfe_carrier/custom_attribute/edit/form/type_switcher.phtml`:

```html
<style type="text/css">
/* 小改 F:错误样式 */
.xfe-ca-field-error,
.xfe-ca-field-error:focus {
    background-color: #FFF1F1 !important;
    border: 1px solid #c00 !important;
    box-shadow: 0 0 0 1px #c00;
}
.xfe-ca-form-errors {
    background: #FFF1F1;
    border: 1px solid #c00;
    padding: 10px 15px;
    margin: 10px 0;
    color: #c00;
    list-style: none;
}
.xfe-ca-form-errors li {
    list-style: none;
    margin: 2px 0;
}
.xfe-ca-form-errors li.head {
    font-weight: bold;
    list-style: none;
    margin: 0;
}
</style>
```

`js/xfe_carrier/custom-attribute-form-switcher.js` — `showFormErrors()` 简化:

```js
// 旧:ul.style.cssText = 'background:#FFF1F1;border:1px solid #c00;...';
//    head.style.cssText = 'font-weight:bold;list-style:none;margin:0;';
//    li.style.cssText  = 'list-style:none;margin:2px 0;';
// 新(全部交给 CSS):
ul.className = 'xfe-ca-form-errors';
head.className = 'head';
// li 默认样式由 .xfe-ca-form-errors li 规则覆盖
```

#### 视觉对比

| 场景 | 旧 | 新 |
|---|---|---|
| 出错字段 | 无样式(class 标记而已) | **红色边框 + 浅红背景** |
| form 顶部错误块 | inline 样式,代码冗余 | CSS class,样式集中 |
| focus 时边框 | 默认蓝色 | 红色保持 |
| error 块标题 | inline 加粗 | `.head` class |

#### 不触碰的文件

- `js/xfe_carrier/custom-attribute-form-switcher.js` — 只改 `showFormErrors()` 内的 3 行 inline style(替换为 className)
- 12 个公开方法签名不变
- 既有 JS 测试不动(JS 测试只验证 `xfe-ca-form-errors` id 存在 + 类被加,不看 inline 样式)

#### 兼容性 / 降级

1. CSS 加载失败 → 错误块无样式但 class 标记仍在 → 用户至少能读到错误文字
2. 老浏览器不支持 `:focus` 选择器 → 只在 focus 时无视觉加强(可接受)
3. 已有 JS 测试不变 — 改前改后输出都是 `<ul id="xfe-ca-form-errors" class="...">`,测试不受影响

---
## 2.14 options_csv 可视化行编辑器(小改 G,2026-09-17,ADR 0022)

为 `options_csv` 增加 key|label 动态行编辑器,新增 + 编辑场景都生效。

### 架构

- **Form.php 不动**:仍输出 `<input id="options_csv" name="options_csv">`,提交语义不变
- **JS 接管视觉**:`mountOptionsEditor()` 在 optionsEl.parentNode 内挂载 `<div class="xfe-ca-opt-editor">` 容器
  - type=select/multiselect 时**隐藏原生 input + 显示编辑器**
  - type=text/number/boolean 时反过来(显示原生 input,隐藏编辑器)
- **CSV ↔ 行编辑器双向同步**:
  - 初始化:`parseOptionsCsvToPairs(options_csv)` → 行
  - 行变化:`serializePairsToCsv(rows)` → 写回隐藏 input.value
  - 空 key 行过滤(允许临时空行;用户正在添加中)
- **CSV 格式**:`key|label,key|label,...`(label 缺省或等于 key 时压缩为 `key`)
- **复用现有迁移策略**:编辑器触发的 change 标记 `_xfeCaEditorSource = true`,`onOptionsChange` 检测后跳过 `promptMigrationStrategy`(用户主动改 cell,无歧义)

### 新增公开方法(13 → 18)

| 方法 | 签名 | 行为 |
|---|---|---|
| `parseOptionToken(s)` | string → `{key, label}` | 拆 `key\|label` 或 `key`,trim 两侧空白 |
| `serializeOptionPair(pair)` | `{key, label}` → string | 反向,label 缺省/等于 key 时压缩为 key |
| `parseOptionsCsvToPairs(s)` | string → `[{key, label}]` | 全量解析,过滤空 token |
| `serializePairsToCsv(pairs)` | `[{key, label}]` → string | 全量序列化,过滤空 key |
| `mountOptionsEditor(opts)` | `{optionsEl, fieldTypeEl}` → `{refresh(type), destroy()}` | 挂载 + 显隐控制 |

### UI 形态

每行:`[ key input ] [ label input ] [ 删除 ]`(默认 1 空行引导输入)
底部:`[ + 添加候选项 ]`

### 测试覆盖(35 个新增断言)

- parseOptionToken: 7 个(空/null/无 pipe / 含 pipe / 内有 pipe / trim / 空 key)
- serializeOptionPair: 5 个(null / label 空 / label==key 压缩 / key|label / 空 key)
- parseOptionsCsvToPairs: 5 个(空 / null / 单 key / 多 key / 边界空白)
- serializePairsToCsv: 5 个(空数组 / 全压缩 / 混合 / 空 key 过滤 / round-trip)
- mountOptionsEditor DOM 集成: 10 个(挂载 / refresh select / refresh text / 空 CSV 默认空行 / add / del / input 同步 / 空 key 过滤 / 防重复 / destroy)
- bind() 集成: 3 个(挂载 + 显隐 / 类型切换 / 编辑器触发 change 跳过 promptMigrationStrategy)

### 关联文件

- `js/xfe_carrier/custom-attribute-form-switcher.js`(27015 → ~35 KB)
- `app/design/adminhtml/default/default/template/xfe_carrier/custom_attribute/edit/form/type_switcher.phtml`(3870 → 4527 字节,+10 行 CSS)
- `tests/js/run-tests.js`(57442 → ~70 KB,+5 个 describe)
- `docs/architecture/decisions/0022-options-csv-row-editor.md`(ADR 草案)

---


## 2.15 options_csv 行编辑器 — key 重复实时检测(小改 H,2026-09-17)

为小改 G 的行编辑器加客户端 key 重复检测,提交前提示用户修正。

### 架构

- **新增私有函数 `_refreshDuplicateHints()`**:在 `mountOptionsEditor` 内部,遍历所有行 key,trim 空白,忽略空 key,找到重复 key 的所有行加 `.xfe-ca-opt-dup` class + 行下方插入 `<span class="xfe-ca-opt-dup-hint">key 重复</span>` 提示
- **触发时机**:
  - `buildRow()` 内 key/label input handler(input 事件)
  - `refresh()` 重建行后立即调用
- **CSS 增量**:
  - `.xfe-ca-opt-key.xfe-ca-opt-dup` — 红色边框 + 浅红背景(输入框级)
  - `.xfe-ca-opt-row-dup` — 行级红色背景 + 左侧红色 border(行级)
  - `.xfe-ca-opt-dup-hint` — 红底白字 chip 提示
- **空 key 不参与检测**:允许用户编辑器里有临时空行(正在添加中)
- **修正即清除**:用户改 key 后,`_refreshDuplicateHints()` 重新计算并自动清除过期标记

### 公开方法变化

无 — 18 个公开方法签名不变,新增私有函数不暴露。

### 测试覆盖(+10 断言)

- 初始无重复无标记
- 改 key 让两个 row 重复 → 两个都标 dup + 提示
- 修正后所有标记清除
- 空 key 不参与检测
- 三行同 key → 三个都标
- 初始加载 CSV 含重复 → refresh 立即标记
- label 改变不触发 dup 检测(避免误报)

### 关联文件

- `js/xfe_carrier/custom-attribute-form-switcher.js`(修改,+约 50 行私有函数 + 3 处 hook)
- `app/design/adminhtml/default/default/template/xfe_carrier/custom_attribute/edit/form/type_switcher.phtml`(修改,+约 25 行 CSS)
- `tests/js/run-tests.js`(修改,+1 组 describe 共 7 it)

---


## 2.16 options_csv 行编辑器 — HTML5 拖拽排序(小改 I,2026-09-17)

为小改 G 的行编辑器加拖拽排序,用户可以拖动候选行调换顺序。

### 架构

- **每行 draggable=true**:`row.draggable = true` + `row.style.cursor = 'move'`
- **dragstart**:标记 `rowsEl.__xfeCaDragRow = row`,加 `.xfe-ca-opt-dragging` class(透明效果)
- **dragend**:清理 dragging class + `__xfeCaDragRow`,清除所有 drag-over 标记
- **rowsEl 委托**:
  - `dragover`:阻止默认(允许 drop),`dropEffect = 'move'`,找目标行加 `.xfe-ca-opt-drag-over` class
  - `dragleave`(仅当离开 rowsEl):清理所有 drag-over 标记
  - `drop`:从 e.target 找目标行(降级兼容,不必依赖 dragover 状态),根据 clientY 与 rect.middle 比较决定插入位置:
    - 上半(`clientY < rect.top + height/2`)→ 插到 target 之前
    - 下半 → 插到 target 之后(若 target 是末尾则 appendChild)
    - drop 到自己 → noop
    - 然后调 `syncToHidden()` 同步 CSV + `_refreshDuplicateHints()` 重新检测 dup

### 公开方法签名变化

无 — 18 个公开方法不变,新增的拖拽逻辑是 mountOptionsEditor 内部实现。

### CSS 增量(4 条)

- `.xfe-ca-opt-row[draggable="true"]` — cursor: move
- `.xfe-ca-opt-row.xfe-ca-opt-dragging` — opacity: 0.4 + 浅蓝背景(被拖行)
- `.xfe-ca-opt-row.xfe-ca-opt-drag-over` — border-top: 2px dashed 蓝色虚线(目标位置指示)

### 测试覆盖(+7 断言,139 → 146)

- 每行默认 draggable=true + cursor: move
- dragstart: 标记 __xfeCaDragRow + dragging class
- dragend: 清理 dragging class + __xfeCaDragRow
- drop(上半): 正确插入到目标行之前 + 同步 CSV
- drop(下半): 正确插入到目标行之后 + 同步 CSV
- 拖拽后 CSV 变化,dup 检测应仍生效
- drop 到自己: noop(无无限递归)

### 关联文件

- `js/xfe_carrier/custom-attribute-form-switcher.js`(修改,+约 80 行拖拽逻辑)
- `app/design/adminhtml/default/default/template/xfe_carrier/custom_attribute/edit/form/type_switcher.phtml`(修改,+约 15 行 CSS)
- `tests/js/run-tests.js`(修改,+约 130 行 describe)

### 兼容性说明

- 仅支持 HTML5 拖拽 API(现代浏览器,Chrome / Firefox / Edge / Safari)
- 移动端浏览器(触屏)不支持 HTML5 拖拽,但 Magento 后台主要在 PC 浏览器使用
- 拖拽不影响 Service 层(CSV 写入格式不变,只是顺序变了)

---


## 2.17 options_csv 行编辑器 — 文案 i18n(小改 J,2026-09-17)

为小改 G 行编辑器的硬编码中文文案加 i18n 注入路径,让 XLIFF 抽取能命中。

### 架构

- **JS `mountOptionsEditor(opts)` 加可选 `opts.labels`**:`{ headTitle, addBtn, delBtn, dupHint }`
- **三优先级**:
  1. `opts.labels`(调用方显式传入,测试用)
  2. `window.XfeCaEditorLabels`(phtml 注入,生产用)
  3. 英文 fallback(中文作为兼容兜底)
- **缺失的 key 回退 fallback**:`labels.headTitle` 设了但 `labels.addBtn` 没设 → 用 fallback
- **应用文案**:
  - 容器头部标题
  - "+ 添加候选项"按钮文字
  - 每行的"删除"按钮文字
  - 重复 key 提示文字

### phtml 注入

```php
window.XfeCaEditorLabels = <?php echo json_encode(array(
    'headTitle' => Mage::helper('xfe_carrier')->__('候选项(key / 显示名)'),
    'addBtn'    => Mage::helper('xfe_carrier')->__('+ 添加候选项'),
    'delBtn'    => Mage::helper('xfe_carrier')->__('删除'),
    'dupHint'   => Mage::helper('xfe_carrier')->__('key 重复'),
)); ?>;
```

4 处文案都通过 `Mage::helper('xfe_carrier')->__()` 包裹,XLIFF 抽取工具可识别。

### 测试覆盖(+6 断言,146 → 152)

- 未传 labels → fallback 英文
- 通过 opts.labels 覆盖文案
- 从 window 全局注入(模拟 phtml 路径)
- opts.labels 优先级 > window 全局
- 部分 labels 缺失 → 缺失的 key 回退 fallback
- dup 提示用 i18n 文案

### 关联文件

- `js/xfe_carrier/custom-attribute-form-switcher.js`(修改,新增 labels 参数 + 4 处文案读取)
- `app/design/adminhtml/default/default/template/xfe_carrier/custom_attribute/edit/form/type_switcher.phtml`(修改,新增 XfeCaEditorLabels 注入)
- `tests/js/run-tests.js`(修改,+1 组 describe 共 6 it)

### 向后兼容

- 现有调用未传 labels → 走 fallback,行为与之前完全一致
- 仅在多语言部署下生效(中文 fallback 仍是中文,英文 fallback 是英文)
- 不影响 Service 层 / Form.php

---

## 3. 单测约定

文件:`tests/js/custom-attribute-form-switcher.html`

### 3.1 测试组织

```js
describe('XfeCaTypeSwitcher', function () {
    describe('parseOptionsCsv()', function () {
        it('空字符串返回 []', function () { ... });
        it('单个值', function () { ... });
        it('多个值带空白', function () { ... });
        it('空项被过滤', function () { ... });
    });

    describe('buildTextInput()', function () { ... });
    describe('buildSelect()', function () { ... });
    describe('buildBooleanRadios()', function () { ... });
    describe('readCurrentValue()', function () { ... });
    describe('restoreMultiselect()', function () { ... });
    describe('setRowVisible()', function () { ... });
});
```

### 3.2 断言

```js
assert.equal(actual, expected, 'msg');
assert.deepEqual(actual, expected, 'msg');
assert.ok(condition, 'msg');
```

`assert.equal` 对 DOM 节点比较 `outerHTML`;对字符串直接 `===`。

### 3.3 跑测试

用户在浏览器打开 `tests/js/custom-attribute-form-switcher.html`:

```
✓ 18 passed, 0 failed in 12 ms
```

每个 `describe` 显示成组,失败的用例展开 `expected vs actual`。

### 3.4 CI(后续可选)

如需命令行跑测试,可后续加 node + jsdom 方案 — 但本次不强求。

---

## 4. 不做

- ❌ 不引入 QUnit / Jasmine / Mocha — 0 新依赖
- ❌ 不改 prototype.js — Magento 1.9 自带
- ❌ 不改 `Edit/Form.php` 字段定义
- ❌ 不引入 ES6 module / import — prototype.js 不支持
- ❌ 不写 async 测试 — 所有方法同步
- ❌ 抽 renderValueCell 不破坏 bind() 行为(bind 内部 switch = renderValueCell + setRowVisible + 引用替换)

---

## 5. 涉及文件

| 文件 | 类型 | 说明 |
|---|---|---|
| `js/xfe_carrier/custom-attribute-form-switcher.js` | 新增 | IIFE + 12 个方法(ADR 0009 = 8,ADR 0012 = 9,ADR 0013 = 10,小改 A = 11,小改 B = 12) |
| `app/design/adminhtml/default/default/layout/xfecarrier.xml` | 修改 | `<reference name="head">` 注册 JS |
| `app/design/adminhtml/default/default/template/xfe_carrier/custom_attribute/edit/form/type_switcher.phtml` | 修改 | 简化(从 ~190 行 → ~30 行),仅调 `bind()` |
| `tests/js/custom-attribute-form-switcher.html` | 新增 | mini runner + 18+ 用例 |
| `docs/architecture/decisions/0009-js-extract-and-tests.md` | 新增 | ADR |

---

## 7. 后续接入路径 — strict_editor.phtml ✅ 已完成(ADR 0013,2026-09-15)

详见 §2.7 与 [`decisions/0013-strict-editor-js-rendering.md`](./decisions/0013-strict-editor-js-rendering.md)。

**落地范围**:

- ✅ `buildChips(container, opts)` 抽进 XfeCaTypeSwitcher(60 行 IIFE → 命名函数,行为等价)
- ✅ `renderValueCell` multiselect 分支接受 array memo(向后兼容)
- ✅ strict_editor.phtml value 列 → `<td data-value-cell ...>` + 底部 init script
- ✅ 4 个 PHP type 分支 + 60 行 chips IIFE 删除
- ✅ 新增 6 个测试用例(48 → 54 passed)
- ✅ PHP 端 POST 兼容性零破坏(`CustomAttributeApplierStrictTest` 未动)

**未做(后续可选)**:

- noscript fallback(JS 失败 → value 列空白;init script 同步执行,实测窗口期 < 50ms,不可见)
- ADR 0014:真实浏览器端到端(Puppeteer / Playwright)

---

## 8. 修订记录

| 日期 | 变更 | 作者 |
|---|---|---|
| 2026-09-14 | 初版:JS 抽取 + 自写 mini runner,约定测试组织 | AI 助手 |
| 2026-09-15 | ADR 0012 落地:新增 `renderValueCell` 公开 API,模块形状 8 → 9 个方法;新增 §2.6 独立使用示例 + §7 strict_editor 后续接入路径 | AI 助手 |
| 2026-09-15 | ADR 0013 落地:新增 `buildChips` 公开方法(60 行 IIFE 抽离),`renderValueCell` 接受 array memo(multiselect),模块形状 9 → 10;新增 §2.7 strict_editor 接入示例 + §7 标记已完成 | AI 助手 |
| 2026-09-17 | 小改 A 落地:JS 联动时同步切换 `default_value` / `options_csv` 的 note 文案,新增 `updateNote(fieldId, noteText)` 公开方法(模块形状 10 → 11);type_switcher.phtml 注入 `window.XfeCaFormNotes` JSON;新增 §2.8 动态 note 章节 | AI 助手 |
| 2026-09-17 | 小改 B 落地:JS submit 前预校验,新增 `validateForm(opts)` 公开方法(模块形状 11 → 12);`bind()` hook form submit;新增 §2.9 客户端预校验章节(规则 + 错误展示 + 服务端真权威) | AI 助手 |
| 2026-09-17 | 小改 C 落地:服务端 updateDef() 在 options_csv 变更时检测 default_value 兼容性,新增 _detectOptionsMigration() / _applyMigrationStrategy() 私有方法 + 3 个迁移策略常量(reject / auto_clean / set_null);默认 reject 保持向后兼容;新增 §2.10 服务端兼容性章节 | AI 助手 |
| 2026-09-17 | 小改 D 落地:JS 端在 options_csv 实际变化且 default_value 不兼容时弹 confirm(2 选项:确定 = auto_clean,取消 = 还原 options_csv);新增 `promptMigrationStrategy(opts)` 公开方法(模块形状 12 → 13);type_switcher.phtml 注入 `window.XfeCaMigrationLabels.confirm`;新增 §2.11 JS 弹窗章节 | AI 助手 |
| 2026-09-17 | 小改 E 落地:`tests/php/run-tests.php` 扩展 Carrier 套件(3 → 8 个测试);runTestFile 兼容 `Total: N assertions` 和 `ALL PASS` 两种输出格式;CI 自动覆盖新增 Carrier 测试;累计 **367 assertions / 0 failures**;新增 §2.12 CI 扩展章节 | AI 助手 |
| 2026-09-17 | 小改 F 落地:`type_switcher.phtml` 注入 `<style>` 块,定义 `.xfe-ca-field-error`(红色边框 + 浅红背景)+ `.xfe-ca-form-errors`(错误块)规则;JS `showFormErrors()` 移除 3 处 inline `style.cssText`,改用 CSS class;新增 §2.13 CSS 视觉化章节 | AI 助手 |
| 2026-09-17 | 小改 G 落地:options_csv 可视化行编辑器(ADR 0022),XfeCaTypeSwitcher 新增 5 个公开方法(parseOptionToken / serializeOptionPair / parseOptionsCsvToPairs / serializePairsToCsv / mountOptionsEditor),模块形状 13 → 18;phtml 注入 ~10 行 CSS(.xfe-ca-opt-editor*);新增 §2.14 行编辑器章节;JS 94 → 129 passed,PHP 407 维持 | AI 助手 |
| 2026-09-17 | 小改 H 落地:行编辑器加 key 重复实时检测,mountOptionsEditor 内部新增私有函数 _refreshDuplicateHints(),trim 后比较所有行 key,空 key 不参与,重复行加 .xfe-ca-opt-dup + 行下方红底白字 chip 提示;phtml 加 4 条 CSS;新增 §2.15 行编辑器 dup 检测章节;**JS 129 → 139 passed**,**PHP 407 维持** | AI 助手 |
| 2026-09-17 | 小改 I 落地:行编辑器加 HTML5 拖拽排序(纯客户端,无依赖),每行 draggable=true,cursor: move;owsEl 委托 dragover / dragleave / drop 三事件,根据 clientY 与 target rect.middle 决定插入位置(上 1/2 之前 / 下 1/2 之后);drop 后调 syncToHidden() + _refreshDuplicateHints();phtml 加 4 条 CSS(dragging 半透明 + drag-over 蓝色虚线);新增 §2.16 拖拽排序章节;**JS 139 → 146 passed**,**PHP 407 维持** | AI 助手 |
| 2026-09-17 | 小改 J 落地:行编辑器文案 i18n,mountOptionsEditor(opts) 加可选 opts.labels(headTitle/addBtn/delBtn/dupHint),三优先级:opts > window.XfeCaEditorLabels > 英文 fallback;phtml 通过 Mage::helper('xfe_carrier')->__() 注入 4 处文案供 XLIFF 抽取;新增 §2.17 文案 i18n 章节;**JS 146 → 152 passed**,**PHP 407 维持** | AI 助手 |

## 2.18 options_csv 行编辑器 — boolean label 自定义(小改 K,2026-09-18)

为布尔型属性增加 label 自定义能力(value 固定 0/1,label 可改)。复用现有 options_csv 行编辑器机制,boolean 时 value 列只读(灰色背景),label 列可编辑;空 options 时自动填 2 行 `{0: 否, 1: 是}` 默认值。

### 架构

- **JS `mountOptionsEditor.refresh(type)` 加 boolean 分支**:
  - `type === 'boolean'` → 显示编辑器容器 + 隐藏 options_csv 原生 input
  - 空时自动填 2 行:`buildRow({key:'0',label:'否'},{readonlyValue:true})` + `{key:'1',label:'是'}`
  - 非空时所有行都用 `readonlyValue: true` 参数(value 只读)
- **JS `buildRow(pair, opts)` 加 `opts.readonlyValue`**:
  - `true` → value input 加 `readonly` 属性 + `.xfe-ca-opt-key-readonly` class(灰色背景)
  - 拖拽 handle 在 boolean 时仍渲染(虽然顺序无意义,保持 UI 一致)
- **JS `buildBooleanRadios(opts)` 加 `opts.options` 结构化 label 解析**:
  - 检测到 `Array.isArray(opts.options) && length === 2` → 从 `[{key, label}, ...]` 取 label
  - 优先级:`opts.options` > `opts.yesLabel/noLabel` > 英文 fallback("Yes"/"No")
  - 支持 `key='0'` → noLabel / `key='1'` → yesLabel 的映射

### UX 设计

**新建 / 编辑域,类型=布尔** 时,候选项行编辑器显示:

```
┌─────────────────────────────────────────────┐
│ 候选项(value / label)                      │
├─────────────────────────────────────────────┤
│ [0 只读] [否 ___________] [删除]           │
│ [1 只读] [是 ___________] [删除]           │
│ [+ 添加候选项]                             │
└─────────────────────────────────────────────┘
```

修改 label → entity 编辑页 boolean radio 文案同步(经 PHP 端 def.getBooleanLabels() 渲染):

```
○ 关闭    ● 开启
```

### 与 PHP 端对接

- **存储**:options_csv 存 `0|关闭,1|开启`(复用既有 `key|label` 形态,无新格式)
- **Domain 内部表示**:`CustomAttribute::$options` 升级为结构化 `[{key:string, label:string}]`
- **CustomField 消费**:继续接收 `string[]`(仅 keys);label 信息由 template 从 `def.getOptions()` 拿结构化后传给 JS
- **template**:`strict_editor.phtml` boolean 分支把 `def.getOptions()` 序列化为 JSON,JS 端 `buildBooleanRadios` 解析为 label
- **回退**:options_csv 为空 / 缺失时,Domain 默认 `{0: 否, 1: 是}`,getBooleanLabels 返回 `{0: 否, 1: 是}`

### JS 公开方法

无变化 — `mountOptionsEditor` 签名不变;boolean 只读模式是内部实现细节(`_label` 参数已支持 i18n)。

### 关联改动

- **PHP**:`Domain/CustomAttribute.php` options 内部结构升级 + 5 个新方法 + boolean 校验(2 行 + key∈{0,1})
- **PHP**:`Domain/CustomAttributeCollection.php::fromArray` 用 `parseOptionsCsvToPairs` 替代 `explode(',')`
- **PHP**:`Model/Service/CustomAttributeApplierAbstract::_buildFromPost` 用 `getOptionKeys()` 喂 CustomField
- **PHP**:`Model/Service/CustomAttribute/Exporter` + `Block/Adminhtml/CustomAttribute/Grid` 用 `serializeOptionsPairsToCsv` 序列化
- **测试**:`tests/js/run-tests.js` 新增 `mountOptionsEditor() — boolean label 自定义(小改 K)` describe 组(8 assertions)
- **测试**:`tests/php/.../CustomAttributeServiceBooleanTest.php` 新建(32 assertions)

| 2026-09-18 | 小改 K 落地:boolean label 自定义(ADR 0023);mountOptionsEditor.refresh('boolean') 显示编辑器 + 默认 2 行 {0:否,1:是} + value 列只读;uildRow(pair, opts) 加 opts.readonlyValue 参数;uildBooleanRadios(opts) 加 opts.options 结构化 label 解析(优先 yesLabel/noLabel);新增 §2.18 boolean label 章节;**JS 152 → 160 passed**,**PHP 407 → 439 passed** | AI 助手 |

