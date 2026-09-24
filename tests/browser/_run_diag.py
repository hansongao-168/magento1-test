"""完整测试 diagnose.html(现在 JS 文件含 autoBoot,即使 type_switcher.phtml 不挂载也能 boot)"""
import json
from playwright.sync_api import sync_playwright

URL = "http://localhost:8765/diagnose.html"
with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    page.goto(URL)
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(300)

    # Step 3: 检查容器(此时 type=text, 容器应挂载但 display:none)
    page.click("button.btn:nth-of-type(3)")
    page.wait_for_timeout(200)
    r3 = page.locator("#r3").text_content()
    print("=== Step 3 (容器现状, type=text) ===")
    print(r3)

    # Step 4: 切 boolean
    page.click("button.btn:nth-of-type(4)")
    page.wait_for_timeout(300)
    r4 = page.locator("#r4").text_content()
    print("\n=== Step 4 (切 boolean) ===")
    print(r4)

    # 截图
    page.screenshot(path="D:/www/m1-test.com/tests/browser/_diag_autoboot.png", full_page=True)

    # 额外:验证没有 type_switcher.phtml 的 inline script 注入时,autoBoot 仍工作
    # 先移除 phtml 块,刷新,再检查
    page.evaluate("""
    // 模拟: phtml 没渲染 — 但 JS 文件加载,autoBoot 应该自动跑
    window.XfeCaFormNotes = undefined;
    window.XfeCaEditorLabels = undefined;
    // 重新加载页面试 autoBoot 效果
    """)
    # 但上面不会重置 DOM.直接测 Step 3+4 在 diagnose.html 的当前状态
    browser.close()
print("[done]")
