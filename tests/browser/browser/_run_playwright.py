"""真浏览器测试 - XFE_Carrier 自定义属性编辑表单 type 切换行为"""
import sys
import json
from playwright.sync_api import sync_playwright

URL = "http://localhost:8765/tests/browser/ca-form.html"

console_msgs = []

def log(msg):
    print(msg)
    console_msgs.append(msg)

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    page.on("console", lambda msg: console_msgs.append(f"[{msg.type}] {msg.text}"))
    page.on("pageerror", lambda exc: console_msgs.append(f"[pageerror] {exc}"))
    page.goto(URL)
    page.wait_for_load_state("networkidle")

    # 对每个 variant 跑 bind + type 切换
    def variant_check(variant_id):
        script = """
        (function () {
            const root = document.getElementById(%s);
            if (!root) return {error: 'variant not found'};
            const fieldTypeEl = root.querySelector('select');
            const inputs = root.querySelectorAll('input[type="text"]');
            const optionsEl = inputs[0];
            const defaultEl = inputs[1];
            const out = {fieldType: fieldTypeEl.value, optionsId: optionsEl.id, optionsName: optionsEl.name, defaultId: defaultEl.id, defaultName: defaultEl.name};
            if (typeof window.XfeCaTypeSwitcher === 'undefined') return Object.assign(out, {error: 'XfeCaTypeSwitcher undefined'});
            XfeCaTypeSwitcher.bind({fieldTypeEl, optionsEl, defaultEl, yesLabel: '是', noLabel: '否', blankLabel: '--'});
            function snap(type) {
                fieldTypeEl.value = type;
                fieldTypeEl.dispatchEvent(new Event('change', {bubbles: true}));
                const ed = optionsEl.parentNode.querySelector('.xfe-ca-opt-editor');
                const r = {};
                r.editor_exists = !!ed;
                if (ed) {
                    r.editor_display = ed.style.display || '(empty)';
                    const rows = ed.querySelectorAll('.xfe-ca-opt-row');
                    r.row_count = rows.length;
                    r.rows = [];
                    rows.forEach((row, i) => {
                        const k = row.querySelector('.xfe-ca-opt-key');
                        const l = row.querySelector('.xfe-ca-opt-label');
                        r.rows.push({key: k && k.value, label: l && l.value, readonly: k && k.readOnly});
                    });
                }
                r.tr_display = (function() { let t = optionsEl.parentNode; while (t && t.nodeName !== 'TR') t = t.parentNode; return t ? t.style.display || '(empty)' : 'NO_TR'; })();
                r.optionsEl_display = optionsEl.style.display || '(empty)';
                return r;
            }
            out.t_text    = snap('text');
            out.t_select  = snap('select');
            out.t_boolean = snap('boolean');
            out.t_multi   = snap('multiselect');
            return out;
        })();
        """ % json.dumps(variant_id)
        return page.evaluate(script)

    for vid, label in [("v1", "id=name"), ("v2", "id=edit_form_<name>"), ("v3", "name=options[<name>]")]:
        log(f"\n========== {vid} ({label}) ==========")
        r = variant_check(vid)
        log(json.dumps(r, ensure_ascii=False, indent=2))

    # 切到 boolean,截图
    page.evaluate("""
    document.querySelectorAll('select').forEach(s => { s.value = 'boolean'; s.dispatchEvent(new Event('change', {bubbles:true})); });
    """)
    page.wait_for_timeout(300)
    page.screenshot(path="D:/www/m1-test.com/tests/browser/_result_boolean.png", full_page=True)
    log("\n[screenshot] _result_boolean.png saved")

    # 切到 select,截图
    page.evaluate("""
    document.querySelectorAll('select').forEach(s => { s.value = 'select'; s.dispatchEvent(new Event('change', {bubbles:true})); });
    """)
    page.wait_for_timeout(300)
    page.screenshot(path="D:/www/m1-test.com/tests/browser/_result_select.png", full_page=True)
    log("[screenshot] _result_select.png saved")

    # console 输出
    if console_msgs:
        log("\n=== console ===")
        for m in console_msgs[-20:]:
            log(m)

    browser.close()

print("\n=== DONE ===")
