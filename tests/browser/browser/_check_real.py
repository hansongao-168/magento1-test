"""访问真实 Magento 后台 URL,看 DOM + JS 加载"""
import json
from playwright.sync_api import sync_playwright

URL = "http://www.m1-test.com/index.php/admin/carrier_customAttribute/new"
console_msgs = []
page_errors = []

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    page.on("console", lambda msg: console_msgs.append(f"[{msg.type}] {msg.text}"))
    page.on("pageerror", lambda exc: page_errors.append(str(exc)))
    page.on("requestfailed", lambda req: console_msgs.append(f"[reqfail] {req.url} -> {req.failure}"))
    page.on("response", lambda r: console_msgs.append(f"[resp {r.status}] {r.url}") if r.status >= 400 else None)

    try:
        page.goto(URL, timeout=15000, wait_until="domcontentloaded")
        page.wait_for_load_state("networkidle", timeout=15000)
    except Exception as e:
        print(f"[NAV ERROR] {e}")

    # 检查 JS 全局
    r = page.evaluate("""
    ({
        xfeCaTypeSwitcher: typeof window.XfeCaTypeSwitcher,
        xfeCaEditorLabels: typeof window.XfeCaEditorLabels,
        xfeCaFormNotes: typeof window.XfeCaFormNotes,
        jQuery_loaded: typeof window.jQuery,
        prototype_loaded: typeof window.Prototype,
        prototype_Version: window.Prototype && window.Prototype.Version,
        has_field_type: !!document.querySelector('select[name="field_type"]'),
        has_options_csv_id: !!document.getElementById('options_csv'),
        has_options_csv_name: !!document.querySelector('input[name="options_csv"]'),
        has_default_value_id: !!document.getElementById('default_value'),
        has_default_value_name: !!document.querySelector('input[name="default_value"]'),
        editor_exists: !!document.querySelector('.xfe-ca-opt-editor'),
        title: document.title
    })
    """)
    print("=== DOM/JS 检查 ===")
    print(json.dumps(r, indent=2, ensure_ascii=False))

    # 看 field_type select 是否找到 + 列出 options
    opts = page.evaluate("""
    (function() {
        var s = document.querySelector('select[name="field_type"]');
        if (!s) return null;
        var opts = [];
        for (var i = 0; i < s.options.length; i++) opts.push({value: s.options[i].value, text: s.options[i].text});
        return {id: s.id, name: s.name, options: opts};
    })()
    """)
    print("\n=== field_type select ===")
    print(json.dumps(opts, indent=2, ensure_ascii=False))

    # 看 options_csv 元素实际属性
    opt_info = page.evaluate("""
    (function() {
        var el = document.getElementById('options_csv') || document.querySelector('input[name="options_csv"]');
        if (!el) return null;
        var tr = el.parentNode;
        while (tr && tr.nodeName !== 'TR') tr = tr.parentNode;
        return {
            tagName: el.tagName,
            id: el.id,
            name: el.name,
            type: el.type,
            value: el.value,
            parentTag: el.parentNode && el.parentNode.tagName,
            parentId: el.parentNode && el.parentNode.id,
            trStyle: tr && tr.getAttribute('style'),
            trDisplay: tr && tr.style.display
        };
    })()
    """)
    print("\n=== options_csv 元素 ===")
    print(json.dumps(opt_info, indent=2, ensure_ascii=False))

    # 截图
    page.screenshot(path="D:/www/m1-test.com/tests/browser/_real_url.png", full_page=True)
    print("\n[screenshot] _real_url.png saved")

    # 模拟切 boolean
    page.evaluate("""
    (function() {
        var s = document.querySelector('select[name="field_type"]');
        if (!s) return;
        s.value = 'boolean';
        s.dispatchEvent(new Event('change', {bubbles: true}));
    })()
    """)
    page.wait_for_timeout(500)

    after = page.evaluate("""
    (function() {
        var s = document.querySelector('select[name="field_type"]');
        var ed = document.querySelector('.xfe-ca-opt-editor');
        var r = {
            field_type: s && s.value,
            editor_exists: !!ed,
            editor_visible: ed ? (ed.style.display !== 'none') : null,
            editor_parent: ed && ed.parentNode.tagName,
            row_count: ed ? ed.querySelectorAll('.xfe-ca-opt-row').length : 0
        };
        if (ed) {
            r.rows = [];
            ed.querySelectorAll('.xfe-ca-opt-row').forEach(function(row, i) {
                var k = row.querySelector('.xfe-ca-opt-key');
                var l = row.querySelector('.xfe-ca-opt-label');
                r.rows.push({i: i, key: k && k.value, label: l && l.value, readonly: k && k.readOnly});
            });
            r.editor_html_first_300 = ed.outerHTML.substring(0, 300);
        }
        return r;
    })()
    """)
    print("\n=== 切 boolean 后 ===")
    print(json.dumps(after, indent=2, ensure_ascii=False))

    page.screenshot(path="D:/www/m1-test.com/tests/browser/_real_url_boolean.png", full_page=True)
    print("\n[screenshot] _real_url_boolean.png saved")

    if console_msgs:
        print("\n=== console / network errors ===")
        for m in console_msgs:
            print(m)
    if page_errors:
        print("\n=== page errors ===")
        for e in page_errors:
            print(e)

    browser.close()
