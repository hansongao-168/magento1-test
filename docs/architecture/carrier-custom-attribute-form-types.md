# 承运商 — 自定义属性表单的 `field_type` 联动

> 主题:`XFE_Carrier` 模块「系统 → 承运商管理 → 自定义属性 → 新增/编辑」页面,
> 切换 `field_type`(text / number / select / multiselect / boolean)时,
> 表单的 `options_csv` 行与 `default_value` 字段应**按 type 差异化联动**,而非 5 种 type 都显示一样的参数。
>
> 状态:**提案**(2026-09-14)
>
> 适用范围:`XFE_Carrier` 模块的 `xfe_carrier/adminhtml_customAttribute_edit` 表单页
> 路由:`adminhtml/carrier_customAttribute/new` / `edit`
> 关联文档:
> - [`carrier-global-custom-field-defs.md`](./carrier-global-custom-field-defs.md) — 自定义属性总设计(§4.4.2 表 4.4 规定的「按 type 切换」设计意图)
> - [`decisions/0005-custom-field-multiselect.md`](./decisions/0005-custom-field-multiselect.md) — multiselect 用 `|` 分隔
> - [`decisions/0008-custom-attribute-form-ux.md`](./decisions/0008-custom-attribute-form-ux.md) — 本次选型 ADR

---

## 1. 背景与问题

### 1.1 现象

在 `admin/carrier_customAttribute/new`(或 `edit?id=...`)页面,「类型」下拉选择
`text` / `number` / `select` / `multiselect` / `boolean` 中的任意一个,
下面的「**候选项**」与「**默认值**」两个字段都**始终显示**,
不论类型是 `text` 还是 `boolean`。

### 1.2 设计意图(已存在,未实现)

架构文档 `carrier-global-custom-field-defs.md` §4.4.2 表 4.4 已经规定:

| 字段 | 类型(设计) |
|---|---|
| `field_type` | select |
| `options_csv` | text(仅 select / multiselect 需填) |
| `default_value` | text(**按 type 切换:multiselect 用 `\|` 分隔**) |

设计意图明确,但 Form block `XFE_Carrier_Block_Adminhtml_CustomAttribute_Edit_Form`
(文件 `app/code/community/XFE/Carrier/Block/Adminhtml/CustomAttribute/Edit/Form.php`)
仅静态创建了 3 个字段,**没有任何联动**。

### 1.3 后果

1. **冗余字段**:为 `boolean` / `text` / `number` 类型填「候选项」是无效输入。
2. **保存即报错**:对 `select` / `multiselect` 留空 `options_csv`,Service 层
   `XFE_Carrier_Model_Service_CustomAttributeService::createDef()` 会校验失败
   (参考 `XFE_Carrier_Domain_CustomAttribute::__construct()` § 137-144 行)。
3. **默认值类型错配**:`boolean` 的「默认值」本应是 Yes/No 单选,现在是 text 框;
   `multiselect` 的「默认值」应支持多个值用 `|` 分隔,现在只看到普通文本框提示。

---

## 2. 设计目标

1. 切换 `field_type` 时,「**候选项**」行**仅**对 `select` / `multiselect` 显示。
2. 「**默认值**」字段按 type 切换:
   - `text` / `number` → `<input type="text">`
   - `select` → `<select>`(选项从 `options_csv` 实时解析)
   - `multiselect` → `<select multiple>`(选项从 `options_csv` 实时解析)
   - `boolean` → Yes / No radio
3. **后端契约不变**:`default_value` 字段 name 保持 `default_value`,后端只读字符串。
   - `boolean` 提交 `0` / `1`
   - `select` 提交单个字符串(option value)
   - `multiselect` 提交 `|` 分隔字符串(沿用 ADR 0005)
   - `text` / `number` 提交原样字符串
4. 不修改 Magento 核心文件(AGENTS.md §5.4 红线)。
5. 不修改 `XFE_Carrier` 已有 Service / Domain / Resource。

---

## 3. 架构与单向依赖

```
L4  Controller  Adminhtml/Carrier/CustomAttributeController(已有,不动)
        ↓
L4  Block       Adminhtml/CustomAttribute/Edit/Form(已有,不动)
L4  Block       Adminhtml/CustomAttribute/Edit/Form/TypeSwitcher  ← 新增(仅输出 <script>)
        ↓ (DOM)
L3  Service     CustomAttributeService(已有,不动,只读 default_value 字符串)
        ↓
L2  Model/Resource   CustomAttribute(已有,不动)
        ↓
L1  Domain      CustomAttribute / CustomField(已有,不动,ALLOWED_TYPES + coerceValue())
```

- 新增的 Block **只**渲染一个 `<script>`,**不**接触任何表单字段定义;
- 已有 Form block **不**被改写 — 表单字段定义(10 个字段)保持不变;
- 联动完全在前端 prototype.js 完成,后端零改动。

---

## 4. 联动行为(决策表)

`switchCustomAttributeType()` JS 函数(在 `type_switcher.phtml`)按以下规则切换:

| `field_type` | `options_csv` 行 | `default_value` 渲染 | 选项来源 |
|---|---|---|---|
| `text` | 隐藏 | `<input type="text">` | — |
| `number` | 隐藏 | `<input type="text" class="validate-number">` | — |
| `select` | **显示** | `<select>` | 从 `options_csv` 按 `,` split |
| `multiselect` | **显示** | `<select multiple size="5">` | 从 `options_csv` 按 `,` split |
| `boolean` | 隐藏 | 2 个 `<input type="radio" name="default_value" value="0|1">` | 固定 Yes / No |

事件绑定:
- `Event.observe('field_type', 'change', switchCustomAttributeType)`
- `Event.observe('options_csv', 'change', function(){ if(field_type in [select, multiselect]) rebuildOptions() })`
- 页面加载完成后立即调用一次 `switchCustomAttributeType()` 处理编辑回显。

---

## 5. 后端契约(零改动)

`default_value` 字段在 controller `saveAction()` 已统一读为字符串。

新建/更新路径(`XFE_Carrier_Model_Service_CustomAttributeService::createDef/updateDef`)
通过 `XFE_Carrier_Domain_CustomAttribute::__construct()` 与 `coerceValue()` 自动:

| `field_type` | `default_value` 入参 | 入参形态 |
|---|---|---|
| `text` | 任意字符串 | scalar |
| `number` | 数字字符串 | scalar |
| `select` | 选项 value | scalar |
| `multiselect` | `a\|b\|c` | scalar(由 ADR 0005 规定) |
| `boolean` | `0` / `1` | scalar |

**因此前端只需保证提交 `default_value` 字符串符合上表**,Service 端不需要任何改动。

---

## 6. 不做

- ❌ **不**改 `Edit/Form.php` 的字段定义 — 已完整,无需重写
- ❌ **不**改 Service / Domain / Resource
- ❌ **不**做「必填校验」「长度校验」等后端校验 — 已有 Domain `coerceValue()` 覆盖
- ❌ **不**做「批量导入/导出」的同款联动 — 那是独立的 import/export 功能(参考 `custom_attribute/import/form.phtml`)
- ❌ **不**加新依赖、不写 jQuery — 复用 Magento 1 自带的 prototype.js

---

## 7. 涉及文件(变更清单)

| 文件 | 类型 | 说明 |
|---|---|---|
| `app/code/community/XFE/Carrier/Block/Adminhtml/CustomAttribute/Edit/Form/TypeSwitcher.php` | 新增 | L4 Block,继承 `Mage_Core_Block_Template`,setTemplate 到下表模板 |
| `app/design/adminhtml/default/default/template/xfe_carrier/custom_attribute/edit/form/type_switcher.phtml` | 新增 | 输出 `<script>switchCustomAttributeType()</script>` |
| `app/design/adminhtml/default/default/layout/xfecarrier.xml` | 修改 | 在 `adminhtml_carrier_customattribute_new` / `_edit` 两个 handle 内 `<reference name="content">` 挂载 child block |
| `docs/architecture/decisions/0008-custom-attribute-form-ux.md` | 新增 | 本次决策 ADR |

**未变更文件**(明确列出,避免误改):
- `XFE_Carrier_Block_Adminhtml_CustomAttribute_Edit_Form`(字段定义)
- `XFE_Carrier_Adminhtml_Carrier_CustomAttributeController`(saveAction)
- `XFE_Carrier_Model_Service_CustomAttributeService`
- `XFE_Carrier_Domain_CustomAttribute` / `XFE_Carrier_Domain_CustomField`
- `XFE_Carrier_Model_CustomAttribute` / `Resource/CustomAttribute`

---

## 8. 验证

| 验证项 | 方法 |
|---|---|
| PHP 语法 | `php -l` 所有新增/修改 PHP |
| 5 种 type 切换 | 后台浏览器手动切 `field_type`,检查 `options_csv` 行显隐与 `default_value` 类型 |
| 编辑回显 | 已存的 `select` 属性打开编辑页,`default_value` 应自动渲染为 `<select>` 且当前值选中 |
| 保存往返 | 5 种 type 各保存一次,检查 `xfe_carrier_custom_attribute.default_value` 列内容符合 §5 表 |

---

## 9. 修订记录

| 日期 | 变更 | 作者 |
|---|---|---|
| 2026-09-14 | 初版:定义 5 种 type 联动行为,新增 TypeSwitcher Block + 模板,layout 挂载 | AI 助手 |
