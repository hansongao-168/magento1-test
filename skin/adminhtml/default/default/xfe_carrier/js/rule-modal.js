/**
 * XFE Carrier - Admin "Add Rule" Modal via widgetTools.openDialog (2026-09-23, ADR 0032)
 *
 * 在承运商编辑页（adminhtml/carrier/edit）的 3 个子列表（Logo / 账号 / FTP 账号）
 * 工具条与每行按钮上提供「+ 添加规则」按钮入口。
 *
 * 设计核心:薄壳包装 Magento 标准 widgetTools.openDialog(url):
 *   - widgetTools.openDialog 内部创建 #widget_window Dialog 弹窗,
 *     并 new Ajax.Updater('modal_dialog_message', url, {evalScripts: true})
 *     拉取 HTML 并自动执行 <script>(条件构建器等直接生效)。
 *   - 我们在调用后用 widgetTools.dialogWindow.setTitle(title) 覆盖默认
 *     'Insert Widget...' 标题。
 *   - 用 MutationObserver 监听 #modal_dialog_message 的子节点变化,
 *     发现 #edit_form 后 hook form submit + back/cancel 按钮。
 *
 * 用法:
 *   <button type="button"
 *           class="scalable add"
 *           onclick="XFE_CarrierRuleModal.open('…/carrier/editRule?carrier_id=12&module_code=logo', '+ 添加 Logo 规则')">
 *       <span><span><span>+ 添加规则</span></span></span>
 *   </button>
 *
 * 关闭策略:
 *   - 保存成功: widgetTools.closeDialog() + window.location.reload()
 *   - 保存失败: 弹窗内显示错误条, 不关闭
 *   - 点 Back / Cancel / × / overlay: widgetTools.closeDialog(), 不刷新父窗
 *
 * @category  XFE
 * @package   XFE_Carrier
 */
(function () {
    "use strict";

    var DEFAULT_TITLE = "规则";

    var instance = {
        url: "",
        title: DEFAULT_TITLE,
        // 不维护 opened 标志, 状态由 DOM 派生 (检查 #widget_window 是否存在),
        // 这样 widgetTools 自带的 × 按钮也能正确触发重置。
        _observer: null,
        _xhr: null,
        _submitting: false,
        _docObserver: null
    };

    /**
     * 弹出错误条到 #modal_dialog_message 顶部, 不关闭弹窗。
     */
    function showError(message) {
        var content = document.getElementById("modal_dialog_message");
        if (!content) { return; }
        hideError();
        var errDiv = document.createElement("div");
        errDiv.id = "xfe-carrier-rule-modal-error";
        errDiv.className = "xfe-carrier-rule-modal-error";
        errDiv.style.cssText =
            "margin:0 0 12px 0;padding:10px 12px;background:#f2dede;" +
            "border:1px solid #ebccd1;color:#a94442;border-radius:4px;";
        errDiv.textContent = message;
        content.insertBefore(errDiv, content.firstChild);
    }

    function hideError() {
        var err = document.getElementById("xfe-carrier-rule-modal-error");
        if (err && err.parentNode) {
            err.parentNode.removeChild(err);
        }
    }

    /**
     * hook form submit -> AJAX POST.
     * widgetTools.openDialog 不会改变 form.submit 行为, 我们的 hook
     * 必须在 capture 阶段截获 submit 事件并走 XHR。
     */
    function hookFormSubmit(form) {
        if (!form) { return false; }
        if (form._xfeCarrierRuleModalHooked) { return true; }
        form._xfeCarrierRuleModalHooked = true;
        form.addEventListener("submit", function (e) {
            if (e && typeof e.preventDefault === "function") {
                e.preventDefault();
            }
            if (e && typeof e.stopPropagation === "function") {
                e.stopPropagation();
            }
            submitFormViaAjax(form);
            return false;
        }, true);
        return true;
    }

    /**
     * hook Back / Cancel 按钮 -> widgetTools.closeDialog().
     * 检测方式: button 的 className / value / textContent / onclick 包含
     * "back" "return" "cancel" 或 "返回" "取消"。
     */
    function hookBackButtons(container) {
        if (!container) { return; }
        var buttons = container.querySelectorAll("button, input[type=button], a");
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            if (btn._xfeCarrierRuleModalHooked) { continue; }
            var text = (btn.className || "") + " " + (btn.value || "") + " " +
                (btn.textContent || "") + " " + (btn.getAttribute("onclick") || "");
            if (/back|return|cancel|返回|取消/i.test(text)) {
                btn._xfeCarrierRuleModalHooked = true;
                btn.setAttribute("onclick", "parent.widgetTools.closeDialog(); return false;");
            }
        }
    }

    function submitFormViaAjax(form) {
        if (instance._submitting) { return; }
        instance._submitting = true;
        hideError();

        var fd = new FormData(form);
        var xhr = new XMLHttpRequest();
        instance._xhr = xhr;
        xhr.open("POST", form.action, true);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.onload = function () {
            instance._xhr = null;
            instance._submitting = false;
            handleSaveResponse(xhr.responseText);
        };
        xhr.onerror = function () {
            instance._xhr = null;
            instance._submitting = false;
            showError("网络错误, 请重试");
        };
        xhr.send(fd);
    }

    function handleSaveResponse(rawText) {
        var resp = null;
        try {
            resp = JSON.parse(rawText);
        } catch (e) {
            showError("保存失败: 服务器返回非 JSON 响应");
            return;
        }
        if (resp && resp.success) {
            // 保存成功: 关闭弹窗 + 刷新父窗
            closeDialog();
            window.location.reload();
        } else {
            showError((resp && resp.message) ? resp.message : "保存失败");
        }
    }

    /**
     * 关闭 widgetTools 弹窗, 由 widgetTools.closeDialog() 内部走 Dialog 的
     * Window.close() 销毁 widget_window + modal_dialog_message DOM。
     *
     * 状态完全由 DOM 派生, 这里只清理本地 _observer / _xhr / _submitting。
     * widget_window DOM 是否存在由 widgetTools 自己维护 (Window.destroy() 调用
     * Element.remove + Windows.unregister); 包括用户点 × 按钮 (触发
     * widgetTools.closeDialog 回调) 的清理路径。
     */
    function closeDialog() {
        if (typeof window.widgetTools !== "undefined" && typeof window.widgetTools.closeDialog === "function") {
            window.widgetTools.closeDialog();
        }
        tearDownObserver();
        tearDownDocObserver();
        if (instance._xhr) {
            try { instance._xhr.abort(); } catch (e) { /* ignore */ }
            instance._xhr = null;
        }
        instance._submitting = false;
        instance.url = "";
        instance.title = DEFAULT_TITLE;
    }

    function tearDownDocObserver() {
        if (instance._docObserver) {
            try { instance._docObserver.disconnect(); } catch (e) { /* ignore */ }
            instance._docObserver = null;
        }
    }

    function tearDownObserver() {
        if (instance._observer) {
            try { instance._observer.disconnect(); } catch (e) { /* ignore */ }
            instance._observer = null;
        }
    }

    /**
     * 监听 modal_dialog_message 子节点变化, 发现 #edit_form 后立刻
     * disconnect + hook form submit + back/cancel 按钮。
     * 带超时保护 (5 秒), 防止极端情况下永久挂起。
     */
    function observeFormLoad() {
        var target = document.getElementById("modal_dialog_message");
        if (!target) {
            showError("加载失败: 未找到 modal_dialog_message 容器");
            return;
        }
        // 同步检查一次 (内容可能已存在, 例如缓存)
        var initialForm = target.querySelector("#edit_form");
        if (initialForm) {
            hookFormSubmit(initialForm);
            hookBackButtons(target);
            return;
        }

        // MutationObserver 不支持时回退到 polling
        var supportsMO = typeof window.MutationObserver === "function" ||
            (typeof window.WebKitMutationObserver === "function");
        if (!supportsMO) {
            pollForForm(target, 0);
            return;
        }

        var MO = window.MutationObserver || window.WebKitMutationObserver;
        var observer = new MO(function () {
            var form = target.querySelector("#edit_form");
            if (form) {
                observer.disconnect();
                instance._observer = null;
                hookFormSubmit(form);
                hookBackButtons(target);
            }
        });
        observer.observe(target, { childList: true, subtree: true });
        instance._observer = observer;

        // 兜底超时
        setTimeout(function () {
            if (instance._observer === observer) {
                observer.disconnect();
                instance._observer = null;
                if (!target.querySelector("#edit_form")) {
                    showError("加载失败: 超时未找到 edit_form");
                }
            }
        }, 5000);
    }

    function pollForForm(target, attempt) {
        if (attempt > 50) {
            showError("加载失败: 超时未找到 edit_form");
            return;
        }
        var form = target.querySelector("#edit_form");
        if (form) {
            hookFormSubmit(form);
            hookBackButtons(target);
            return;
        }
        setTimeout(function () { pollForForm(target, attempt + 1); }, 100);
    }

    /**
     * 监听 document.body 上 #widget_window 的移除 (用户点 × 关闭弹窗路径),
     * 用于清理 _observer / _submitting 等本地状态。
     * 注意: MutationObserver 监听 childList, 不递归 (我们只关心直接子节点)。
     */
    function observeDialogRemoval() {
        if (typeof window.MutationObserver !== "function" &&
            typeof window.WebKitMutationObserver !== "function") {
            return; // 不支持时直接跳过, 关闭路径由我们自己 XFE_CarrierRuleModal.close() 清理
        }
        var MO = window.MutationObserver || window.WebKitMutationObserver;
        var observer = new MO(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var removed = mutations[i].removedNodes;
                for (var j = 0; j < removed.length; j++) {
                    if (removed[j] && removed[j].id === "widget_window") {
                        // widget_window 被移除 -> 用户通过 × / Back 关闭了弹窗
                        tearDownObserver();
                        tearDownDocObserver();
                        instance._submitting = false;
                        instance.url = "";
                        instance.title = DEFAULT_TITLE;
                        return;
                    }
                }
            }
        });
        observer.observe(document.body, { childList: true });
        instance._docObserver = observer;
    }

    /**
     * 覆盖 widgetTools.openDialog 默认的 'Insert Widget...' 标题。
     * widgetTools.openDialog 把 Dialog 对象存在 widgetTools.dialogWindow,
     * prototype-windows 的 Window 类提供 setTitle(newTitle) 公开 API。
     */
    function overrideTitle(title) {
        if (typeof window.widgetTools === "undefined") { return; }
        var w = window.widgetTools.dialogWindow;
        if (w && typeof w.setTitle === "function") {
            w.setTitle(title);
        }
    }

    var XFE_CarrierRuleModal = {
        open: function (url, title) {
            if (!url) {
                if (window.console && window.console.warn) {
                    window.console.warn("XFE_CarrierRuleModal.open: url is required");
                }
                return;
            }
            if (typeof window.widgetTools === "undefined" ||
                typeof window.widgetTools.openDialog !== "function") {
                if (window.console && window.console.error) {
                    window.console.error(
                        "XFE_CarrierRuleModal.open: widgetTools.openDialog 不可用, " +
                        "请确认 layout 已加载 js/mage/adminhtml/wysiwyg/widget.js"
                    );
                }
                return;
            }

            // 状态完全从 DOM 派生: widget_window 还在说明弹窗未销毁 (用户可能点 × / Back / Cancel / 已 reload)
            // 我们走 widgetTools 自带的 focus 行为, 不重复 openDialog。
            if (document.getElementById("widget_window")) {
                if (typeof window.Windows !== "undefined" &&
                    typeof window.Windows.focus === "function") {
                    try { window.Windows.focus("widget_window"); } catch (e) { /* focus 失败不影响 */ }
                }
                return;
            }

            instance.url = url;
            instance.title = title || DEFAULT_TITLE;

            // 拼接 ajax=1 参数, 让后端走 ajax 模板 (无 chrome)
            var sep = url.indexOf("?") >= 0 ? "&" : "?";
            var ajaxUrl = url + sep + "ajax=1";

            // 调用 Magento 标准 widget 弹窗
            window.widgetTools.openDialog(ajaxUrl);

            // 覆盖默认 'Insert Widget...' 标题
            overrideTitle(instance.title);

            // 监听 modal_dialog_message, 内容加载完后 hook form
            observeFormLoad();
            // 监听 body 上 #widget_window 的移除, 用于清理 _observer (覆盖用户点 × 路径)
            observeDialogRemoval();
        },

        close: function () {
            closeDialog();
        },

        closeAndReload: function () {
            closeDialog();
            window.location.reload();
        },

        // ---- 测试 / 调试钩子 ----
        _setInstance: function (overrides) {
            for (var k in overrides) {
                if (Object.prototype.hasOwnProperty.call(overrides, k)) {
                    instance[k] = overrides[k];
                }
            }
        }
    };

    window.XFE_CarrierRuleModal = XFE_CarrierRuleModal;
})();
