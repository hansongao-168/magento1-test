# 0032. 弹窗重构：AJAX 自实现 → widgetTools.openDialog（ADR 0031 修）

- 状态：Accepted
- 日期：2026-09-23
- 决策者：AI 助手
- 取代：ADR 0031 的自实现 AJAX 注入式弹窗

## 背景

ADR 0031 实现了「自建 overlay + popup + content + 自己 fetch + 自己注入 script + 自己 hook form」的弹窗。2026-09-23 用户反馈：

> 改为 widgetTools.openDialog

实测问题：

1. **重复造轮子**：Magento 1 标准库 `js/mage/adminhtml/wysiwyg/widget.js` 已提供 `widgetTools.openDialog(url)`，该 API 内部使用 `Dialog.info` + `Ajax.Updater({evalScripts: true})`，本质就是 ADR 0031 的实现，但更稳定（经过 Magento 多年迭代）。
2. **依赖耦合**：自实现版本需要在 layout 里手动加载 `prototype/window.js` + CSS + 自写 dialog markup，重复劳动。
3. **可维护性**：每次 prototype-windows 升级都要回头调我们自己的 CSS 选择器；widgetTools 已经经过大量场景验证。

用户明确偏好：**直接调用 widgetTools.openDialog**，让 Magento 标准的 widget 基础设施负责弹窗的 chrome / 注入 / evalScripts。

本 ADR 记录这次重构决策。

## 决策

把弹窗实现从「自建 overlay + XHR 注入」改为 **薄壳包装 widgetTools.openDialog**：

0. **修复补丁记录 (2026-09-23 晚)：**
   - **补丁 #1**：`editRuleAction()` 必须检测 `?ajax=1` 转发到 `editRuleAjaxAction()`，否则 chrome 整个注入弹窗。
   - **补丁 #2**：layout 必须同时加载 `prototype/windows/themes/default.css` (基础) + `magento.css` (主题)，否则弹窗 div 无基础样式 → 黑屏没窗口。
   - **补丁 #3**：弹窗状态必须从 DOM 派生（检查 `#widget_window` 是否存在），不能用本地 `instance.opened` flag。widget.js 的 × 按钮触发 `Window.close()` → `_notify("onClose")` → `widgetTools.closeDialog()`，**不经过** 我自己的 `closeDialog()` 函数 → `instance.opened` 不会被重置 → 第二次点击「+ 添加规则」时本地 flag 仍为 true → `return` → 弹窗打不开。修复：移除本地 flag，改在 `open()` 入口检查 `#widget_window` DOM 是否存在；新增 `observeDialogRemoval()` 用 MutationObserver 监听 `document.body.childList`，发现 `#widget_window` 被移除（用户点 ×）时清理本地 `_observer` / `_submitting`。
   - **补丁 #4 (本次)**：父页（`adminhtml_carrier_edit` handle）必须显式加载 `xfe_shippingrule/js/condition-builder.js`，否则弹窗内 `[+ 添加条件组]` 按钮 onclick 引用 `addConditionGroup()` 全局函数时报 `addConditionGroup is not defined`。iframe 时代 iframe 是独立 document 自带该 JS；widgetTools.openDialog 注入 innerHTML 时代父页必须自己拥有。

1. **后端：契约不变（但 editRuleAction() 加 ajax 分发）**
   - `editRuleAction()` / `editRuleAjaxAction()`：保持 ADR 0031 的 AJAX 模板契约不变（仅模板输出 tabs + form，无 chrome）。
   - `saveRuleAction()` AJAX 分支返回 JSON `{success, message, errors, rule_id}`。
   - 这些都是 widgetTools.openDialog 加载的 URL 所需要的契约，保持原样。
   - **重要：`editRuleAction()` 检测到 `?ajax=1` 参数时必须转发到 `editRuleAjaxAction()`**，否则 `widgetTools.openDialog` 把整个 admin 后台页（含 chrome 顶/底部）注入弹窗 div（ADR 0032 落地发现的真坑）。原 ADR 0031 走的是自建 `XHR.open('GET', ajaxUrl)`，调用方明确拼 `?ajax=1` 给后端 controller，所以走的是 `editRuleAction()` 入口分支但通过 `isAjax()` 检测路由——但 `editRuleAction()` 实际从未实现这个检测！ADR 0032 落地 widgetTools.openDialog 路径下注入 innerHTML，chrome 整个跑出来。修复：在 `editRuleAction()` 顶部增加 `if ($this->getRequest()->getParam('ajax')) { return $this->editRuleAjaxAction(); }`。

2. **layout：注册 widget.js + windows CSS（两层）**
   - `app/design/adminhtml/default/default/layout/xfecarrier.xml` 的 `adminhtml_carrier_edit` 节点增加：
     - `<action method="addJs"><script>mage/adminhtml/wysiwyg/widget.js</script></action>`（提供 `widgetTools` 全局对象）
     - `<action method="addItem"><type>js_css</type><name>prototype/windows/themes/default.css</name></action>`（基础样式，**缺这个会导致黑屏没窗口**）
     - `<action method="addCss"><name>lib/prototype/windows/themes/magento.css</name></action>`（Magento 主题，覆盖 default.css）
   - `prototype/window.js` 已被 main.xml 全局加载，无需重复。

3. **前端：rewrite rule-modal.js（薄壳版）**
   - `XFE_CarrierRuleModal.open(url, title)`：
     1. 拼上 `?ajax=1` 参数（让后端走 ajax 模板）。
     2. 调用 `widgetTools.openDialog(ajaxUrl)` 打开 Dialog 弹窗（id = `widget_window`，content element id = `modal_dialog_message`）。
     3. `widgetTools.openDialog` 内部 `new Ajax.Updater('modal_dialog_message', url, {evalScripts: true})` 拉取 HTML 并执行 `<script>`。
     4. 立即调用 `widgetTools.dialogWindow.setTitle(title)` 覆盖默认的 'Insert Widget...' 标题。
     5. 用 `MutationObserver` 监听 `modal_dialog_message`，发现 `#edit_form` 后 hook form submit + back/cancel 按钮。
   - `XFE_CarrierRuleModal.close()`：调用 `widgetTools.closeDialog()`。
   - `XFE_CarrierRuleModal.closeAndReload()`：`widgetTools.closeDialog() + window.location.reload()`。
   - 保存成功：关闭弹窗 + reload 父窗。
   - 保存失败：弹窗内显示红色错误条（向 `modal_dialog_message` 顶部插入错误 div）。

4. **layout XML 调整**：仅在 `adminhtml_carrier_edit` 节点加 widget.js + windows CSS，不影响其他页面。

### 文件清单

| 文件 | 变更 |
|------|------|
| skin/adminhtml/default/default/xfe_carrier/js/rule-modal.js | **重写**：从自建 overlay 改为 `widgetTools.openDialog` 薄壳包装 |
| app/design/adminhtml/default/default/layout/xfecarrier.xml | 在 `adminhtml_carrier_edit` 节点加 widget.js + windows CSS |
| docs/architecture/carrier-rule-conditions.md §6 | 改写为 widgetTools.openDialog 方案说明 |
| docs/architecture/decisions/0031-rule-modal-ajax.md | 标注 superseded by 0032（保留文档历史） |
| tests/js/test-rule-modal.js | **重写**：mock `widgetTools.openDialog` / `Dialog.info` / `Ajax.Updater` |

## 备选方案

### A. 完全沿用 ADR 0031（自建 AJAX 注入）

保留自建 overlay + popup + content + 自己注入 script 的实现。

**问题**：用户明确偏好 widgetTools.openDialog；自建版本与 Magento 标准 widget 基础设施重复。

### B. 用 iframe 弹窗（ADR 0029）

已被 ADR 0031 弃用，不再讨论。

### C. 调用 widgetTools.openDialog 但不覆盖 title

直接 `widgetTools.openDialog(url)`，标题统一显示 'Insert Widget...'。

**问题**：3 个 Grid（Logo / 账号 / FTP）的「+ 添加规则」需要不同中文标题（「添加 Logo 规则」等），无法直接复用默认标题。

**结论**：调用 `widgetTools.openDialog(url)` 后，用 `widgetTools.dialogWindow.setTitle(title)` 覆盖（prototype-windows 第 1032 行的 `setTitle` 公开 API）。这是 widgetTools 设计内的合法操作，不算绕过。

## 后果

### 正面

- 弹窗 chrome / 注入 / evalScripts 全交给 Magento 标准 widget 基础设施，与后台其他 widget 弹窗（产品页 CTA 选择器、CMS 块编辑器）体验一致。
- 无需在 layout 重复注册 `prototype/window.js`（已全局加载）+ 自写 dialog HTML 结构。
- 复用经过 Magento 多年迭代的 `Ajax.Updater({evalScripts: true})` 路径，与 widget chooser 行为一致。
- 后续 prototype-windows / widget.js 升级时，本弹窗自动跟随受益。

### 负面

- 强依赖 `widgetTools.openDialog` 全局对象（来自 `mage/adminhtml/wysiwyg/widget.js`）。必须在 layout 显式加载这个 JS（main.xml 仅在 `<editor>` handle 才加载）。
- 内容加载完成时机需自己 hook（`widgetTools.openDialog` 不暴露 `onComplete`）。用 `MutationObserver` 监听 `modal_dialog_message`，是合理的折中。
- 标题必须显式调用 `setTitle` 覆盖，无法在调用 `widgetTools.openDialog` 时直接传入。

### 风险与回退

- 风险点：依赖 widget.js / Dialog / Ajax.Updater 全局 API。这些都是 Magento 1.4+ 标准 API，长期稳定。
- 回退成本：ADR 0031 的 git 历史可恢复；本次改动不修改 layout 的 `adminhtml_carrier_editrule` handle / Column Renderer / DB schema，仅影响 layout 头部 JS 注册 + rule-modal.js。

## 关键技术点

### 1. widgetTools.openDialog 内部实现（js/mage/adminhtml/wysiwyg/widget.js）

```javascript
openDialog: function(widgetUrl) {
    if ($('widget_window') && typeof(Windows) != 'undefined') {
        Windows.focus('widget_window');
        return;
    }
    this.dialogWindow = Dialog.info(null, {
        draggable:true, resizable:false, closable:true,
        className:'magento', windowClassName:"popup-window",
        title: Translator.translate('Insert Widget...'),
        top:50, width:950, zIndex:1000, recenterAuto:false,
        hideEffect:Element.hide, showEffect:Element.show,
        id:'widget_window',
        onClose: this.closeDialog.bind(this)
    });
    new Ajax.Updater('modal_dialog_message', widgetUrl, {evalScripts: true});
}
```

关键不变量：
- 弹窗 DOM id = `widget_window`
- 内容容器 DOM id = `modal_dialog_message`
- `widgetTools.dialogWindow` 是 `Window` 对象（prototype-windows 实例），有 `setTitle(newTitle)` 方法（第 1032 行）。
- `Ajax.Updater` 带 `evalScripts: true`，自动执行响应中的 `<script>`（条件构建器等直接生效）。
- 弹窗可见性依赖 CSS — 必须同时加载 `default.css` (基础) + `magento.css` (主题)，否则只有 overlay 黑底，弹窗 div 不可见。

### 2. 覆盖默认 title

```javascript
var XFE_CarrierRuleModal = {
    open: function(url, title) {
        widgetTools.openDialog(ajaxUrl);
        if (widgetTools.dialogWindow && typeof widgetTools.dialogWindow.setTitle === 'function') {
            widgetTools.dialogWindow.setTitle(title || '规则');
        }
        instance._observeFormLoad();
    }
};
```

`setTitle(newTitle)` 是 prototype-windows `Window` 类的公开方法（js/prototype/window.js:1032），调用 `Element.update(this.element.id + '_top', newTitle)` 更新弹窗顶栏标题文字。

### 3. 内容加载完成后 hook form

`widgetTools.openDialog` 不暴露 onComplete 回调。我们用 `MutationObserver` 监听 `modal_dialog_message` 的子节点变化，发现 `#edit_form` 后立刻 disconnect + hook：

```javascript
function observeFormLoad() {
    var target = document.getElementById('modal_dialog_message');
    if (!target) return;
    var observer = new MutationObserver(function() {
        var form = target.querySelector('#edit_form');
        if (form && !form._xfeHooked) {
            observer.disconnect();
            hookFormSubmit(form);
            hookBackButtons(target);
        }
    });
    observer.observe(target, { childList: true, subtree: true });
}
```

### 4. 后端 AJAX 契约（继承自 ADR 0031，不变）

- GET `*/carrier/editRule?ajax=1` → 返回 ajax.phtml HTML（无 chrome 顶/底部，仅 tabs + form + 必要 script）。
- POST `*/carrier/saveRule?ajax=1` (form.action) → 返回 JSON `{success, message, errors, rule_id}`。

## 不变量

- `widgetTools` 来自 `mage/adminhtml/wysiwyg/widget.js`，必须在 layout `adminhtml_carrier_edit` 节点显式加载。
- `prototype/window.js` 已由 main.xml 全局加载，无需重复。
- `js/prototype/windows/themes/default.css` 由 layout 加载，提供 prototype-windows 弹窗基础样式（缺这个会黑屏没窗口）。
- `lib/prototype/windows/themes/magento.css` 由 layout 加载，提供 widget 弹窗 magento 主题样式。
- 后端契约不变（editRuleAction ajax 分发 + editRuleAjaxAction + saveRule ajax 分支）。
