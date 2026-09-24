/**
 * XFE Carrier - rule-modal.js jsdom 烟雾测试 (ADR 0032, widgetTools.openDialog 薄壳)
 *
 * 用 jsdom 模拟打开弹窗流程,验证:
 *  - open() 调用 widgetTools.openDialog(ajaxUrl), URL 含 ajax=1
 *  - open() 后调用 widgetTools.dialogWindow.setTitle 覆盖默认标题
 *  - mock 的 widgetTools.openDialog 同步注入 HTML, 触发同步 hook 路径
 *  - form submit 走 AJAX POST, 成功 -> close + reload, 失败 -> 弹窗内错误
 *  - Back 按钮 onclick 重写为 widgetTools.closeDialog()
 *  - close() / closeAndReload() 行为
 *  - 二次 open() 不重复调用 widgetTools.openDialog
 *  - widgetTools 不可用时报 error, 不创建弹窗
 *
 * 入口:由 run-tests.js 在末尾调用 register({ describe, it, assert }),
 *      把本文件的测试块注册到 run-tests.js 的同一个 groups[] 闭包里。
 */
'use strict';

const fs = require('fs');
const path = require('path');
const { JSDOM, VirtualConsole } = require('jsdom');
const jsCode = fs.readFileSync(
    path.join(__dirname, '..', '..', 'skin', 'adminhtml', 'default', 'default', 'xfe_carrier', 'js', 'rule-modal.js'),
    'utf8'
);

// === 后端模拟 HTML ===
const FAKE_RULE_HTML = ''
    + '<form id="edit_form" name="edit_form" action="http://example.com/admin/carrier/saveRule/ajax/1" method="post">'
    + '<input type="hidden" name="form_key" value="abc123"/>'
    + '<input type="hidden" name="groups_data" value="[]"/>'
    + '<div class="entry-edit"><fieldset><input type="text" name="name" value="Test Rule"/></fieldset></div>'
    + '<div class="content-header"><p class="form-buttons">'
    + '<button type="button" class="scalable back" onclick="setLocation(\'http://example.com/back\')">返回</button>'
    + '<button type="button" class="scalable save">保存规则</button>'
    + '</p></div>'
    + '</form>';

function setup(options) {
    options = options || {};
    // jsdom VirtualConsole 监听 navigation 警告, 间接判断 location.reload 被调用
    let navAttempted = 0;
    const vc = new VirtualConsole();
    vc.on('jsdomError', function (err) {
        if (err && err.message && err.message.indexOf('navigation to another Document') >= 0) {
            navAttempted++;
        }
    });
    const dom = new JSDOM(
        '<!doctype html><html><body><button id="opener">+ Add Rule</button></body></html>',
        { runScripts: 'dangerously', url: 'http://example.com/admin/carrier/edit/id/12', pretendToBeVisual: true, virtualConsole: vc }
    );
    const win = dom.window;
    const doc = win.document;

    // === Mock XMLHttpRequest (saveRule POST) ===
    const xhrInstances = [];
    function FakeXHR() {
        this._method = null;
        this._url = null;
        this._body = null;
        this._headers = {};
        this.responseText = '';
        this.status = 0;
        this.onload = null;
        this.onerror = null;
        xhrInstances.push(this);
    }
    FakeXHR.prototype.open = function (method, url) { this._method = method; this._url = url; };
    FakeXHR.prototype.setRequestHeader = function (k, v) { this._headers[k] = v; };
    FakeXHR.prototype.send = function (body) { this._body = body; };
    FakeXHR.prototype.abort = function () { this._aborted = true; };
    win.XMLHttpRequest = FakeXHR;

    // === Mock location.reload 通过 VirtualConsole 间接监听 ===
    // jsdom 的 Location.reload 是原生 binding, 不可覆写; 但调用时会发出
    // 'jsdomError: Not implemented: navigation to another Document' 警告。
    // 我们监听 virtualConsole 来判断 reload 是否被调用。

    // === Mock widgetTools.openDialog (同步注入) ===
    const widgetApi = {
        dialogWindow: null,
        lastUrl: null,
        _closed: false,
        _title: '',
        openDialog: function (widgetUrl) {
            this.lastUrl = widgetUrl;
            // 模拟 Dialog.info 创建 #widget_window + #modal_dialog_message
            let win1 = doc.getElementById('widget_window');
            if (!win1) {
                win1 = doc.createElement('div');
                win1.id = 'widget_window';
                doc.body.appendChild(win1);
            }
            let content = doc.getElementById('modal_dialog_message');
            if (!content) {
                content = doc.createElement('div');
                content.id = 'modal_dialog_message';
                content.className = 'magento_message';
                win1.appendChild(content);
            }
            // 同步注入 HTML (模拟 Ajax.Updater 完成)
            content.innerHTML = FAKE_RULE_HTML;
            // 模拟 dialogWindow 对象 (prototype-windows Window)
            const winApi = this;
            this.dialogWindow = {
                setTitle: function (t) { winApi._title = t; },
                close: function () { winApi._closed = true; }
            };
        },
        closeDialog: function () {
            const winEl = doc.getElementById('widget_window');
            if (winEl && winEl.parentNode) {
                winEl.parentNode.removeChild(winEl);
            }
            const c = doc.getElementById('modal_dialog_message');
            if (c && c.parentNode) {
                c.parentNode.removeChild(c);
            }
            this._closed = true;
            this.dialogWindow = null;
        }
    };
    if (!options.disableWidgetTools) {
        win.widgetTools = widgetApi;
    }

    dom.window.eval(jsCode);

    const result = {
        dom: dom,
        doc: doc,
        win: win,
        xhrInstances: xhrInstances,
        widgetApi: widgetApi,
        isReloaded: function () { return navAttempted > 0; },
        navAttempted: function () { return navAttempted; }
    };
    return result;
}

module.exports = function register(env) {
    const describe = env.describe;
    const it = env.it;
    const assert = env.assert;

    describe('rule-modal.js — widgetTools.openDialog 薄壳 (ADR 0032)', function () {

        it('open() 调用 widgetTools.openDialog(url + ajax=1) 并覆盖标题', function () {
            const s = setup();
            s.doc.getElementById('opener').focus();
            s.win.XFE_CarrierRuleModal.open(
                'http://example.com/admin/carrier/editRule?carrier_id=12&module_code=logo',
                '+ 添加 Logo 规则'
            );
            assert.equal(s.widgetApi.lastUrl,
                'http://example.com/admin/carrier/editRule?carrier_id=12&module_code=logo&ajax=1',
                'URL 追加 ajax=1');
            assert.ok(!!s.doc.getElementById('widget_window'), 'widget_window 已创建');
            assert.ok(!!s.doc.getElementById('modal_dialog_message'), 'modal_dialog_message 已创建');
            assert.equal(s.widgetApi._title, '+ 添加 Logo 规则', '标题被覆盖');
        });

        it('open() URL 已含 query 时用 & 拼接 ajax=1', function () {
            const s = setup();
            s.win.XFE_CarrierRuleModal.open(
                'http://example.com/admin/carrier/editRule?carrier_id=12',
                'Add Rule'
            );
            assert.equal(s.widgetApi.lastUrl,
                'http://example.com/admin/carrier/editRule?carrier_id=12&ajax=1');
        });

        it('open() URL 为空时报 warning 并返回', function () {
            const s = setup();
            const origWarn = s.win.console && s.win.console.warn;
            let warned = false;
            if (s.win.console) {
                s.win.console.warn = function () { warned = true; };
            }
            s.win.XFE_CarrierRuleModal.open('', 'Add Rule');
            assert.ok(!s.doc.getElementById('widget_window'), 'widget_window 未创建');
            assert.equal(warned, true, 'console.warn 被调用');
            if (origWarn) { s.win.console.warn = origWarn; }
        });

        it('widgetTools 不可用时报 error 不创建弹窗', function () {
            const s = setup({ disableWidgetTools: true });
            const origErr = s.win.console && s.win.console.error;
            let errored = false;
            if (s.win.console) {
                s.win.console.error = function () { errored = true; };
            }
            s.win.XFE_CarrierRuleModal.open('http://example.com/x', 'Add Rule');
            assert.ok(!s.doc.getElementById('widget_window'), 'widget_window 未创建');
            assert.equal(errored, true, 'console.error 被调用');
            if (origErr) { s.win.console.error = origErr; }
        });

        it('二次 open() (widget_window 仍存在) 不重复调用 widgetTools.openDialog', function () {
            const s = setup();
            s.win.XFE_CarrierRuleModal.open('http://example.com/x?a=1', 'Title 1');
            const firstCallUrl = s.widgetApi.lastUrl;
            s.win.XFE_CarrierRuleModal.open('http://example.com/y?a=2', 'Title 2');
            assert.equal(s.widgetApi.lastUrl, firstCallUrl, 'lastUrl 仍是第一次的值');
            assert.ok(!!s.doc.getElementById('widget_window'), 'widget_window 仍只有 1 个');
        });

        it('close() 后再次 open() 能重新打开 (修复 widgetTools × 按钮关闭路径)', function () {
            const s = setup();
            s.win.XFE_CarrierRuleModal.open('http://example.com/x?a=1', 'Title 1');
            assert.ok(!!s.doc.getElementById('widget_window'), 'widget_window 第一次创建');
            s.win.XFE_CarrierRuleModal.close();
            assert.ok(!s.doc.getElementById('widget_window'), 'close 后 widget_window 被移除');
            // 再次 open - 应该能重新创建
            s.win.XFE_CarrierRuleModal.open('http://example.com/y?a=2', 'Title 2');
            assert.ok(!!s.doc.getElementById('widget_window'), '第二次 open 重新创建 widget_window');
            assert.equal(s.widgetApi.lastUrl,
                'http://example.com/y?a=2&ajax=1', '第二次 open 调用新 URL');
        });

        it('同步注入内容后, hook form submit 已就绪', function () {
            const s = setup();
            s.win.XFE_CarrierRuleModal.open('http://example.com/x', 'Add Rule');
            const form = s.doc.querySelector('#modal_dialog_message #edit_form');
            assert.ok(!!form, 'edit_form 已存在');
            assert.equal(form._xfeCarrierRuleModalHooked, true, 'form 已 hook');
        });

        it('保存成功 -> widgetTools.closeDialog() + window.location.reload', function () {
            const s = setup();
            s.win.XFE_CarrierRuleModal.open('http://example.com/x', 'Add Rule');
            const form = s.doc.querySelector('#modal_dialog_message #edit_form');
            form.dispatchEvent(new s.win.Event('submit', { bubbles: true, cancelable: true }));
            const xhr = s.xhrInstances[0];
            assert.equal(xhr._method, 'POST', 'POST 方法');
            assert.ok(xhr._url.indexOf('saveRule') >= 0, 'POST 到 saveRule');
            xhr.status = 200;
            xhr.responseText = JSON.stringify({ success: true, message: 'OK', rule_id: 123 });
            xhr.onload();
            assert.equal(s.widgetApi._closed, true, 'widgetTools 弹窗已关闭');
            assert.equal(s.isReloaded(), true, 'reload 被调用');
        });

        it('保存失败 -> 弹窗内显示错误, 不关闭', function () {
            const s = setup();
            s.win.XFE_CarrierRuleModal.open('http://example.com/x', 'Add Rule');
            const form = s.doc.querySelector('#modal_dialog_message #edit_form');
            form.dispatchEvent(new s.win.Event('submit', { bubbles: true, cancelable: true }));
            const xhr = s.xhrInstances[0];
            xhr.status = 200;
            xhr.responseText = JSON.stringify({ success: false, message: '名称必填' });
            xhr.onload();
            assert.ok(!!s.doc.getElementById('widget_window'), '弹窗保留');
            const err = s.doc.getElementById('xfe-carrier-rule-modal-error');
            assert.ok(!!err, '错误条已显示');
            assert.equal(err.textContent, '名称必填');
        });

        it('保存响应非 JSON -> 错误条, 不关闭', function () {
            const s = setup();
            s.win.XFE_CarrierRuleModal.open('http://example.com/x', 'Add Rule');
            const form = s.doc.querySelector('#modal_dialog_message #edit_form');
            form.dispatchEvent(new s.win.Event('submit', { bubbles: true, cancelable: true }));
            const xhr = s.xhrInstances[0];
            xhr.status = 200;
            xhr.responseText = '<html>oops</html>';
            xhr.onload();
            const err = s.doc.getElementById('xfe-carrier-rule-modal-error');
            assert.ok(!!err, '错误条已显示');
        });

        it('Back 按钮 onclick 被重写为 widgetTools.closeDialog()', function () {
            const s = setup();
            s.win.XFE_CarrierRuleModal.open('http://example.com/x', 'Add Rule');
            const back = s.doc.querySelector('#modal_dialog_message button.scalable.back');
            assert.ok(!!back, 'back 按钮存在');
            assert.ok(back._xfeCarrierRuleModalHooked, 'back 按钮已标记');
            const onclickAttr = back.getAttribute('onclick') || '';
            assert.ok(onclickAttr.indexOf('widgetTools.closeDialog') >= 0,
                'back onclick 重写为 widgetTools.closeDialog()');
        });

        it('XFE_CarrierRuleModal.close() 走 widgetTools.closeDialog', function () {
            const s = setup();
            s.win.XFE_CarrierRuleModal.open('http://example.com/x', 'Add Rule');
            s.win.XFE_CarrierRuleModal.close();
            assert.equal(s.widgetApi._closed, true, 'widgetTools 已 close');
            assert.ok(!s.doc.getElementById('widget_window'), 'widget_window DOM 已清理');
        });

        it('closeAndReload() 同时关闭弹窗 + reload', function () {
            const s = setup();
            s.win.XFE_CarrierRuleModal.open('http://example.com/x', 'Add Rule');
            s.win.XFE_CarrierRuleModal.closeAndReload();
            assert.equal(s.widgetApi._closed, true, 'widgetTools 已 close');
            assert.equal(s.isReloaded(), true, 'reload 被调用');
        });

        it('_submitting 防重复提交', function () {
            const s = setup();
            s.win.XFE_CarrierRuleModal.open('http://example.com/x', 'Add Rule');
            const form = s.doc.querySelector('#modal_dialog_message #edit_form');
            form.dispatchEvent(new s.win.Event('submit', { bubbles: true, cancelable: true }));
            const beforeCount = s.xhrInstances.length;
            form.dispatchEvent(new s.win.Event('submit', { bubbles: true, cancelable: true }));
            assert.equal(s.xhrInstances.length, beforeCount, '第二次 submit 不创建新 XHR');
        });

        it('第二次保存触发时, 第一次的错误条被清除', function () {
            const s = setup();
            s.win.XFE_CarrierRuleModal.open('http://example.com/x', 'Add Rule');
            const form = s.doc.querySelector('#modal_dialog_message #edit_form');
            form.dispatchEvent(new s.win.Event('submit', { bubbles: true, cancelable: true }));
            const xhr = s.xhrInstances[0];
            xhr.status = 200;
            xhr.responseText = JSON.stringify({ success: false, message: 'fail' });
            xhr.onload();
            assert.ok(!!s.doc.getElementById('xfe-carrier-rule-modal-error'), '错误条出现');
            // 第二次提交
            form.dispatchEvent(new s.win.Event('submit', { bubbles: true, cancelable: true }));
            assert.ok(!s.doc.getElementById('xfe-carrier-rule-modal-error'), '错误条已清除');
        });

    });
};
