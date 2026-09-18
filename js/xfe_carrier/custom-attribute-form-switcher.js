/**
 * XFE_Carrier 自定义属性表单 — field_type 联动
 *
 * 暴露 window.XfeCaTypeSwitcher 全局对象,提供:
 *   parseOptionsCsv(s)        字符串 -> 字符串[]
 *   buildTextInput(opts)      {memo, withNumberValidator} -> HTMLInputElement
 *   buildSelect(opts)         {memo, multiple} -> HTMLSelectElement
 *   buildBooleanRadios(opts)  {memo, yesLabel, noLabel} -> HTMLDivElement
 *   readCurrentValue(el)      Element -> string
 *   restoreMultiselect(sel,memo)  HTMLSelectElement x string -> void
 *   setRowVisible(el, visible)    Element x bool -> void
 *   renderValueCell(container, opts)  Element x {type, memo, ...} -> void  (ADR 0012,multiselect 支持 array memo)
 *   buildChips(container, opts)        Element x {name, currentValues?, placeholder?} -> Element|null  (ADR 0013)
 *   updateNote(fieldId, noteText)     string x string -> void  (小改 A,2026-09-17:动态 note 文案)
 *   validateForm(opts)            {fieldTypeEl, optionsEl, defaultEl} -> {ok, errors}  (小改 B,2026-09-17:submit 前预校验)
 *   bind(opts)                {fieldTypeEl, optionsEl, defaultEl, yesLabel, noLabel, blankLabel} -> void
 *
 * 不依赖 prototype.js,只依赖浏览器 DOM API — 这样可脱离 Magento 跑单测。
 *
 * 关联文档:
 *   docs/architecture/carrier-custom-attribute-form-types.md
 *   docs/architecture/xfe-carrier-custom-attribute-form-switcher-js.md
 *   docs/architecture/decisions/0009-js-extract-and-tests.md
 *
 * @category  XFE
 * @package   XFE_Carrier
 */
(function (root) {
    'use strict';

    /** 拆逗号 → 数组;空白 / 空项过滤 */
    function parseOptionsCsv(s) {
        if (typeof s !== 'string' || s.length === 0) return [];
        return s.split(',')
            .map(function (t) { return t.trim(); })
            .filter(function (t) { return t.length > 0; });
    }

    /** 创建 text input(支持 validate-number) */
    function buildTextInput(opts) {
        var memo = (opts && opts.memo) ? opts.memo : '';
        var withNumberValidator = !!(opts && opts.withNumberValidator);
        var i = document.createElement('input');
        i.type = 'text';
        i.id = 'default_value';
        i.name = 'default_value';
        i.value = memo;
        i.className = withNumberValidator ? 'input-text validate-number' : 'input-text';
        return i;
    }

    /** 创建 select;multiple=true 时 size=5 + 不加 blank 选项 */
    function buildSelect(opts) {
        var memo = (opts && opts.memo) ? opts.memo : '';
        var multiple = !!(opts && opts.multiple);
        var blankLabel = (opts && opts.blankLabel) ? opts.blankLabel : '';
        var s = document.createElement('select');
        s.id = 'default_value';
        s.name = 'default_value';
        s.className = 'select';
        if (multiple) {
            s.multiple = true;
            s.size = 5;
        }
        var rawOptions = (opts && typeof opts.optionsCsv === 'string') ? opts.optionsCsv : '';
        var items = parseOptionsCsv(rawOptions);
        if (!multiple) {
            var blank = document.createElement('option');
            blank.value = '';
            blank.text = blankLabel;
            s.appendChild(blank);
        }
        for (var i = 0; i < items.length; i++) {
            var o = document.createElement('option');
            o.value = items[i];
            o.text = items[i];
            s.appendChild(o);
        }
        return s;
    }

    /** 创建 Yes / No radio 容器(返回 div,id=default_value) */
    function buildBooleanRadios(opts) {
        var memo = (opts && typeof opts.memo === 'string') ? opts.memo : '';
        var yesLabel = (opts && opts.yesLabel) ? opts.yesLabel : 'Yes';
        var noLabel  = (opts && opts.noLabel)  ? opts.noLabel  : 'No';
        // 小改 K(2026-09-18):从 opts.options 结构化数组取 boolean label(优先于 yesLabel/noLabel)
        if (opts && Array.isArray(opts.options) && opts.options.length === 2) {
            for (var _i = 0; _i < opts.options.length; _i++) {
                var _p = opts.options[_i];
                if (!_p || typeof _p.key !== 'string') continue;
                var _lbl = (_p.label != null && String(_p.label) !== '') ? String(_p.label) : _p.key;
                if (_p.key === '1') yesLabel = _lbl;
                else if (_p.key === '0') noLabel = _lbl;
            }
        }
        var wrap = document.createElement('div');
        wrap.id = 'default_value';
        wrap.className = 'default-value-boolean';

        function makeRadio(value, label, checked) {
            var l = document.createElement('label');
            l.htmlFor = 'default_value_' + value;
            l.style.marginRight = '12px';
            var r = document.createElement('input');
            r.type  = 'radio';
            r.name  = 'default_value';
            r.value = value;
            r.id    = 'default_value_' + value;
            if (checked) r.checked = true;
            l.appendChild(r);
            l.appendChild(document.createTextNode(' ' + label));
            return l;
        }

        wrap.appendChild(makeRadio('1', yesLabel, memo === '1'));
        wrap.appendChild(makeRadio('0', noLabel,  memo === '0' || memo === ''));
        return wrap;
    }

    /** 跨 input / select / select-multiple / boolean radio 容器 读值 */
    function readCurrentValue(el) {
        if (!el) return '';
        if (el.type === 'select-multiple') {
            var vals = [];
            for (var i = 0; i < el.options.length; i++) {
                if (el.options[i].selected) vals.push(el.options[i].value);
            }
            return vals.join('|');
        }
        if (el.nodeName === 'DIV') {
            var inputs = el.getElementsByTagName('input');
            for (var j = 0; j < inputs.length; j++) {
                if (inputs[j].type === 'radio' && inputs[j].checked) return inputs[j].value;
            }
            return '';
        }
        return (el.value != null) ? el.value : '';
    }

    /** multiselect 还原已选项(memo 用 '|' 分隔) */
    function restoreMultiselect(sel, memo) {
        if (!sel || !memo) return;
        var parts = memo.split('|');
        for (var i = 0; i < sel.options.length; i++) {
            if (parts.indexOf(sel.options[i].value) !== -1) {
                sel.options[i].selected = true;
            }
        }
    }

    /** 切整行 <tr> 显隐 */
    function setRowVisible(el, visible) {
        if (!el) return;
        var tr = el.parentNode;
        // prototype.js 用 .up('tr'),原生 DOM 用 closest
        while (tr && tr.nodeName !== 'TR') {
            tr = tr.parentNode;
        }
        if (!tr) return;
        if (visible) {
            tr.style.display = '';
        } else {
            tr.style.display = 'none';
        }
    }

    /**
     * 把 "自由标签 chips" 控件渲染到 container(ADR 0013)
     * 容器假设:已含 <input class="xfe-ca-chip-input"> 的 div(strict_editor 自由 multiselect 用)
     * 行为:创建 hidden input(承载 values.join(',')) + 渲染 chip + 挂 Enter/,/Backspace/× 事件
     * 防重复:`__inited` 标记,二次调用直接返回原 container
     *
     * @param {HTMLElement} container  挂载点(已含 <input class="xfe-ca-chip-input">)
     * @param {object}      opts       {name?: string, currentValues?: string[], placeholder?: string}
     * @return {HTMLElement|null}      返回 container;container 缺失返回 null
     */
    function buildChips(container, opts) {
        if (!container) return null;
        var o = opts || {};
        if (container.__inited) return container; // 防御性:二次调用直接返回
        container.__inited = true;

        var values = Array.isArray(o.currentValues) ? o.currentValues.slice() : [];
        var name = o.name || '';
        var placeholder = o.placeholder || '输入后回车';

        // hidden input 承载真实值(逗号分隔)
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = name;
        hidden.value = values.join(',');
        container.appendChild(hidden);

        var input = container.querySelector('.xfe-ca-chip-input');
        if (input && placeholder) input.placeholder = placeholder;

        function render() {
            // 清空旧 chip
            Array.prototype.forEach.call(
                container.querySelectorAll('.xfe-ca-chip'),
                function (el) { el.parentNode.removeChild(el); }
            );
            values.forEach(function (v, idx) {
                var chip = document.createElement('span');
                chip.className = 'xfe-ca-chip';
                chip.innerHTML = '<span></span><span class="xfe-ca-chip-x">×</span>';
                chip.querySelector('span').textContent = v;
                chip.querySelector('.xfe-ca-chip-x').addEventListener('click', function () {
                    values.splice(idx, 1);
                    hidden.value = values.join(',');
                    render();
                });
                container.insertBefore(chip, input);
            });
        }

        if (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    var v = input.value.trim();
                    if (v && values.indexOf(v) === -1) {
                        values.push(v);
                        hidden.value = values.join(',');
                        render();
                    }
                    input.value = '';
                } else if (e.key === 'Backspace' && input.value === '' && values.length) {
                    values.pop();
                    hidden.value = values.join(',');
                    render();
                }
            });
        }

        render();
        return container;
    }

    /**
     * 把指定 type 的 value 控件渲染到 container (ADR 0012)
     *
     * @param {HTMLElement} container  控件挂载点(会被 replaceChild 替换内容)
     * @param {object}      opts
     *   - type:               'text' | 'number' | 'select' | 'multiselect' | 'boolean'
     *   - memo:               string|null      编辑回显 / 默认值(string,multiselect 时支持 array — ADR 0013)
     *   - optionsCsv:         string           逗号分隔的候选项(select/multiselect 用)
     *   - yesLabel / noLabel: string           boolean 用
     *   - blankLabel:         string           select 用(blank 选项文案)
     *   - withNumberValidator:boolean          text 用
     *   - name:               string           重写控件 name(供 strict_editor 等用)
     *   - className:          string           重写控件 className(供 strict_editor 等用)
     * @return {HTMLElement|null}  新生成的控件;type 不识别或 container 缺失返回 null
     */
    function renderValueCell(container, opts) {
        if (!container) return null;
        var o = opts || {};
        var t = o.type;
        var memo = (o.memo != null) ? String(o.memo) : '';
        var newEl = null;

        switch (t) {
            case 'text':
                newEl = buildTextInput({ memo: memo, withNumberValidator: false });
                break;
            case 'number':
                newEl = buildTextInput({ memo: memo, withNumberValidator: true });
                break;
            case 'select':
                newEl = buildSelect({
                    memo: memo,
                    multiple: false,
                    blankLabel: o.blankLabel || '',
                    optionsCsv: o.optionsCsv || ''
                });
                if (memo) newEl.value = memo;
                break;
            case 'multiselect':
                // multiselect memo 支持 string|array(string 用 "|" 分隔,array 原样 join) — ADR 0013
                var memoStr;
                if (Array.isArray(o.memo)) {
                    memoStr = o.memo.join('|');
                } else {
                    memoStr = (o.memo != null) ? String(o.memo) : '';
                }
                newEl = buildSelect({
                    memo: memoStr,
                    multiple: true,
                    blankLabel: o.blankLabel || '',
                    optionsCsv: o.optionsCsv || ''
                });
                restoreMultiselect(newEl, memoStr);
                break;
            case 'boolean':
                newEl = buildBooleanRadios({
                    memo: memo,
                    yesLabel: o.yesLabel || 'Yes',
                    noLabel:  o.noLabel  || 'No'
                });
                break;
            default:
                return null;
        }

        // 可选重写 name(默认 buildXxx 已写 default_value,这里允许覆盖)
        if (newEl && o.name) {
            if (newEl.nodeName === 'DIV') {
                // boolean:容器内的 radio 需要重写 name
                var radios = newEl.getElementsByTagName('input');
                for (var i = 0; i < radios.length; i++) {
                    radios[i].name = o.name;
                }
            } else {
                newEl.name = o.name;
            }
        }
        // 可选重写 className(strict_editor 用 xfe-ca-row-input)
        if (newEl && o.className) {
            newEl.className = o.className;
        }

        // 替换 container 内容
        // boolean 返回 div,需要把 div 内所有节点搬过去再清空 div;
        // 这里采用:container.innerHTML = '',然后 appendChild(newEl)
        container.innerHTML = '';
        container.appendChild(newEl);
        return newEl;
    }

    /**
     * 把指定 fieldId 对应的 note 元素的 textContent 改为 noteText(小改 A,2026-09-17)
     * 容器约定:Magento Varien 渲染的 <p id="FIELDID_note" class="note">
     * 容器不存在 或 noteText 为 null/undefined/empty → no-op(沿用 Form Block 静态文案兜底)
     *
     * @param {string} fieldId   字段 id(不带 _note 后缀)
     * @param {string|null|undefined} noteText  新文案;空值跳过更新
     * @return {HTMLElement|null}  返回被更新的元素;未更新返回 null
     */
    function updateNote(fieldId, noteText) {
        if (noteText === null || noteText === undefined || noteText === '') return null;
        var noteEl = document.getElementById(fieldId + '_note');
        if (!noteEl) return null;
        noteEl.textContent = noteText;
        return noteEl;
    }

    /**
     * submit 前预校验(小改 B,2026-09-17):返回 {ok, errors:[{field, message}]}
     * 校验规则:
     *   1. select 类型 → options_csv 必须有非空白值(否则 Domain 抛异常)
     *   2. boolean 类型 → default_value 必须 ∈ {'0', '1'}(避免 'yes'/'no' 误提交)
     *   3. multiselect 固定模式 → default_value 各项必须 ∈ options(防 stale 数据)
     *
     * @param {Object} opts  {fieldTypeEl, optionsEl, defaultEl}
     * @return {Object}      {ok: bool, errors: [{field, message}]}
     */
    function validateForm(opts) {
        var errors = [];
        var fieldTypeEl = opts && opts.fieldTypeEl;
        var optionsEl   = opts && opts.optionsEl;
        var defaultEl   = opts && opts.defaultEl;
        if (!fieldTypeEl) return { ok: true, errors: errors };

        var t = (fieldTypeEl.value || '').toString();
        var optionsRaw = optionsEl ? (optionsEl.value || '') : '';
        var optionsArr = parseOptionsCsv(optionsRaw);
        var defaultVal = defaultEl ? readCurrentValue(defaultEl) : '';

        // 规则 1:select 必须有候选项
        if (t === 'select') {
            if (optionsArr.length === 0) {
                errors.push({
                    field: 'options_csv',
                    message: 'select 类型必须填写候选项(逗号分隔,至少 1 项)'
                });
            }
        }

        // 规则 2:boolean 必须 0 或 1
        if (t === 'boolean') {
            if (defaultVal !== '0' && defaultVal !== '1') {
                errors.push({
                    field: 'default_value',
                    message: 'boolean 类型的默认值必须是 0 或 1(当前:' + (defaultVal === '' ? '空' : defaultVal) + ')'
                });
            }
        }

        // 规则 3:multiselect 固定模式 → default_value 各项必须 ∈ options
        if (t === 'multiselect' && optionsArr.length > 0) {
            var memos = defaultVal ? defaultVal.split('|') : [];
            for (var i = 0; i < memos.length; i++) {
                var m = memos[i].trim();
                if (m === '') continue;
                if (optionsArr.indexOf(m) === -1) {
                    errors.push({
                        field: 'default_value',
                        message: 'multiselect 默认值 "' + m + '" 不在候选项内'
                    });
                    break; // 只报一个避免刷屏
                }
            }
        }

        return { ok: errors.length === 0, errors: errors };
    }

    /**
     * 在 form 顶部展示错误块 + 高亮错误字段(小改 B 配套)
     * @param {Array} errors  validateForm 返回的 errors 数组
     * @return {HTMLElement|null}  返回错误 <ul> 元素;无错误 → null(清空旧块)
     */
    function showFormErrors(errors) {
        // 清掉旧错误块 + 高亮 class
        var old = document.getElementById('xfe-ca-form-errors');
        if (old && old.parentNode) old.parentNode.removeChild(old);
        var prev = document.querySelectorAll('.xfe-ca-field-error');
        Array.prototype.forEach.call(prev, function (el) { el.classList.remove('xfe-ca-field-error'); });

        if (!errors || errors.length === 0) return null;

        var ul = document.createElement('ul');
        ul.id = 'xfe-ca-form-errors';
        ul.className = 'xfe-ca-form-errors';
        // 小改 F(2026-09-17):inline style 改用 CSS class(类型切换器 phtml 已注入 .xfe-ca-form-errors 规则)
        var head = document.createElement('li');
        head.className = 'head';
        head.textContent = '表单校验失败(' + errors.length + ' 项):';
        ul.appendChild(head);
        errors.forEach(function (err) {
            var li = document.createElement('li');
            // li.style 由 CSS 接管(.xfe-ca-form-errors li 规则)
            li.textContent = '• [' + err.field + '] ' + err.message;
            ul.appendChild(li);
            // 高亮对应字段
            var fieldEl = document.getElementById(err.field);
            if (fieldEl) fieldEl.classList.add('xfe-ca-field-error');
        });

        // 插入到 form 顶部
        var form = document.getElementById('edit_form');
        if (form && form.parentNode) {
            form.parentNode.insertBefore(ul, form);
        } else if (document.body) {
            document.body.insertBefore(ul, document.body.firstChild);
        }
        return ul;
    }


    /**
     * options_csv 实际变化时,如果 default_value 会失效,弹 confirm 让用户选策略(小改 D,2026-09-17)。
     * 与 §2.10 服务端能力配套 — 后端默认 reject,JS 端 UX 增强。
     *
     * @param {object} opts
     * @param {string} opts.oldCsv       旧 options_csv(原始字符串)
     * @param {string} opts.newCsv       新 options_csv(用户刚输入)
     * @param {string} opts.type         select | multiselect | text | number | boolean
     * @param {string|Array} opts.defaultValue  当前 default_value
     * @returns {string} 'none' | 'auto_clean' | 'cancel'
     */
    function promptMigrationStrategy(opts) {
        var oldCsv = (opts && opts.oldCsv) || '';
        var newCsv = (opts && opts.newCsv) || '';
        var type = (opts && opts.type) || '';
        var defaultValue = opts ? opts.defaultValue : null;

        // 1. 只有 select/multiselect 需要检测
        if (type !== 'select' && type !== 'multiselect') {
            return 'none';
        }

        // 2. 比较"有效 options"(去除空白 + 排序后)
        var oldArr = parseOptionsCsv(oldCsv).slice().sort();
        var newArr = parseOptionsCsv(newCsv).slice().sort();
        if (oldArr.length === newArr.length &&
            oldArr.every(function (v, i) { return v === newArr[i]; })) {
            return 'none';   // 没真变化(只是空白格式调整)
        }

        // 3. 检测 default 是否还在新 options 内
        if (_wouldBeIncompatible(type, newArr, defaultValue)) {
            var labels = (typeof window !== 'undefined' && window.XfeCaMigrationLabels)
                || {
                    confirm: '修改候选项(options_csv)会导致现有默认值失效。\n'
                           + '点 [确定] = 自动清洗(只保留合法项,丢弃非法)\n'
                           + '点 [取消] = 恢复候选项(请重新编辑)'
                };
            var ok = false;
            try { ok = window.confirm(labels.confirm); } catch (e) { ok = false; }
            return ok ? 'auto_clean' : 'cancel';
        }

        return 'none';
    }

    /** 检测 default_value 在新 options 下是否还合法 */
    function _wouldBeIncompatible(type, newOptionsArr, defaultValue) {
        if (defaultValue === null || defaultValue === undefined || defaultValue === '') {
            return false;
        }
        var newOpts = newOptionsArr || [];
        if (type === 'select') {
            return newOpts.indexOf(String(defaultValue)) === -1;
        }
        if (type === 'multiselect') {
            // defaultValue 在 strict_editor 是 string,在 Edit Form 可能是 'a|b|c' 或 array
            var parts;
            if (Array.isArray(defaultValue)) {
                parts = defaultValue.map(function (s) { return String(s).trim(); });
            } else {
                parts = String(defaultValue).split(/[,|]/).map(function (s) { return s.trim(); });
            }
            parts = parts.filter(function (s) { return s !== ''; });
            for (var i = 0; i < parts.length; i++) {
                if (newOpts.indexOf(parts[i]) === -1) return true;
            }
            return false;
        }
        return false;
    }

    /** 在 form 内设置/更新 hidden input name="migration_strategy" */
    function _setHiddenMigrationStrategy(value, form) {
        if (!form) return;
        var existing = form.querySelector('input[name="migration_strategy"]');
        if (existing) {
            existing.value = value;
        } else {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'migration_strategy';
            input.value = value;
            form.appendChild(input);
        }
    }

    /* ====== 小改 G(2026-09-17):options_csv 行编辑器(ADR 0022) ====== */

    /**
     * 拆 "key|label" 或 "key" → {key, label}(key 必填,label 缺省 = key)
     * 解析规则:
     *   - 含 "|" 且非首字符:split 第一段为 key,剩余拼接为 label(允许 label 含 "|")
     *   - 不含 "|":整体作为 key,label = key
     *   - 空字符串 / 非字符串:返回 {key:'', label:''}
     */
    function parseOptionToken(s) {
        if (typeof s !== 'string') return { key: '', label: '' };
        var t = s.replace(/^\s+|\s+$/g, ''); // trim 边界空白
        if (t.length === 0) return { key: '', label: '' };
        var pipeIdx = t.indexOf('|');
        if (pipeIdx >= 0) {
            // 分别 trim key / label(允许 value 含 '|' 但不允许两侧空白)
            var keyPart = t.substring(0, pipeIdx).replace(/^\s+|\s+$/g, '');
            var labelPart = t.substring(pipeIdx + 1).replace(/^\s+|\s+$/g, '');
            return { key: keyPart, label: labelPart };
        }
        return { key: t, label: t };
    }

    /**
     * {key, label} → string
     * 压缩规则:label 缺省 / 等于 key → 只输出 key(保持 CSV 简洁)
     */
    function serializeOptionPair(pair) {
        if (!pair || typeof pair.key !== 'string') return '';
        var key = pair.key.replace(/^\s+|\s+$/g, '');
        if (key === '') return '';
        var label = (typeof pair.label === 'string') ? pair.label.replace(/^\s+|\s+$/g, '') : '';
        if (label === '' || label === key) return key;
        return key + '|' + label;
    }

    /**
     * 整段 options_csv → [{key, label}, ...](过滤空 token)
     * 与 parseOptionsCsv 语义一致:逗号分隔,trim 空白,丢弃空项
     */
    function parseOptionsCsvToPairs(s) {
        if (typeof s !== 'string' || s.length === 0) return [];
        return s.split(',')
            .map(function (t) { return t.replace(/^\s+|\s+$/g, ''); })
            .filter(function (t) { return t.length > 0; })
            .map(parseOptionToken);
    }

    /**
     * [{key, label}] → 整段 CSV
     * 过滤空 key 行(允许用户编辑器里有临时空行);其余走 serializeOptionPair
     */
    function serializePairsToCsv(pairs) {
        if (!Array.isArray(pairs)) return '';
        var out = [];
        for (var i = 0; i < pairs.length; i++) {
            var s = serializeOptionPair(pairs[i]);
            if (s.length > 0) out.push(s);
        }
        return out.join(',');
    }

    /**
     * 行编辑器主入口(小改 G,ADR 0022)
     *
     * 行为:
     *   1. 在 optionsEl.parentNode 内追加一个 <div class="xfe-ca-opt-editor"> 容器
     *   2. refresh(type):type=select/multiselect → 显示容器 + 从 optionsEl.value 重建行
     *                     其他 → 隐藏容器(同时恢复 optionsEl 文本框可见)
     *   3. 行变化 → 同步 serializePairsToCsv 到 optionsEl.value + dispatch change 事件
     *      (让现有 promptMigrationStrategy / onOptionsChange 检测触发)
     *
     * 防重复:optionsEl.__xfeCaOptEditorInited 标记,二次调用直接返回缓存
     *
     * @param {object} opts  {optionsEl, fieldTypeEl}
     * @return {object|null} {refresh(type), destroy()} 或 optionsEl 缺失时返回 null
     */
    function mountOptionsEditor(opts) {
        var optionsEl = opts && opts.optionsEl;
        var fieldTypeEl = opts && opts.fieldTypeEl;
        // 小改 J(2026-09-17):文案 i18n — 优先 opts.labels,再读 window.XfeCaEditorLabels,fallback 英文
        var labels = (opts && opts.labels) || (typeof window !== 'undefined' && window.XfeCaEditorLabels) || null;
        function _label(key, fallback) { return (labels && labels[key]) || fallback; }
        if (!optionsEl || !optionsEl.parentNode) return null;

        // 防重复挂载
        if (optionsEl.__xfeCaOptEditorInited) return optionsEl.__xfeCaOptEditorHandle;

        var container = document.createElement('div');
        container.className = 'xfe-ca-opt-editor';
        container.style.display = 'none';
        // 标题 + 行表 + 添加按钮
        container.innerHTML =
            '<div class="xfe-ca-opt-editor-head">' + _label('headTitle', '候选项(key / 显示名)') + '</div>' +
            '<div class="xfe-ca-opt-editor-rows"></div>' +
            '<button type="button" class="xfe-ca-opt-editor-add">' + _label('addBtn', '+ 添加候选项') + '</button>';
        optionsEl.parentNode.appendChild(container);

        var rowsEl = container.querySelector('.xfe-ca-opt-editor-rows');
        var addBtn = container.querySelector('.xfe-ca-opt-editor-add');

        function buildRow(pair, opts) {
            opts = opts || {};
            var readonlyValue = !!opts.readonlyValue;
            var row = document.createElement('div');
            row.className = 'xfe-ca-opt-row';
            if (readonlyValue) row.classList.add('xfe-ca-opt-row-readonly');
            row.innerHTML =
                '<input type="text" class="xfe-ca-opt-key input-text" placeholder="key(如 red)" />' +
                '<input type="text" class="xfe-ca-opt-label input-text" placeholder="显示名(如 红色,留空 = 同 key)" />' +
                '<button type="button" class="xfe-ca-opt-del">删除</button>';
            if (pair) {
                row.querySelector('.xfe-ca-opt-key').value = pair.key || '';
                row.querySelector('.xfe-ca-opt-label').value = pair.label || '';
            }
            // 小改 K(2026-09-18):boolean 时 value 列只读(灰色背景)
            if (readonlyValue) {
                var keyInput = row.querySelector('.xfe-ca-opt-key');
                keyInput.readOnly = true;
                keyInput.style.backgroundColor = '#EEE';
                keyInput.style.cursor = 'not-allowed';
            }
            // 删除
            row.querySelector('.xfe-ca-opt-del').textContent = _label('delBtn', '删除');
            row.querySelector('.xfe-ca-opt-del').addEventListener('click', function () {
                if (row.parentNode) row.parentNode.removeChild(row);
                syncToHidden();
                _refreshDuplicateHints();
            });

            // 小改 I(2026-09-17):HTML5 拖拽排序
            row.draggable = true;
            row.style.cursor = 'move';
            row.addEventListener('dragstart', function (e) {
                if (!e.dataTransfer) return;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', ''); // FF 兼容
                rowsEl.__xfeCaDragRow = row;
                row.classList.add('xfe-ca-opt-dragging');
            });
            row.addEventListener('dragend', function () {
                row.classList.remove('xfe-ca-opt-dragging');
                rowsEl.__xfeCaDragRow = null;
                // 清理所有 drag-over 标记
                var overs = rowsEl.querySelectorAll('.xfe-ca-opt-drag-over');
                for (var i = 0; i < overs.length; i++) overs[i].classList.remove('xfe-ca-opt-drag-over');
                rowsEl.__xfeCaDragTarget = null;
            });
            // key / label 输入 → 同步到隐藏 input
            var keyInput = row.querySelector('.xfe-ca-opt-key');
            var labelInput = row.querySelector('.xfe-ca-opt-label');
            // 记录初始 key,用于"label 还等于旧 key 时跟随 key 变化"(保持 label == key 压缩语义)
            keyInput.__xfeCaOrigKey = (pair && pair.key) || '';
            keyInput.addEventListener('input', function () {
                if (labelInput.value === keyInput.__xfeCaOrigKey) {
                    labelInput.value = keyInput.value;
                }
                keyInput.__xfeCaOrigKey = keyInput.value;
                syncToHidden();
                _refreshDuplicateHints();
            });
            labelInput.addEventListener('input', function () {
                syncToHidden();
                _refreshDuplicateHints();
            });
            return row;
        }

        function collectRows() {
            var list = [];
            var rows = rowsEl.querySelectorAll('.xfe-ca-opt-row');
            for (var i = 0; i < rows.length; i++) {
                var key = rows[i].querySelector('.xfe-ca-opt-key').value;
                var label = rows[i].querySelector('.xfe-ca-opt-label').value;
                list.push({ key: key, label: label });
            }
            return list;
        }

        function syncToHidden() {
            var csv = serializePairsToCsv(collectRows());
            var oldValue = optionsEl.value;
            optionsEl.value = csv;
            // 标记:本次 change 来自行编辑器 → onOptionsChange 应跳过 promptMigrationStrategy
            optionsEl._xfeCaEditorSource = true;
            // 同步记忆值,防止 onOptionsChange 拿 stale _xfeCaLastSeenCsv 误判
            optionsEl._xfeCaLastSeenCsv = csv;
            if (oldValue !== csv) {
                try {
                    var docView = optionsEl.ownerDocument && optionsEl.ownerDocument.defaultView;
                    var Evt = (docView && docView.Event) ? docView.Event : (typeof window !== 'undefined' ? window.Event : null);
                    if (Evt) optionsEl.dispatchEvent(new Evt('change', { bubbles: true }));
                } catch (e) { /* IE 兼容 */ }
            }
        }

        // 小改 H(2026-09-17):key 重复实时检测
        // 检测所有行的 key(忽略空 key 与纯空白),对重复 key 的行加 .xfe-ca-opt-dup class + 提示
        function _refreshDuplicateHints() {
            if (!rowsEl) return;
            var rows = rowsEl.querySelectorAll('.xfe-ca-opt-row');
            var keyToRows = {};
            for (var i = 0; i < rows.length; i++) {
                var keyInput = rows[i].querySelector('.xfe-ca-opt-key');
                if (!keyInput) continue;
                var k = (keyInput.value || '').replace(/^\s+|\s+$/g, '');
                if (k === '') continue;
                if (!keyToRows[k]) keyToRows[k] = [];
                keyToRows[k].push(rows[i]);
            }
            for (var i = 0; i < rows.length; i++) {
                var keyInput = rows[i].querySelector('.xfe-ca-opt-key');
                if (!keyInput) continue;
                var k = (keyInput.value || '').replace(/^\s+|\s+$/g, '');
                var isDup = k !== '' && keyToRows[k] && keyToRows[k].length > 1;
                if (isDup) {
                    keyInput.classList.add('xfe-ca-opt-dup');
                    rows[i].classList.add('xfe-ca-opt-row-dup');
                    if (!rows[i].querySelector('.xfe-ca-opt-dup-hint')) {
                        var hint = document.createElement('span');
                        hint.className = 'xfe-ca-opt-dup-hint';
                        hint.textContent = _label('dupHint', 'key 重复');
                        rows[i].appendChild(hint);
                    }
                } else {
                    keyInput.classList.remove('xfe-ca-opt-dup');
                    rows[i].classList.remove('xfe-ca-opt-row-dup');
                    var old = rows[i].querySelector('.xfe-ca-opt-dup-hint');
                    if (old && old.parentNode) old.parentNode.removeChild(old);
                }
            }
        }

        // 小改 I(2026-09-17):rowsEl 拖拽委托 — 处理 dragover/drop/dragleave
        rowsEl.addEventListener('dragover', function (e) {
            if (!rowsEl.__xfeCaDragRow) return;
            e.preventDefault(); // 允许 drop
            if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
            // 找到鼠标下的行
            var target = e.target;
            while (target && target !== rowsEl && !target.classList.contains('xfe-ca-opt-row')) {
                target = target.parentNode;
            }
            if (!target || target === rowsEl) return;
            // 清理旧标记
            var overs = rowsEl.querySelectorAll('.xfe-ca-opt-drag-over');
            for (var i = 0; i < overs.length; i++) overs[i].classList.remove('xfe-ca-opt-drag-over');
            target.classList.add('xfe-ca-opt-drag-over');
            rowsEl.__xfeCaDragTarget = target;
        });
        rowsEl.addEventListener('dragleave', function (e) {
            // 仅当离开 rowsEl 时清理
            if (e.target === rowsEl) {
                var overs = rowsEl.querySelectorAll('.xfe-ca-opt-drag-over');
                for (var i = 0; i < overs.length; i++) overs[i].classList.remove('xfe-ca-opt-drag-over');
                rowsEl.__xfeCaDragTarget = null;
            }
        });
        rowsEl.addEventListener('drop', function (e) {
            if (!rowsEl.__xfeCaDragRow) return;
            e.preventDefault();
            var dragged = rowsEl.__xfeCaDragRow;
            // 直接从 e.target 找目标行(不依赖 dragover 设置的 __xfeCaDragTarget,降级兼容)
            var target = e.target;
            while (target && target !== rowsEl && target !== document && !target.classList.contains('xfe-ca-opt-row')) {
                target = target.parentNode;
            }
            if (!target || target === dragged || target === rowsEl) return;
            // 计算插入位置:鼠标在 target 的上 1/2 → 插前面;下 1/2 → 插后面
            var rect = target.getBoundingClientRect();
            var before = (e.clientY - rect.top) < (rect.height / 2);
            dragged.parentNode.removeChild(dragged);
            if (before) {
                rowsEl.insertBefore(dragged, target);
            } else {
                if (target.nextSibling) {
                    rowsEl.insertBefore(dragged, target.nextSibling);
                } else {
                    rowsEl.appendChild(dragged);
                }
            }
            target.classList.remove('xfe-ca-opt-drag-over');
            rowsEl.__xfeCaDragTarget = null;
            syncToHidden();
            _refreshDuplicateHints();
        });

        addBtn.addEventListener('click', function () {
            rowsEl.appendChild(buildRow({ key: '', label: '' }));
            // 焦点到新行的 key input,方便连续录入
            var newRow = rowsEl.lastChild;
            if (newRow) {
                var keyInput = newRow.querySelector('.xfe-ca-opt-key');
                if (keyInput) keyInput.focus();
            }
        });

        function refresh(type) {
            var isOptionType = (type === 'select' || type === 'multiselect');
            var isBooleanType = (type === 'boolean');
            if (isOptionType || isBooleanType) {
                container.style.display = '';
                optionsEl.style.display = 'none';
                var pairs = parseOptionsCsvToPairs(optionsEl.value || '');
                rowsEl.innerHTML = '';
                if (pairs.length === 0) {
                    // 小改 K(2026-09-18):boolean 默认填 2 行 {0:否,1:是}(value 只读)
                    if (isBooleanType) {
                        rowsEl.appendChild(buildRow({ key: '0', label: '否' }, { readonlyValue: true }));
                        rowsEl.appendChild(buildRow({ key: '1', label: '是' }, { readonlyValue: true }));
                    } else {
                        rowsEl.appendChild(buildRow({ key: '', label: '' }));
                    }
                } else {
                    for (var i = 0; i < pairs.length; i++) {
                        var rowOpts = isBooleanType ? { readonlyValue: true } : null;
                        rowsEl.appendChild(buildRow(pairs[i], rowOpts));
                    }
                }
                _refreshDuplicateHints();
            } else {
                container.style.display = 'none';
                optionsEl.style.display = '';
            }
        }

        function destroy() {
            if (container.parentNode) container.parentNode.removeChild(container);
            optionsEl.__xfeCaOptEditorInited = false;
            optionsEl.__xfeCaOptEditorHandle = null;
        }

        var handle = { refresh: refresh, destroy: destroy };
        optionsEl.__xfeCaOptEditorInited = true;
        optionsEl.__xfeCaOptEditorHandle = handle;

        // 初始挂载时,按当前 type 决定显隐(由 bind() 后续 switchType 统一触发 refresh,此处不主动)
        return handle;
    }

    /** 主入口:绑定 field_type / options_csv change 事件 */
    function bind(opts) {
        var fieldTypeEl = opts.fieldTypeEl;
        var optionsEl   = opts.optionsEl;
        var defaultEl   = opts.defaultEl;
        var yesLabel    = opts.yesLabel || 'Yes';
        var noLabel     = opts.noLabel || 'No';
        var blankLabel  = opts.blankLabel || '';

        if (!fieldTypeEl || !defaultEl) return;

        // 小改 G(2026-09-17):挂载 options_csv 行编辑器(只挂一次,后续 switchType 反复 refresh)
        var optEditor = optionsEl ? mountOptionsEditor({ optionsEl: optionsEl, fieldTypeEl: fieldTypeEl }) : null;

        function switchType() {
            var t = fieldTypeEl.value;
            var memo = readCurrentValue(defaultEl);

            // 小改 L(2026-09-18):boolean 也保留 options_csv 行 — 容器 append 到 optionsEl 的 <td> 内,setRowVisible(false) 会连容器一起隐藏;refresh() 内部会决定 input vs 容器显隐
            setRowVisible(optionsEl, t === 'select' || t === 'multiselect' || t === 'boolean');

            // 小改 G(2026-09-17):行编辑器显隐同步(挂载后才有 optEditor)
            if (optEditor) optEditor.refresh(t);

            // 小改 A:联动时同步切换 note 文案(从 window.XfeCaFormNotes 读 type-specific 提示)
            var noteMap = (typeof window !== 'undefined' && window.XfeCaFormNotes)
                ? window.XfeCaFormNotes : null;
            if (noteMap) {
                var dvNotes = noteMap['default_value'];
                if (dvNotes && dvNotes[t]) updateNote('default_value', dvNotes[t]);
                var ocNotes = noteMap['options_csv'];
                if (ocNotes && ocNotes[t]) updateNote('options_csv', ocNotes[t]);
            }

            // 用 renderValueCell 渲染到 defaultEl 的父容器,保持 defaultEl 引用指向新控件
            var parent = defaultEl.parentNode;
            defaultEl = renderValueCell(parent, {
                type:        t,
                memo:        memo,
                optionsCsv:  optionsEl ? optionsEl.value : '',
                yesLabel:    yesLabel,
                noLabel:     noLabel,
                blankLabel:  blankLabel
            });
        }

        function onOptionsChange() {
            var t = fieldTypeEl.value;
            if (t !== 'select' && t !== 'multiselect') return;

            // 小改 G(2026-09-17):行编辑器触发的 change → 跳过 promptMigrationStrategy(用户主动改 cell,无歧义)
            // 仅重新渲染 default_value 下拉(让用户看到更新后的候选项)
            if (optionsEl._xfeCaEditorSource) {
                optionsEl._xfeCaEditorSource = false;
                var memo2 = readCurrentValue(defaultEl);
                defaultEl = renderValueCell(defaultEl.parentNode, {
                    type:       t,
                    memo:       memo2,
                    optionsCsv: optionsEl ? optionsEl.value : '',
                    blankLabel: blankLabel
                });
                return;
            }

            // 小改 D(2026-09-17):检测 options_csv 实际变化 + 弹 confirm 让用户选 migration_strategy
            var oldCsv = optionsEl._xfeCaLastSeenCsv || '';
            var newCsv = optionsEl.value || '';
            if (oldCsv !== newCsv) {
                var strategy = promptMigrationStrategy({
                    oldCsv: oldCsv,
                    newCsv: newCsv,
                    type: t,
                    defaultValue: readCurrentValue(defaultEl)
                });
                if (strategy === 'cancel') {
                    optionsEl.value = oldCsv;   // 还原
                    return;
                }
                if (strategy === 'auto_clean') {
                    var form = fieldTypeEl.form || (defaultEl && defaultEl.form);
                    _setHiddenMigrationStrategy('auto_clean', form);
                }
                optionsEl._xfeCaLastSeenCsv = newCsv;
            }

            var memo = readCurrentValue(defaultEl);
            defaultEl = renderValueCell(defaultEl.parentNode, {
                type:        t,
                memo:        memo,
                optionsCsv:  optionsEl ? optionsEl.value : '',
                blankLabel:  blankLabel
            });
        }

        fieldTypeEl.addEventListener('change', switchType);
        if (optionsEl) {
            optionsEl.addEventListener('change', onOptionsChange);
            // 小改 D:初始化记忆初始 csv(用户后续修改后才能触发 onOptionsChange 检测)
            optionsEl._xfeCaLastSeenCsv = optionsEl.value || '';
        }
        // 初始化跑一次,处理编辑回显
        switchType();

        // 小改 B:submit 前预校验
        var form = fieldTypeEl.form || (defaultEl && defaultEl.form);
        if (form) {
            form.addEventListener('submit', function (e) {
                var result = validateForm({
                    fieldTypeEl: fieldTypeEl,
                    optionsEl:   optionsEl,
                    defaultEl:   defaultEl
                });
                if (!result.ok) {
                    if (e && e.preventDefault) e.preventDefault();
                    showFormErrors(result.errors);
                    return false;
                }
                // 校验通过 → 清掉旧错误展示(防御)
                showFormErrors([]);
                return true;
            });
        }
    }

    root.XfeCaTypeSwitcher = {
        parseOptionsCsv:    parseOptionsCsv,
        buildTextInput:     buildTextInput,
        buildSelect:        buildSelect,
        buildBooleanRadios: buildBooleanRadios,
        readCurrentValue:   readCurrentValue,
        restoreMultiselect: restoreMultiselect,
        setRowVisible:      setRowVisible,
        renderValueCell:    renderValueCell,
        buildChips:         buildChips,
        updateNote:         updateNote,
        validateForm:       validateForm,
        promptMigrationStrategy: promptMigrationStrategy,
        parseOptionToken:       parseOptionToken,
        serializeOptionPair:    serializeOptionPair,
        parseOptionsCsvToPairs: parseOptionsCsvToPairs,
        serializePairsToCsv:    serializePairsToCsv,
        mountOptionsEditor:     mountOptionsEditor,
        bind:               bind
    };

    // 小改 N(2026-09-18):自启动 boot — 不依赖 type_switcher.phtml 的 inline script 渲染
    // 根因:某些 Magento 后台 layout 配置下 <reference name="js"> 不会渲染 phtml 块,
    // 导致 bind() 不被调用 → 行编辑器容器从未挂载 → 切 type 无效果。
    // 现在 JS 文件加载完,自动检测三个 form 元素,找到就启动 bind()。
    (function autoBoot() {
        function findByName(name) {
            if (typeof document === 'undefined') return null;
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
            try {
                root.XfeCaTypeSwitcher.bind({
                    fieldTypeEl: fieldTypeEl,
                    optionsEl:   optionsEl,
                    defaultEl:   defaultEl,
                    yesLabel:   (root.XfeCaEditorLabels && root.XfeCaEditorLabels.yesLabel) || '是',
                    noLabel:    (root.XfeCaEditorLabels && root.XfeCaEditorLabels.noLabel)  || '否',
                    blankLabel: (root.XfeCaEditorLabels && root.XfeCaEditorLabels.blankLabel) || '-- 请选择 --'
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
}(typeof window !== 'undefined' ? window : this));
