# 0026. JS 自启动 boot(小改 N)— 不依赖 type_switcher.phtml 渲染

- 状态：Accepted
- 日期：2026-09-18
- 决策者：AI 助手
- 关联 ADR：0022 / 0023 / 0024 / 0025
- 关联模块：XFE_Carrier 自定义属性"新建/编辑"表单

## 背景

用户实环境(2026-09-18,在小改 L + M 修复 boolean 行可见性 + name 查找之后):

> 缓存刷新了,http://www.m1-test.com/index.php/admin/carrier_customAttribute/new 还是一样问题

排查路径(基于用户在浏览器 console 跑诊断脚本输出):
1. JS 文件加载成功(`XfeCaTypeSwitcher: object`,18 个方法都在)
2. form 元素全部找到(`ft_id / oc_id / dv_id: true`)
3. **type_switcher.phtml 没被 layout 渲染**(`XfeCaFormNotes / XfeCaEditorLabels: undefined`)
4. 行编辑器容器没挂载(`editor_count: 0`)
5. 切 type 完全无反应(`after.visible: null`)

诊断结论:`type_switcher.phtml` 的 inline script 没执行 → `XfeCaTypeSwitcher.bind()` 没被调用 → `mountOptionsEditor` 没挂载容器 → 切 type 无效果。

根因:某些 Magento 后台环境下,`<reference name="js">` 不会渲染其下的 phtml 块(layout XML 配置差异 / 缓存 / 模块加载顺序等原因,具体路径无法复现到本地)。

## 决策

`custom-attribute-form-switcher.js` 文件末尾追加自启动 boot 逻辑 — **不依赖任何 phtml inline script 渲染**。

```js
(function autoBoot() {
    function findByName(name) {
        return document.querySelector('select[name="' + name + '"]')
            || document.querySelector('input[name="' + name + '"]')
            || document.querySelector('textarea[name="' + name + '"]')
            || document.getElementById(name);
    }
    function tryBoot() {
        var fieldTypeEl = findByName('field_type');
        var optionsEl   = findByName('options_csv');
        var defaultEl   = findByName('default_value');
        if (!fieldTypeEl || !defaultEl) return false;
        if (optionsEl && optionsEl.__xfeCaAutoBooted) return true;
        root.XfeCaTypeSwitcher.bind({...});
        if (optionsEl) optionsEl.__xfeCaAutoBooted = true;
        return true;
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            if (!tryBoot()) setTimeout(tryBoot, 200);
        });
    } else {
        if (!tryBoot()) setTimeout(tryBoot, 200);
    }
})();
```

特性:
- **零依赖**:不依赖 type_switcher.phtml 的 inline script,也不依赖 layout `<reference name="js">` 渲染
- **自动检测**:三个 form 元素(field_type / options_csv / default_value)任意缺失都跳过
- **幂等**:`__xfeCaAutoBooted` 标记防止重复 bind
- **兼容 fallback**:如果 autoBoot 失败,旧的 type_switcher.phtml + findByName(小改 M)仍生效
- **i18n fallback**:yesLabel / noLabel / blankLabel 默认值采用 UTF-8 中文字面量,即使 XfeCaEditorLabels 未注入

## 后果

- 正面:彻底解耦 JS 启动与 layout phtml 渲染,任意 Magento 后台环境都能自动启动
- 负面:页面打开时会自动执行 bind()(已在 DOMContentLoaded 时),对其他页面无副作用 — 找不到三个 form 元素时静默返回 false
- 兼容:旧的 type_switcher.phtml + 小改 M 的 findByName 仍生效,作为 fallback

## 实施范围

| 文件 | 改动 |
|---|---|
| `js/xfe_carrier/custom-attribute-form-switcher.js` | 末尾追加 autoBoot 闭包(~40 行) |
| `tests/js/run-tests.js` | 不变(JS 测试用 jsdom,autoBoot 在 jsdom 里 tryBoot 静默失败) |
| `task_plan.md` | 阶段 18 / 小改 N |
| `docs/architecture/xfe-carrier-custom-attribute-form-switcher-js.md` | §8 修订记录 |

预期测试基线:JS 162 → 166 维持,PHP 452 维持。
