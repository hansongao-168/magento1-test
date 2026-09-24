# -*- coding: utf-8 -*-
import io

path = r"D:\www\m1-test.com\js\xfe_carrier\custom-attribute-form-switcher.js"
with io.open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# 已注入过则跳过
if 'autoBoot' in content:
    print('[SKIP] autoBoot already injected')
else:
    marker = "        mountOptionsEditor:     mountOptionsEditor,\n        bind:               bind\n    };\n}(typeof window !== 'undefined' ? window : this));"
    replacement = """        mountOptionsEditor:     mountOptionsEditor,
        bind:               bind
    };

    // \u5c0f\u6539 N(2026-09-18):\u81ea\u542f\u52a8 boot \u2014 \u4e0d\u4f9d\u8d56 type_switcher.phtml \u7684 inline script \u6e32\u67d3
    // \u6839\u56e0:\u67d0\u4e9b Magento \u540e\u53f0 layout \u914d\u7f6e\u4e0b <reference name=\"js\"> \u4e0d\u4f1a\u6e32\u67d3 phtml \u5757,
    // \u5bfc\u81f4 bind() \u4e0d\u88ab\u8c03\u7528 \u2192 \u884c\u7f16\u8f91\u5668\u5bb9\u5668\u4ece\u672a\u6302\u8f7d \u2192 \u5207 type \u65e0\u6548\u679c\u3002
    // \u73b0\u5728 JS \u6587\u4ef6\u52a0\u8f7d\u5b8c,\u81ea\u52a8\u68c0\u6d4b\u4e09\u4e2a form \u5143\u7d20,\u627e\u5230\u5c31\u542f\u52a8 bind()\u3002
    (function autoBoot() {
        function findByName(name) {
            if (typeof document === 'undefined') return null;
            return document.querySelector('select[name=\"' + name + '\"]')
                || document.querySelector('input[name=\"' + name + '\"]')
                || document.querySelector('textarea[name=\"' + name + '\"]')
                || document.getElementById(name);
        }
        function tryBoot() {
            var fieldTypeEl = findByName('field_type');
            var optionsEl   = findByName('options_csv');
            var defaultEl   = findByName('default_value');
            if (!fieldTypeEl || !defaultEl) return false;
            if (optionsEl && optionsEl.__xfeCaAutoBooted) return true;
            try {
                root.XfeCaTypeSwitcher.bind({
                    fieldTypeEl: fieldTypeEl,
                    optionsEl:   optionsEl,
                    defaultEl:   defaultEl,
                    yesLabel:   (root.XfeCaEditorLabels && root.XfeCaEditorLabels.yesLabel) || '\u662f',
                    noLabel:    (root.XfeCaEditorLabels && root.XfeCaEditorLabels.noLabel)  || '\u5426',
                    blankLabel: (root.XfeCaEditorLabels && root.XfeCaEditorLabels.blankLabel) || '-- \u8bf7\u9009\u62e9 --'
                });
                if (optionsEl) optionsEl.__xfeCaAutoBooted = true;
                return true;
            } catch (e) {
                return false;
            }
        }
        if (typeof document === 'undefined') return;
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                if (!tryBoot()) setTimeout(tryBoot, 200);
            });
        } else {
            if (!tryBoot()) setTimeout(tryBoot, 200);
        }
    })();
}(typeof window !== 'undefined' ? window : this));"""
    if marker not in content:
        print('[NOT MATCHED] marker not found')
        raise SystemExit(2)
    content = content.replace(marker, replacement)
    with io.open(path, 'w', encoding='utf-8') as f:
        f.write(content)
    print('[OK] autoBoot injected')
