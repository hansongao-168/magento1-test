# 0031. 弹窗重构：iframe → AJAX 注入（ADR 0029 修）

> **⚠️ Superseded by ADR 0032 (2026-09-23)**
> 
> 本 ADR 描述的「自建 AJAX 注入式弹窗」在落地后被用户进一步调整；新方案见 ADR 0032，改用 widgetTools.openDialog 薄壳包装（依赖 Magento 标准 widget 基础设施）。本 ADR 保留作为历史决策记录。


- 状态：**Superseded by 0032**
- 取代：ADR 0029 的 iframe 弹窗实现
- 被取代于：ADR 0032（widgetTools.openDialog 薄壳包装）
- 日期：2026-09-23
- 决策者：AI 助手
- 取代：ADR 0029 的 iframe 弹窗实现

## 背景

ADR 0029 在三个 Resource Grid（Logo / Account / FtpAccount）上做了 iframe 弹窗，2026-09-23 用户反馈：

> 还是不行，不如改ajax

实测问题：

1. **chrome 隐藏 CSS 选择器在某些主题/扩展 override 下失效**——比如模块化改造 header.phtml / menu.phtml 后 .header / .nav-bar 选择器即使放宽（去掉 div.wrapper > 前缀）也不保证全覆盖；inject 的 CSS 还可能被某些后台扩展 hook 覆盖。
2. **form submit + 重定向时序在 iframe 内不稳定**——用户点 Save 按钮后弹窗可能在重定向完成前就已经销毁，监听 onload 不能保证抓得住「保存完成」事件。
3. **prototype.js / condition-builder.js 在 iframe 重载时重新初始化**——一旦用户保存失败、Back 重进或翻 tab 都需要重新 hook form/button，叠加状态越来越不可靠。

用户明确偏好：**改为 AJAX 注入方案**。本 ADR 记录重构决策。

## 决策

把弹窗实现从 **iframe 内嵌整页后台** 改为 **AJAX 拉 HTML 注入到 overlay div**：

1. **后端：?ajax=1 标志**
   - editRuleAction() 检查 isAjax()，如果 ajax=1 → 仅渲染表单 + tabs 主体，不输出 Magento 后台 chrome（.header / .nav-bar / .footer / .breadcrumbs 等）。
   - 新建模板 xfe_carrier/rule/edit/ajax.phtml，只输出 tabs + form HTML + 必要 script。
   - saveRuleAction() 检查 isAjax()，如果 ajax=1 → 返回 application/json：{success: bool, message: string, errors: array, rule_id: int}，不发起 _redirect。

2. **前端：rewrite rule-modal.js**
   - 移除 iframe + chrome CSS 注入 + injectModalBehaviors。
   - 新流程：弹窗 div overlay → XMLHttpRequest GET editRule?ajax=1&... → content.innerHTML = responseText → 手动抽取并 eval script 标签（因为 innerHTML 不会执行 script）→ hook form submit → 保存。
   - 复用 prototype.js 已有的全局 helper（$()、Element.insert()）——条件构建器本身不需要改。

3. **关闭策略**
   - 保存成功 → 关弹窗 + window.location.reload()（父窗仍在 carrier/edit，自动刷新当前 Tab Grid）。
   - 保存校验失败 → 弹窗内显示错误消息，**不**关闭弹窗，让用户继续编辑。
   - 点 Back / 取消 / × → 关闭弹窗，**不**刷新父窗。

4. **layout XML 不变**：skin JS 仍在 adminhtml_carrier_edit 节点加载。

### 文件清单

| 文件 | 变更 |
|------|------|
| skin/adminhtml/default/default/xfe_carrier/js/rule-modal.js | **重写**：移除 iframe + chrome CSS，改为 AJAX 注入 + script 提取执行 + form submit hook |
| app/code/community/XFE/Carrier/controllers/Adminhtml/CarrierController.php | editRuleAction 加 isAjax() 分支；saveRuleAction 加 ajax 分支（返回 JSON） |
| app/code/community/XFE/Carrier/Block/Adminhtml/Carrier/Rule/Edit.php | 加 getIsAjax() 方法，让模板可以根据 ajax 标志切换 layout |
| app/design/adminhtml/default/default/template/xfe_carrier/rule/edit/ajax.phtml | **新建**：仅输出 tabs + form 主体（无 chrome） |
| app/design/adminhtml/default/default/template/xfe_carrier/rule/edit/container.phtml | **保留**：非 ajax 模式继续用 |
| docs/architecture/carrier-rule-conditions.md §6 | 改写为 AJAX 方案说明 |
| docs/architecture/decisions/0029-carrier-rule-add-modal.md | 标注 superseded by 0031（保留文档历史） |
| tests/js/test-rule-modal.js | **重写**：移除 chrome CSS 注入 it 块，加 AJAX 流程 it 块（mock XHR） |
| tests/js/run-tests.js | 无改动（仍由 register({...}) 注册） |

## 备选方案

### A. iframe + 更激进的 chrome 隐藏 CSS（ADR 0029 修）

扩展选择器覆盖范围，加更多 !important，使用 Shadow DOM。

**问题**：chrome 元素散落在 50+ 模板里（包括模块化 override），打补丁是无限游戏；新扩展每次装都可能破坏；用户已表达不满。

### B. 完全重写 editRule 页面，去掉 chrome 渲染

让 editRule 永远走 no-chrome 模板（始终 ajax 模式）。

**问题**：直接打开 */carrier/editRule（非弹窗入口，例如书签/分享链接）的用户也会看到无 chrome 页面，破坏 admin UX 完整性。需保留两种入口。

**结论**：通过 ?ajax=1 标志切换，正常入口仍走完整 admin chrome。

### C. 把弹窗入口改成新窗口（window.open）

简单，但失去弹窗的轻量感 + 不能 lock 父窗滚动 + 移动端体验差。拒绝。

## 后果

### 正面

- 不依赖同源 iframe.contentDocument 访问，所有操作都是顶层 DOM。
- 弹窗内 form submit 走 AJAX POST → 校验失败时弹窗内显示错误（不丢失用户输入）。
- 移除 chrome CSS 选择器博弈，方案永久不破。
- 脚本注入后执行，prototype.js 全局 helpers 直接可用，condition-builder 不需要改。

### 负面

- AJAX 注入的脚本需要手动 eval（innerHTML 不执行 script）。JS 提取逻辑是必要的复杂度，需充分测试。
- script 在已加载页面执行，全局变量可能冲突（例如 conditionGroups / rootGroupOrder / nextGroupId 等是 var，重复执行会 reset）。当前 condition-builder.js 在 init 时会重置这些变量，因此**重开弹窗时旧状态会被覆盖**——这是我们想要的行为。
- 保存时需要传完整 form 数据（enctype=multipart/form-data），改用 FormData 对象而非 URL-encoded，避免破坏条件 JSON。

### 风险与回退

- 风险点：AJAX POST 后端契约要稳定，校验失败要带 field-level 错误信息。
- 回退成本：ADR 0029 的 iframe 实现 git 历史可恢复；本次改动不修改 layout XML / Column Renderer / DB schema，仅影响控制器 + JS。

## 关键技术点

### 1. AJAX 请求生命周期

```
open(url) {
  showOverlay()
  new XMLHttpRequest
    GET url + '?ajax=1&...'
    onload: injectHtml(responseText) -> runScripts() -> hookForm() -> hookButtons()
}

save() {
  new XMLHttpRequest
    POST saveRule url (form.action)
    Content-Type: application/x-www-form-urlencoded; charset=UTF-8
    body: serializeForm()
    onload: parse JSON {success, message}
      success → close + reload
      failure → reload HTML with errors (弹窗内显示)
}
```

### 2. script 提取与执行

```javascript
function runScripts(container) {
    var scripts = container.querySelectorAll('script');
    for (var i = 0; i < scripts.length; i++) {
        var script = scripts[i];
        var newScript = document.createElement('script');
        for (var j = 0; j < script.attributes.length; j++) {
            newScript.setAttribute(script.attributes[j].name, script.attributes[j].value);
        }
        newScript.text = script.textContent;
        script.parentNode.replaceChild(newScript, script);
    }
}
```

replaceChild(newScript, script) 触发浏览器解析执行。这是 prototype.js / jQuery 之外的纯 DOM API。

### 3. 后端 AJAX 模板契约

xfe_carrier/rule/edit/ajax.phtml（仅输出 tabs + form 主体，无 chrome）：

```
<div id="xfe-carrier-rule-modal-body">
    getChildHtml('tabs')
    getFormHtml()
    <script type="text/javascript">
        editForm = new varienForm('edit_form', getValidationUrl());
    </script>
    getFormScripts()
</div>
```

注意：ajax 模板**不**调用 getFormInitScripts()，因为 prototype.js 等已经在父窗加载过；不输出 content-header / content-footer。

### 4. saveRule AJAX 响应契约

成功：

```
{
    "success": true,
    "message": "Rule saved.",
    "rule_id": 123
}
```

失败：

```
{
    "success": false,
    "message": "Missing required field: name",
    "errors": ["name", "groups_data"]
}
```




