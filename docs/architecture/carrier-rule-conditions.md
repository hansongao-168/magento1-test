# 承运商规则条件描述与订单创建时间

## 1. 目的

承运商规则管理页的规则列表必须同时展示规则描述和条件描述，并在条件选择器中增加“订单创建时间”条件。

本次改动属于 `XFE_Carrier` 模块内部行为扩展，不新增数据库字段，不改变条件树的持久化结构，也不改变其他模块对 `XFE_Carrier` 的调用方式。

## 2. 设计范围

### 2.1 规则列表

以下三个后台规则列表统一增加“条件描述”列：

- 承运商编辑页的规则列表：`XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Rules_Grid`
- 承运商账号编辑页的规则列表：`XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Account_Rules_Grid`
- 承运商 FTP 账号编辑页的规则列表：`XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_FtpAccount_Rules_Grid`

列使用同一行格式化入口，不在各个 Grid 内复制条件解析逻辑。

### 2.2 条件描述格式

条件描述由规则模型从 `conditions_data` 读取并生成：

- 顶层条件组之间使用 `全部条件组（AND）` / `任意条件组（OR）` 的关系表达；当前规则引擎实际语义为各顶层组全部满足。
- 单个条件格式为：`属性中文名 操作符中文名 值`，例如 `目的地国家 等于 US`。
- `is_null` 和 `is_not_null` 不显示值。
- 条件值为空但不是空值操作符时显示为空字符串。
- 空条件树显示“匹配所有”。
- 未知属性使用属性代码原文，避免后台静默丢失规则信息。

`getConditionsDescription()` 是模型层的公开读取接口，仅负责展示格式化，不承担条件求值。

## 3. 订单创建时间条件

新增条件属性：`order_created_at`，显示名称为“订单创建时间”。

### 3.1 编辑器契约

- 属性元数据类型为 `datetime`。
- 条件编辑器提供文本值输入，格式为 `YYYY-MM-DD HH:mm:ss`。
- 可用操作符：等于、不等于、大于、大于等于、小于、小于等于、范围、为空、不为空。
- `datetime` 不套用纯数字 `between` 的旧 `x~y` 规则；若当前共享编辑器只支持字符串和数字输入框，则至少支持 ISO 时间字符串比较，并将该限制记录在模块架构文档中。

### 3.2 订单上下文契约

`XFE_Carrier_Model_Service_Rule_OrderContextBuilder` 增加：

```php
'order_created_at' => $this->_order->getCreatedAt()
```

值使用订单对象的原始 `created_at` 字符串，求值器按时间戳进行可比较运算。报价上下文不提供伪造的订单创建时间；缺少该值时条件不匹配。

### 3.3 评估契约

- `order_created_at` 可使用时间比较操作符。
- 两个时间值必须都能解析为有效时间；无法解析的时间值不匹配比较操作符。
- `is_null` 与 `is_not_null` 继续依据上下文是否存在 `order_created_at` 判断。
- 规则条件表继续使用现有 `attribute`、`operator`、`value` 字段，不需要数据库升级脚本。

## 4. 依赖与边界

- 规则模型负责读取和格式化已持久化条件树。
- 订单上下文构建器负责把 `Mage_Sales_Model_Order` 的业务字段映射到 `MatchContext`。
- 求值器负责时间语义比较，不读取数据库。
- Grid 仅请求条件描述，不直接读取其他模块的私有数据。

### 4.1 规则 Grid 的条件树加载

Magento 的 Db collection 加载后**不会**对每行调用 `afterLoad()`，因此资源模型的 `_afterLoad()`（负责生成 `conditions_data`）在集合行上不会执行。若不处理，规则列表的“条件描述”列会对所有规则显示“匹配所有”。

- 规则集合新增公开方法 `XFE_Carrier_Model_Resource_Rule_Collection::loadConditions()`：遍历已加载行并调用 `$rule->afterLoad()` 补全 `conditions_data`。
- 三个规则 Grid（承运商/账号/FTP账号）在 `_afterLoadCollection()` 中调用该方法（集合为空或非规则集合时跳过）。
- 该方法**刻意做成 opt-in**，不挂进集合 `_afterLoad()`：规则求值器 `Rule\Resolver` 在每次解析时都加载该集合并自行读取条件树，若集合自动加载条件会导致每次解析重复查询。

### 4.2 保存链路中的 `groups_data_hidden`

Magento 的原生 `form.submit()`（`varienForm.submit()` 内部调用）**不会**触发 submit 事件，因此条件构建器 JS 里的 `editForm.observe('submit', updateHiddenField)` 不会在保存按钮被点击时运行。`groups_data_hidden` 必须在每次条件变更、**以及 init 阶段**就被写入，提交时才能带上合法 JSON。

`skin/xfe_shippingrule/js/condition-builder.js` 的 `initConditionBuilder` 在默认分支后追加了一段安全网：`addConditionGroup()` 跑完后若 `groups_data_hidden` 仍无值（容器 `condition-groups-container` 尚未被 `varienTabs` 移入 `edit_form`），主动注册一个空 group 并 `updateHiddenField()`。这样**新建规则的 `groups_data` 永远是非空且合法的 JSON**，`saveRuleAction` / `_afterSave` 一定能把条件树落库。

### 4.3 模型层兜底

`XFE_Carrier_Model_Carrier_Rule::getConditionsData()` 末尾：当 `conditions_data` 为空 + 规则有 id 时主动 `$this->afterLoad()` 重新拉条件树。任何调用 `getConditionsDescription()` 的代码路径——包括 Grid 集合行、未来的 Grid、单条加载、任何 service——都能拿到真实条件，无需依赖 Grid 的批量预热，也不依赖缓存。这一层让"有条件就显示真实描述"成为模型的固有行为。

### 4.4 共享条件编辑器只接收由 `XFE_Carrier_Helper_Data::getConditionAttributeOptions()` 和 `getAttributeTypeMap()` 下发的属性契约；若编辑器需要真正的日期选择控件，应单独设计并避免影响 `XFE_ShippingRule` 等其他消费者。

## 5. 验证要求

- 覆盖三处规则 Grid 的条件描述列配置，或至少覆盖承运商主列表并对复用列做静态检查。
- 覆盖条件描述的普通条件、嵌套条件组、空值操作符、空树和未知属性。
- 覆盖 `order_created_at` 在订单上下文中的映射。
- 覆盖时间等于、前后比较、范围和非法时间值。
- 执行新增/修改 PHP 文件的 `php -l`；如有可用 PHPUnit 入口，执行相关 Carrier 测试。


## 6. 列表入口-弹窗（2026-09-23 ADR 0032 widgetTools.openDialog 薄壳）

承运商编辑页（`adminhtml/carrier/edit`）的 3 个子列表 —— Logo 列表、账号列表、FTP 账号列表 —— 各加一个「+ 添加规则」按钮，点击后弹出 **widgetTools.openDialog 薄壳包装的弹窗**（**2026-09-23 替代 ADR 0031 自建 AJAX 注入**），加载 `adminhtml/carrier/editRule?ajax=1` 的纯 HTML，保存成功后弹窗关闭并刷新当前页。

### 6.0 演进历史

- **ADR 0029（2026-09-21）**：iframe 弹窗 + chrome 隐藏 CSS 注入。**已被 ADR 0031 标记 superseded**。
- **ADR 0031（2026-09-23）**：自建 AJAX 注入式弹窗（DOM 注入 + script 执行 + form submit hook）。**2026-09-23 被 ADR 0032 标记 superseded**（重复造轮子，应直接用 widgetTools.openDialog）。
- **ADR 0032（2026-09-23）**：薄壳包装 Magento 标准 `widgetTools.openDialog(url)`，弹窗 chrome / 内容注入 / evalScripts 全交给 widget 基础设施。

### 6.1 入口参数

| 父列表 | 弹窗 URL | module_code |
|--------|---------|-------------|
| Logo 列表 | `*/carrier/editRule?carrier_id=…&module_code=logo` | logo |
| 账号列表 | `*/carrier/editRule?carrier_id=…&module_code=account` | account |
| FTP 列表 | `*/carrier/editRule?carrier_id=…&module_code=ftp` | ftp |

按钮放在三个 Grid 块的 `_toHtml()` 工具条，紧贴现有的「+ 添加 Logo / + 添加账号 / + 添加 FTP 账号」按钮右侧。

### 6.2 弹窗 JS 契约（ADR 0032）

弹窗由新资源 `skin/adminhtml/default/default/xfe_carrier/js/rule-modal.js` 提供，命名空间 `XFE_CarrierRuleModal`：

- `XFE_CarrierRuleModal.open(url, title)`：
  1. 拼上 `?ajax=1` 参数（让后端走 ajax 模板）。
  2. 调用 `widgetTools.openDialog(ajaxUrl)` 打开 Dialog 弹窗（id = `widget_window`，content element id = `modal_dialog_message`）。
  3. `widgetTools.openDialog` 内部 `new Ajax.Updater('modal_dialog_message', url, {evalScripts: true})` 拉取 HTML 并自动执行 `<script`（条件构建器等直接生效）。
  4. 立即调用 `widgetTools.dialogWindow.setTitle(title)` 覆盖默认的 'Insert Widget...' 标题。
  5. 用 `MutationObserver` 监听 `modal_dialog_message`，发现 `<form id="edit_form">` 后 hook form submit + back/cancel 按钮。
- `XFE_CarrierRuleModal.close()`：调用 `widgetTools.closeDialog()`。
- `XFE_CarrierRuleModal.closeAndReload()`：`widgetTools.closeDialog() + window.location.reload()`。
- 关闭方式：ESC / × / overlay 点击 / Back 按钮 → 调用 `widgetTools.closeDialog()`，但**不**刷新父窗。

### 6.3 widgetTools.openDialog 基础设施依赖

| 资源 | 路径 | 来源 | 备注 |
|------|------|------|------|
| widgetTools 全局对象 | `js/mage/adminhtml/wysiwyg/widget.js` | layout 在 `adminhtml_carrier_edit` 节点显式注册 | 提供 `openDialog(url)` / `closeDialog()` / `dialogWindow` |
| condition-builder.js | `skin/adminhtml/default/default/xfe_shippingrule/js/condition-builder.js` | layout 在 `adminhtml_carrier_edit` 节点显式注册（`skin_js`） | 弹窗内 `[+ 添加条件组]` 按钮 onclick 调用全局 `addConditionGroup()` 函数，父页必须加载（ADR 0032 补丁 #4） |
| Dialog.info / Windows | `js/prototype/window.js` | main.xml 全局加载（无需重复） | widgetTools.openDialog 底层依赖 |
| Translator 全局对象 | `js/mage/translate.js` | main.xml 全局加载（无需重复） | widgetTools.openDialog 调用 `Translator.translate('Insert Widget...')` |
| Windows 基础 CSS | `js/prototype/windows/themes/default.css` | layout 在 `adminhtml_carrier_edit` 节点显式注册（`js_css` 类型） | 提供 prototype-windows 弹窗基础样式（`dialog` / `dialog_window` 等）。**缺这个 → 黑屏没窗口** |
| Windows magento 主题 CSS | `lib/prototype/windows/themes/magento.css` | layout 在 `adminhtml_carrier_edit` 节点显式注册 | 提供 `magento_message` / `popup-window` / `top_bg.gif` 等 widget 弹窗主题样式 |

注意：
- `mage/adminhtml/wysiwyg/widget.js` **不**会被 main.xml 全局加载，只在 `<editor>` handle 才加载。我们显式在 `xfecarrier.xml` 的 `adminhtml_carrier_edit` 节点加 `<action method="addJs"><script>mage/adminhtml/wysiwyg/widget.js</script></action>`。
- `prototype/window.js` 在 main.xml 第 60 行已全局加载。
- Windows 主题 CSS 包含两层：**`default.css` 是基础**（缺这个 → 黑屏没窗口），**`magento.css` 是 Magento 主题**（覆盖 default.css）。两者必须同时加载。

### 6.4 后端契约（继承自 ADR 0031，不变）

后端契约**完全沿用 ADR 0031**：

#### `editRuleAction()` ajax 分发（ADR 0032 修复）

- 原 `editRuleAction()` 始终走完整 admin layout（带 chrome），没有 `?ajax=1` 分发。
- **ADR 0032 修复**：在 `editRuleAction()` 顶部增加：
  ```php
  if ($this->getRequest()->getParam('ajax')) {
      return $this->editRuleAjaxAction();
  }
  ```
- 这样 `widgetTools.openDialog(url + '?ajax=1')` 内部 `Ajax.Updater` 拉取的就是 ajax 模板（无 chrome）。

#### `editRuleAjaxAction()` — 现有方法（ADR 0031 新增）

- 入口 URL：`*/carrier/editRuleAjax`（router 把 `editRule` → `editRuleAjax` 映射到 `editRuleAjaxAction`）。
- 但实际调用方式：前端 `widgetTools.openDialog(ajaxUrl)` 内部用 `Ajax.Updater('modal_dialog_message', url, {evalScripts: true})` 拉取 `*/carrier/editRule?ajax=1`，**由 `editRuleAction()` 顶部新增的 ajax 分发转发到本方法**（ADR 0032 修复）。
- 实现：先调用 `_loadRuleForEdit(true)` 加载 + 验证 rule/carrier（复用现有逻辑），失败时输出 JSON `{success:false, message}`。
- 成功：注册 `xfe_carrier_rule_edit_ajax` registry + `xfe_carrier_rule_data` registry → `loadLayout(['adminhtml_carrier_editrule'])` 实例化 block → 直接 `getBlock('carrier_rule_edit')->toHtml()` 输出（跳过 head/header/footer 等 chrome）。

#### `Edit::getTemplate()` — 现有方法（ADR 0031 新增）

- 检查 `Mage::registry('xfe_carrier_rule_edit_ajax')`：true 时返回 `xfe_carrier/rule/edit/ajax.phtml`，否则保留 `container.phtml`。

#### `Edit::getSaveUrl()` — 现有方法（ADR 0031 修改）

- AJAX 模式追加 `ajax=1` 参数，让前端 form.submit()（varienForm.submit → 原生 submit）触发 saveRuleAction 的 AJAX 分支。

#### `xfe_carrier/rule/edit/ajax.phtml` — 现有模板（ADR 0031 新建）

只输出 tabs + form 主体（不含 content-header chrome）。widgetTools.openDialog 的 Ajax.Updater({evalScripts: true}) 会自动执行模板里的 `<script>`（条件构建器等）。

#### `saveRuleAction()` — 现有方法（ADR 0031 修改）

- 增加 `$isAjax = (bool) $this->getRequest()->getParam('ajax')` 检测。
- AJAX 模式：所有 `_redirect` 改为 `_ajaxRuleSave(true, message, [], rule_id)`，所有错误分支改为 `_ajaxRuleSave(false, message, [errors])`。
- 非 AJAX 模式行为完全不变（兼容书签 / 分享链接）。

### 6.5 保存成功 / 失败的响应契约

成功：
```
{
    "success": true,
    "message": "Rule saved.",
    "rule_id": 123
}
```

失败（含 field-level 错误）：
```
{
    "success": false,
    "message": "Name is required",
    "errors": ["name"]
}
```

前端处理（rule-modal.js）：
- `success: true` → `widgetTools.closeDialog() + window.location.reload()`。
- `success: false` → 在 `#modal_dialog_message` 顶部插入红色错误条 div，弹窗保留，用户可继续编辑。

### 6.6 表单 submit hook 与 Back 按钮重写

`widgetTools.openDialog` 不改变 form.submit 行为（form 仍是原生 varienForm.submit → 原生 submit）。我们在 form 上加 capture-phase submit 监听器，截获后 preventDefault + stopPropagation + 走 XHR POST。

Back / Cancel 按钮检测：button 的 className / value / textContent / onclick 包含 "back" "return" "cancel" 或 "返回" "取消"，将其 onclick 重写为 `parent.widgetTools.closeDialog(); return false;`。

### 6.7 MutationObserver 与同步 fallback

`widgetTools.openDialog` 不暴露 `onComplete` 回调（不像 widget.Updater 那样）。我们用 `MutationObserver` 监听 `#modal_dialog_message` 的子节点变化，发现 `<form id="edit_form">` 后 disconnect + hook。

`observeFormLoad()` 实现要点（rule-modal.js）：

1. 同步检查：`target.querySelector('#edit_form')`，如果已存在（缓存命中或 mock 测试）→ 直接 hook 并 return，跳过 observer。
2. `MutationObserver` 不可用时回退到 polling（`setTimeout` 每 100ms 检查，最多 50 次 = 5 秒超时）。
3. 兜底超时：`setTimeout(5000)` 仍未发现 form → 显示「加载失败: 超时未找到 edit_form」错误条。

### 6.8 依赖与边界

- 弹窗 JS 仅依赖 `widgetTools` / `Dialog` / `Ajax.Updater` / `MutationObserver` / 浏览器原生 DOM + 事件 API + `XMLHttpRequest`，不引入第三方库。
- 条件构建器（`condition-builder.js`）依赖 prototype.js 全局 helper（`$()、Element.insert()`），父窗已加载 prototype.js，`Ajax.Updater({evalScripts: true})` 在注入的 `<script>` 执行后可直接访问这些 helper。**无需修改 condition-builder.js**。
- 三个 Grid 块只通过 `Mage::registry` 与 `Mage::helper('xfe_carrier')` 协作，符合 AGENTS.md §3 模块边界。
- layout `xfecarrier.xml` 在 `adminhtml_carrier_edit` 节点下注册 `widget.js` + `windows CSS` + `rule-modal.js`；其他 carrier 后台页面不加载这些 JS（仅在编辑承运商时才需要）。

### 6.9 UX 细节

- **标题覆盖**：widgetTools.openDialog 默认标题是 'Insert Widget...'，调用 `widgetTools.dialogWindow.setTitle(title)` 覆盖为各 Grid 的中文标题（'+ 添加 Logo 规则' 等）。`setTitle` 是 prototype-windows `Window` 类的公开方法（js/prototype/window.js:1032），调用 `Element.update(this.element.id + '_top', newTitle)`。
- **同一时刻只允许一个弹窗**：`open()` 入口检测 `instance.opened`，若已打开则不重复调用 `widgetTools.openDialog`。widgetTools 内部对 `#widget_window` 已存在时也会走 `Windows.focus()`，我们这里双保险。
- **重复提交防护**：`_submitting` flag 防止 form submit 事件被快速重复触发多次。
- **弹窗状态从 DOM 派生（ADR 0032 补丁 #3）**：本地不维护 `opened` 标志，`open()` 入口改用 `document.getElementById('widget_window')` 判断弹窗是否存在。widget.js 的 × 按钮走 `Window.close() → _notify('onClose') → widgetTools.closeDialog()` 路径，**不经过** `XFE_CarrierRuleModal.close()`，所以本地 flag 不会被重置——DOM 派生方案天然规避此坑。同时新增 `observeDialogRemoval()` 用 MutationObserver 监听 `document.body.childList`，发现 `#widget_window` 被移除时清理本地 `_observer` / `_submitting`。
- **关闭按钮**：widgetTools 自带的 × 按钮（prototype-windows Window._createCloseButton）调用 `Window.close()`，触发 `widgetTools.dialogWindow` 销毁 + `#widget_window` / `#modal_dialog_message` DOM 移除。Back / Cancel 按钮则被我们重写 onclick 为 `widgetTools.closeDialog()` 走相同路径。

### 6.10 烟雾测试

`tests/js/test-rule-modal.js`（jsdom + mock widgetTools.openDialog + mock XMLHttpRequest + VirtualConsole 监听 location.reload）覆盖：

- `open()` 调用 `widgetTools.openDialog(url + ajax=1)`
- `open()` 后 `widgetTools.dialogWindow.setTitle` 覆盖默认标题
- `open()` URL 已含 query 时用 `&` 拼接 ajax=1
- `open()` URL 为空时 warning
- widgetTools 不可用时 error
- 二次 open() 不重复调用
- 同步注入内容后 form hook 已就绪
- 保存成功 → closeDialog + reload
- 保存失败 → 弹窗内错误条
- 保存响应非 JSON → 错误条
- Back 按钮 onclick 重写为 widgetTools.closeDialog
- `XFE_CarrierRuleModal.close()` 走 widgetTools.closeDialog
- closeAndReload 同时关闭 + reload
- _submitting 防重复提交
- 第二次保存清除前一次错误条

### 6.11 为什么放弃 ADR 0031（自建 AJAX 注入）

ADR 0031 在落地后被用户进一步调整，理由：

1. **重复造轮子**：`js/mage/adminhtml/wysiwyg/widget.js` 已提供 `widgetTools.openDialog(url)`，内部 `Dialog.info` + `Ajax.Updater({evalScripts: true})` 与 ADR 0031 实现本质相同，但更稳定（经过 Magento 多年迭代）。
2. **依赖耦合**：自建版本需要在 layout 里手动加载 `prototype/window.js` + CSS + 自写 dialog markup，重复劳动。
3. **可维护性**：每次 prototype-windows 升级都要回头调我们自己的 CSS 选择器；widgetTools 已经经过大量场景验证。

ADR 0031 保留为历史决策记录（标注 superseded by 0032）。
