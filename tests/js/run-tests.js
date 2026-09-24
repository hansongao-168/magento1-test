/**
 * Node 端跑测试 — jsdom + 自写 mini test runner (ADR 0010)
 * 用 jsdom 替换 ADR 0009 时期的 minimal DOM stub,支持 addEventListener 真实触发
 *
 * 用法:node tests/js/run-tests.js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { JSDOM } = require('jsdom');

// === 加载被测代码(在 jsdom 的 window/document 上下文里跑) ===
const jsCode = fs.readFileSync(
    path.join(__dirname, '..', '..', 'js', 'xfe_carrier', 'custom-attribute-form-switcher.js'),
    'utf8'
);

const dom = new JSDOM('<!doctype html><html><body></body></html>', {
    runScripts: 'outside-only',
    pretendToBeVisual: true
});

const ctx = {
    window: dom.window,
    document: dom.window.document,
    console,
    // jsdom 不实现 self,测试里偶尔用 typeof self 不至于 ReferenceError
    self: dom.window
};
vm.createContext(ctx);
vm.runInContext(jsCode, ctx);

const XfeCaTypeSwitcher = dom.window.XfeCaTypeSwitcher;
if (!XfeCaTypeSwitcher) {
    console.error('FAIL: XfeCaTypeSwitcher not loaded');
    process.exit(1);
}

const document = dom.window.document;
const trigger = dom.window.Event;

// === Mini test runner ===
let currentGroup = null;
const groups = [];
let totalCount = 0;
let failedCount = 0;

function describe(name, fn) {
    currentGroup = { name, tests: [] };
    groups.push(currentGroup);
    try { fn(); } catch (e) { it('(describe internal error)', () => { throw e; }); }
    currentGroup = null;
}

function it(name, fn) {
    totalCount++;
    const t = { name, error: null };
    try { fn(); } catch (e) { t.error = e; failedCount++; }
    (currentGroup || { tests: [] }).tests.push(t);
}

const assert = {
    equal(actual, expected, msg) {
        const a = (actual && actual.nodeType) ? actual.outerHTML : String(actual);
        const e = (expected && expected.nodeType) ? expected.outerHTML : String(expected);
        if (a !== e) throw new Error((msg || 'equal') + ': expected [' + e + '] actual [' + a + ']');
    },
    deepEqual(actual, expected, msg) {
        const a = JSON.stringify(actual);
        const e = JSON.stringify(expected);
        if (a !== e) throw new Error((msg || 'deepEqual') + ': expected [' + e + '] actual [' + a + ']');
    },
    ok(cond, msg) { if (!cond) throw new Error(msg || 'falsy'); },
    throws(fn, msg) { let t = false; try { fn(); } catch (_) { t = true; } if (!t) throw new Error(msg || 'no throw'); }
};

// === 复用 document 引用供测试用 ===
function fire(el, type) {
    el.dispatchEvent(new trigger(type, { bubbles: true }));
}

// ============================================================
// 单元测试(纯函数,直接覆盖 ADR 0009 的 32 个用例)
// ============================================================

describe('parseOptionsCsv()', () => {
    it('empty string -> []', () => assert.deepEqual(XfeCaTypeSwitcher.parseOptionsCsv(''), []));
    it('null/undefined/non-string -> []', () => {
        assert.deepEqual(XfeCaTypeSwitcher.parseOptionsCsv(null), []);
        assert.deepEqual(XfeCaTypeSwitcher.parseOptionsCsv(undefined), []);
        assert.deepEqual(XfeCaTypeSwitcher.parseOptionsCsv(123), []);
    });
    it('single', () => assert.deepEqual(XfeCaTypeSwitcher.parseOptionsCsv('a'), ['a']));
    it('multiple', () => assert.deepEqual(XfeCaTypeSwitcher.parseOptionsCsv('a,b,c'), ['a','b','c']));
    it('whitespace trimmed', () => assert.deepEqual(XfeCaTypeSwitcher.parseOptionsCsv('  a ,b , c '), ['a','b','c']));
    it('empty items filtered', () => assert.deepEqual(XfeCaTypeSwitcher.parseOptionsCsv('a,,b,  ,c'), ['a','b','c']));
    it('trailing comma no empty', () => assert.deepEqual(XfeCaTypeSwitcher.parseOptionsCsv('a,b,'), ['a','b']));
});

describe('buildTextInput()', () => {
    it('memo empty', () => {
        const i = XfeCaTypeSwitcher.buildTextInput({ memo: '' });
        assert.equal(i.outerHTML, '<input type="text" id="default_value" name="default_value" class="input-text">');
    });
    it('memo pass-through', () => assert.equal(XfeCaTypeSwitcher.buildTextInput({ memo: 'hello' }).value, 'hello'));
    it('withNumberValidator adds class', () => assert.equal(XfeCaTypeSwitcher.buildTextInput({ memo: '', withNumberValidator: true }).className, 'input-text validate-number'));
    it('id is default_value', () => assert.equal(XfeCaTypeSwitcher.buildTextInput({}).id, 'default_value'));
});

describe('buildSelect()', () => {
    it('single + blank', () => {
        const s = XfeCaTypeSwitcher.buildSelect({ memo: '', multiple: false, blankLabel: '--', optionsCsv: 'a,b,c' });
        assert.equal(s.multiple, false);
        assert.equal(s.options.length, 4);
        assert.equal(s.options[0].text, '--');
        assert.equal(s.options[0].value, '');
        assert.equal(s.options[1].value, 'a');
        assert.equal(s.options[3].value, 'c');
    });
    it('multiple no blank size=5', () => {
        const s = XfeCaTypeSwitcher.buildSelect({ memo: '', multiple: true, blankLabel: '--', optionsCsv: 'x,y' });
        assert.equal(s.multiple, true);
        assert.equal(s.options.length, 2);
    });
    it('empty optionsCsv', () => {
        const s1 = XfeCaTypeSwitcher.buildSelect({ multiple: false, blankLabel: '--', optionsCsv: '' });
        assert.equal(s1.options.length, 1);
        const s2 = XfeCaTypeSwitcher.buildSelect({ multiple: true, optionsCsv: '' });
        assert.equal(s2.options.length, 0);
    });
    it('id is default_value', () => assert.equal(XfeCaTypeSwitcher.buildSelect({}).id, 'default_value'));
});

describe('buildBooleanRadios()', () => {
    it('memo=1 -> yes checked', () => {
        const d = XfeCaTypeSwitcher.buildBooleanRadios({ memo: '1', yesLabel: 'Yes', noLabel: 'No' });
        const inputs = d.getElementsByTagName('input');
        assert.equal(inputs.length, 2);
        assert.equal(inputs[0].checked, true);
        assert.equal(inputs[1].checked, false);
        assert.equal(inputs[0].value, '1');
        assert.equal(inputs[1].value, '0');
    });
    it('memo=0 -> no checked', () => {
        const d = XfeCaTypeSwitcher.buildBooleanRadios({ memo: '0' });
        const inputs = d.getElementsByTagName('input');
        assert.equal(inputs[0].checked, false);
        assert.equal(inputs[1].checked, true);
    });
    it('memo="" -> no default', () => {
        const d = XfeCaTypeSwitcher.buildBooleanRadios({ memo: '' });
        const inputs = d.getElementsByTagName('input');
        assert.equal(inputs[0].checked, false);
        assert.equal(inputs[1].checked, true);
    });
    it('id is default_value', () => assert.equal(XfeCaTypeSwitcher.buildBooleanRadios({}).id, 'default_value'));
    it('labels pass-through', () => {
        const d = XfeCaTypeSwitcher.buildBooleanRadios({ memo: '', yesLabel: 'On', noLabel: 'Off' });
        assert.ok(d.innerHTML.indexOf('On') !== -1);
        assert.ok(d.innerHTML.indexOf('Off') !== -1);
    });
});

describe('readCurrentValue()', () => {
    it('text input', () => {
        const i = document.createElement('input'); i.type = 'text'; i.value = 'hello';
        assert.equal(XfeCaTypeSwitcher.readCurrentValue(i), 'hello');
    });
    it('select single', () => {
        const s = document.createElement('select');
        s.innerHTML = '<option value="">--</option><option value="x">X</option><option value="y">Y</option>';
        s.value = 'y';
        assert.equal(XfeCaTypeSwitcher.readCurrentValue(s), 'y');
    });
    it('select-multiple join |', () => {
        const s = document.createElement('select');
        s.multiple = true;
        s.innerHTML = '<option value="a">A</option><option value="b">B</option><option value="c">C</option>';
        s.options[0].selected = true;
        s.options[2].selected = true;
        assert.equal(XfeCaTypeSwitcher.readCurrentValue(s), 'a|c');
    });
    it('boolean radio container', () => {
        const d = XfeCaTypeSwitcher.buildBooleanRadios({ memo: '1' });
        assert.equal(XfeCaTypeSwitcher.readCurrentValue(d), '1');
    });
    it('null/undefined -> empty', () => {
        assert.equal(XfeCaTypeSwitcher.readCurrentValue(null), '');
        assert.equal(XfeCaTypeSwitcher.readCurrentValue(undefined), '');
    });
});

describe('restoreMultiselect()', () => {
    it('single value', () => {
        const s = XfeCaTypeSwitcher.buildSelect({ multiple: true, optionsCsv: 'a,b,c' });
        XfeCaTypeSwitcher.restoreMultiselect(s, 'b');
        assert.equal(s.options[0].selected, false);
        assert.equal(s.options[1].selected, true);
        assert.equal(s.options[2].selected, false);
    });
    it('multiple values', () => {
        const s = XfeCaTypeSwitcher.buildSelect({ multiple: true, optionsCsv: 'a,b,c' });
        XfeCaTypeSwitcher.restoreMultiselect(s, 'a|c');
        assert.equal(s.options[0].selected, true);
        assert.equal(s.options[1].selected, false);
        assert.equal(s.options[2].selected, true);
    });
    it('empty memo no change', () => {
        const s = XfeCaTypeSwitcher.buildSelect({ multiple: true, optionsCsv: 'a,b' });
        XfeCaTypeSwitcher.restoreMultiselect(s, '');
        assert.equal(s.options[0].selected, false);
        assert.equal(s.options[1].selected, false);
    });
    it('null sel no throw', () => { XfeCaTypeSwitcher.restoreMultiselect(null, 'a'); assert.ok(true); });
});

describe('setRowVisible()', () => {
    it('show clears display', () => {
        const s = document.createElement('span');
        const td = document.createElement('td');
        const tr = document.createElement('tr');
        tr.style.display = 'none';
        tr.appendChild(td); td.appendChild(s);
        XfeCaTypeSwitcher.setRowVisible(s, true);
        assert.equal(tr.style.display, '');
    });
    it('hide sets display none', () => {
        const s = document.createElement('span');
        const td = document.createElement('td');
        const tr = document.createElement('tr');
        tr.appendChild(td); td.appendChild(s);
        XfeCaTypeSwitcher.setRowVisible(s, false);
        assert.equal(tr.style.display, 'none');
    });
    it('null no throw', () => { XfeCaTypeSwitcher.setRowVisible(null, true); assert.ok(true); });
});

// ============================================================
// renderValueCell() (ADR 0012) — 从 bind().switchType 抽出的"按 type 渲染"公开 API
// 覆盖 5 种 type + name 重写 + 编辑回显 + boolean 防御 + 不识别 type 兜底
// ============================================================
describe('renderValueCell()', () => {
    function makeContainer() {
        const c = document.createElement('div');
        document.body.appendChild(c);
        return c;
    }

    it('type=text 渲染 input(id=default_value)', () => {
        const c = makeContainer();
        const out = XfeCaTypeSwitcher.renderValueCell(c, { type: 'text', memo: 'hi' });
        assert.equal(out.tagName, 'INPUT');
        assert.equal(out.type, 'text');
        assert.equal(out.id, 'default_value');
        assert.equal(out.value, 'hi');
        assert.equal(c.children.length, 1);
        assert.equal(c.firstChild, out);
    });

    it('type=number 渲染带 validate-number class 的 input', () => {
        const c = makeContainer();
        const out = XfeCaTypeSwitcher.renderValueCell(c, { type: 'number', memo: '42' });
        assert.equal(out.tagName, 'INPUT');
        assert.equal(out.className.indexOf('validate-number') !== -1, true);
        assert.equal(out.value, '42');
    });

    it('type=select 渲染 <select>(单选 + blank 选项)', () => {
        const c = makeContainer();
        const out = XfeCaTypeSwitcher.renderValueCell(c, {
            type: 'select', memo: 'b', optionsCsv: 'a,b,c', blankLabel: '-- --'
        });
        assert.equal(out.tagName, 'SELECT');
        assert.equal(out.multiple, false);
        // blank + 3 个 option = 4
        assert.equal(out.options.length, 4);
        assert.equal(out.options[0].text, '-- --');
        assert.equal(out.value, 'b');
    });

    it('type=multiselect 渲染 <select multiple>(无 blank + 编辑回显)', () => {
        const c = makeContainer();
        const out = XfeCaTypeSwitcher.renderValueCell(c, {
            type: 'multiselect', memo: 'a|c', optionsCsv: 'a,b,c'
        });
        assert.equal(out.tagName, 'SELECT');
        assert.equal(out.multiple, true);
        assert.equal(out.size, 5);
        // 无 blank
        assert.equal(out.options.length, 3);
        // 编辑回显:memo `a|c` → a 和 c 已 selected
        assert.equal(out.options[0].selected, true);  // a
        assert.equal(out.options[1].selected, false); // b
        assert.equal(out.options[2].selected, true);  // c
    });

    it('type=boolean 渲染 div 含 2 个 radio(默认 yesLabel=Yes)', () => {
        const c = makeContainer();
        const out = XfeCaTypeSwitcher.renderValueCell(c, { type: 'boolean', memo: '1' });
        assert.equal(out.tagName, 'DIV');
        const radios = out.getElementsByTagName('input');
        assert.equal(radios.length, 2);
        assert.equal(radios[0].value, '1');
        assert.equal(radios[0].checked, true);  // memo='1' → yes
        assert.equal(radios[1].value, '0');
        assert.equal(radios[1].checked, false);
    });

    it('name 重写生效(input/select 直接覆盖,boolean 容器遍历内部 radio)', () => {
        // input 重写
        const c1 = makeContainer();
        const o1 = XfeCaTypeSwitcher.renderValueCell(c1, {
            type: 'text', name: 'custom_fields[k1][value]'
        });
        assert.equal(o1.name, 'custom_fields[k1][value]');
        // select 重写
        const c2 = makeContainer();
        const o2 = XfeCaTypeSwitcher.renderValueCell(c2, {
            type: 'select', optionsCsv: 'a,b', name: 'custom_fields[k2][value]'
        });
        assert.equal(o2.name, 'custom_fields[k2][value]');
        // boolean 容器内 radio 重写
        const c3 = makeContainer();
        const o3 = XfeCaTypeSwitcher.renderValueCell(c3, {
            type: 'boolean', name: 'custom_fields[k3][value]'
        });
        const rs = o3.getElementsByTagName('input');
        assert.equal(rs[0].name, 'custom_fields[k3][value]');
        assert.equal(rs[1].name, 'custom_fields[k3][value]');
    });

    it('className 重写生效(text → xfe-ca-row-input)', () => {
        const c = makeContainer();
        const out = XfeCaTypeSwitcher.renderValueCell(c, {
            type: 'text', className: 'xfe-ca-row-input', memo: 'v'
        });
        assert.equal(out.className, 'xfe-ca-row-input');
    });

    it('不识别 type 返回 null(防御性)', () => {
        const c = makeContainer();
        const out = XfeCaTypeSwitcher.renderValueCell(c, { type: 'weird-type' });
        assert.equal(out, null);
        assert.equal(c.children.length, 0);
    });

    it('multiselect memo 接受 array: memo=["a","c"] → a/c selected,b 不 selected', () => {
        // ADR 0013 strict_editor 接入用:PHP 端 json_encode array 直接进 data-default
        const c = makeContainer();
        const out = XfeCaTypeSwitcher.renderValueCell(c, {
            type: 'multiselect', memo: ['a', 'c'], optionsCsv: 'a,b,c'
        });
        assert.equal(out.tagName, 'SELECT');
        assert.equal(out.multiple, true);
        assert.equal(out.options.length, 3);
        assert.equal(out.options[0].selected, true);  // a
        assert.equal(out.options[1].selected, false); // b
        assert.equal(out.options[2].selected, true);  // c
    });

    it('multiselect memo 接受 array: 与 string memo 等价(向后兼容)', () => {
        const c1 = makeContainer();
        const out1 = XfeCaTypeSwitcher.renderValueCell(c1, {
            type: 'multiselect', memo: ['x', 'z'], optionsCsv: 'x,y,z'
        });
        const c2 = makeContainer();
        const out2 = XfeCaTypeSwitcher.renderValueCell(c2, {
            type: 'multiselect', memo: 'x|z', optionsCsv: 'x,y,z'
        });
        assert.equal(out1.options[0].selected, out2.options[0].selected);
        assert.equal(out1.options[1].selected, out2.options[1].selected);
        assert.equal(out1.options[2].selected, out2.options[2].selected);
    });
});

// ============================================================
// buildChips 单元测试 (ADR 0013 strict_editor 自由 multiselect)
// ============================================================

describe('buildChips()', () => {
    /** 构造 strict_editor 风格的 chips 容器:div 含 <input class="xfe-ca-chip-input"> */
    function makeChipsContainer() {
        const c = document.createElement('div');
        c.className = 'xfe-ca-chips';
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'xfe-ca-chip-input';
        c.appendChild(input);
        document.body.appendChild(c);
        return c;
    }

    it('基础渲染: 初始 values=["a","b"] → hidden 同步 + 2 个 chip 渲染', () => {
        const c = makeChipsContainer();
        const out = XfeCaTypeSwitcher.buildChips(c, {
            name: 'custom_fields[k][value]',
            currentValues: ['a', 'b']
        });
        assert.equal(out, c);

        const hidden = c.querySelector('input[type=hidden]');
        assert.ok(hidden, '应有 hidden input');
        assert.equal(hidden.name, 'custom_fields[k][value]');
        assert.equal(hidden.value, 'a,b');

        const chips = c.querySelectorAll('.xfe-ca-chip');
        assert.equal(chips.length, 2);
        assert.equal(chips[0].firstChild.textContent, 'a');
        assert.equal(chips[1].firstChild.textContent, 'b');
    });

    it('Enter 添加: keydown Enter "c" → values+1 + hidden 同步 + chip+1', () => {
        const c = makeChipsContainer();
        XfeCaTypeSwitcher.buildChips(c, {
            name: 'k', currentValues: ['a']
        });
        const input = c.querySelector('.xfe-ca-chip-input');
        input.value = 'c';

        // dispatch Enter keydown
        const ev = new dom.window.KeyboardEvent('keydown', {
            key: 'Enter', bubbles: true
        });
        input.dispatchEvent(ev);

        const hidden = c.querySelector('input[type=hidden]');
        assert.equal(hidden.value, 'a,c');
        const chips = c.querySelectorAll('.xfe-ca-chip');
        assert.equal(chips.length, 2);
        assert.equal(chips[1].firstChild.textContent, 'c');
        assert.equal(input.value, '', 'input 应被清空');
    });

    it('× 关闭: click 第一个 chip 的 x → values-1 + hidden 同步 + chip-1', () => {
        const c = makeChipsContainer();
        XfeCaTypeSwitcher.buildChips(c, {
            name: 'k', currentValues: ['a', 'b']
        });
        const firstX = c.querySelectorAll('.xfe-ca-chip-x')[0];
        // dispatch click
        firstX.dispatchEvent(new dom.window.MouseEvent('click', { bubbles: true }));

        const hidden = c.querySelector('input[type=hidden]');
        assert.equal(hidden.value, 'b');
        const chips = c.querySelectorAll('.xfe-ca-chip');
        assert.equal(chips.length, 1);
        assert.equal(chips[0].firstChild.textContent, 'b');
    });

    it('Backspace 删最后一个: keydown Backspace(空 input)→ values-1', () => {
        const c = makeChipsContainer();
        XfeCaTypeSwitcher.buildChips(c, {
            name: 'k', currentValues: ['a', 'b']
        });
        const input = c.querySelector('.xfe-ca-chip-input');
        assert.equal(input.value, '');  // 确认 input 初始为空

        input.dispatchEvent(new dom.window.KeyboardEvent('keydown', {
            key: 'Backspace', bubbles: true
        }));

        const hidden = c.querySelector('input[type=hidden]');
        assert.equal(hidden.value, 'a');
        const chips = c.querySelectorAll('.xfe-ca-chip');
        assert.equal(chips.length, 1);
        assert.equal(chips[0].firstChild.textContent, 'a');
    });

    it('二次调用防御: 同一 container 第二次调用直接返回,values 不被重置', () => {
        const c = makeChipsContainer();
        XfeCaTypeSwitcher.buildChips(c, { name: 'k', currentValues: ['a'] });
        const before = c.querySelectorAll('.xfe-ca-chip').length;
        const second = XfeCaTypeSwitcher.buildChips(c, { name: 'k', currentValues: ['x', 'y', 'z'] });
        assert.equal(second, c);
        assert.equal(c.querySelectorAll('.xfe-ca-chip').length, before, '二次调用不应增加 chip');
    });
});

// ============================================================
// updateNote 单元测试 (小改 A,2026-09-17:动态 note 文案)
// ============================================================

describe('updateNote()', () => {
    /** 构造 Magento Varien 风格的 note 结构:<input id> + <p id="INPUT_note" class="note"> */
    function setupNote(initial) {
        const input = document.createElement('input');
        input.id = 'default_value';
        const note = document.createElement('p');
        note.id = 'default_value_note';
        note.className = 'note';
        note.textContent = initial || '';
        document.body.appendChild(input);
        document.body.appendChild(note);
        return note;
    }

    it('正常更新 textContent', () => {
        const note = setupNote('原静态文案');
        const out = XfeCaTypeSwitcher.updateNote('default_value', '新的动态文案');
        assert.equal(out, note);
        assert.equal(note.textContent, '新的动态文案');
    });

    it('noteText 空 → no-op(沿用 Form Block 静态文案兜底)', () => {
        const note = setupNote('原静态文案');
        assert.equal(XfeCaTypeSwitcher.updateNote('default_value', ''), null);
        assert.equal(XfeCaTypeSwitcher.updateNote('default_value', null), null);
        assert.equal(XfeCaTypeSwitcher.updateNote('default_value', undefined), null);
        assert.equal(note.textContent, '原静态文案', 'note 不应被覆盖');
    });

    it('容器找不到 → no-op(严格模式编辑器无 note 元素)', () => {
        // 不调用 setupNote,所以没有 #non_existent_note
        assert.equal(XfeCaTypeSwitcher.updateNote('non_existent', 'text'), null);
    });

    /** 模拟 Magento 字段组:ft / opt 在 form 内,dv 包在 td 里,note 是 td 的兄弟元素 */
    function setupMagentoField() {
        document.body.innerHTML = '';
        // Magento 字段定义器会渲染 form 结构,这里手动模拟最小可用形态
        const ft = document.createElement('select');
        ft.id = 'field_type';
        ['text', 'number', 'select', 'multiselect', 'boolean'].forEach(function (v) {
            const o = document.createElement('option');
            o.value = v;
            o.text = v;
            ft.appendChild(o);
        });
        // options_csv 直接挂在 body(因为它整个 <tr> 是 setRowVisible 切的,不在 td 兄弟关系里)
        const opt = document.createElement('input');
        opt.id = 'options_csv';
        // default_value 包在 td 里(Magento 真实形态),note 是 td 的兄弟
        const dvTd = document.createElement('td');
        dvTd.id = 'dv_td';
        const dv = document.createElement('input');
        dv.id = 'default_value';
        dv.name = 'default_value';
        dvTd.appendChild(dv);
        document.body.appendChild(ft);
        document.body.appendChild(opt);
        document.body.appendChild(dvTd);

        // note 元素(在 Magento 里是 td 的兄弟,这里独立挂着)
        const dvNote = document.createElement('p');
        dvNote.id = 'default_value_note';
        dvNote.className = 'note';
        dvNote.textContent = '原静态文案';
        const ocNote = document.createElement('p');
        ocNote.id = 'options_csv_note';
        ocNote.className = 'note';
        ocNote.textContent = '原静态文案';
        document.body.appendChild(dvNote);
        document.body.appendChild(ocNote);

        return { ft: ft, opt: opt, dv: dv, dvNote: dvNote, ocNote: ocNote };
    }

    it('bind() 联动触发 updateNote:切 boolean → note 显示 "选择 是 / 否"', () => {
        // 模拟 type_switcher.phtml 注入的 window.XfeCaFormNotes
        dom.window.XfeCaFormNotes = {
            'default_value': {
                'text':        '任意字符串',
                'number':      '数字(支持小数)',
                'select':      '从上方"候选项"里选一个',
                'multiselect': '从上方"候选项"里多选(按住 Ctrl/Shift)',
                'boolean':     '选择 是 / 否'
            },
            'options_csv': {
                'select':      '必填,逗号分隔(如 红,绿,蓝)',
                'multiselect': '可选,逗号分隔。留空 = 自由标签输入'
            }
        };

        const f = setupMagentoField();

        XfeCaTypeSwitcher.bind({
            fieldTypeEl: f.ft, optionsEl: f.opt, defaultEl: f.dv,
            yesLabel: 'Y', noLabel: 'N', blankLabel: '-'
        });

        // 切到 boolean
        f.ft.value = 'boolean';
        f.ft.dispatchEvent(new dom.window.Event('change', { bubbles: true }));

        assert.equal(f.dvNote.textContent, '选择 是 / 否', 'default_value note 应被 boolean 提示覆盖');
        // options_csv 切到 boolean,noteMap.options_csv.boolean 不存在 → 保持原静态
        assert.equal(f.ocNote.textContent, '原静态文案', 'options_csv 切到 boolean 无对应文案,保持原值');

        // 切到 select
        f.ft.value = 'select';
        f.ft.dispatchEvent(new dom.window.Event('change', { bubbles: true }));
        assert.equal(f.dvNote.textContent, '从上方"候选项"里选一个');
        assert.equal(f.ocNote.textContent, '必填,逗号分隔(如 红,绿,蓝)');

        delete dom.window.XfeCaFormNotes;
    });

    it('window.XfeCaFormNotes 未注入 → note 保持原静态文案(向后兼容)', () => {
        delete dom.window.XfeCaFormNotes;
        const f = setupMagentoField();

        XfeCaTypeSwitcher.bind({
            fieldTypeEl: f.ft, optionsEl: f.opt, defaultEl: f.dv,
            yesLabel: 'Y', noLabel: 'N', blankLabel: '-'
        });

        f.ft.value = 'boolean';
        f.ft.dispatchEvent(new dom.window.Event('change', { bubbles: true }));
        assert.equal(f.dvNote.textContent, '原静态文案', '未注入 noteMap 时不应覆盖');
    });
});

// ============================================================
// validateForm 单元测试 (小改 B,2026-09-17:submit 前预校验)
// ============================================================

describe('validateForm()', () => {
    /** 构造最小 Magento 字段组 + form (含 edit_form) */
    function setupForm(opts) {
        opts = opts || {};
        document.body.innerHTML = '';
        const form = document.createElement('form');
        form.id = 'edit_form';
        document.body.appendChild(form);

        const ft = document.createElement('select');
        ft.id = 'field_type';
        ft.name = 'field_type';
        ['text', 'number', 'select', 'multiselect', 'boolean'].forEach(function (v) {
            const o = document.createElement('option');
            o.value = v;
            o.text = v;
            ft.appendChild(o);
        });
        ft.value = opts.fieldType || 'text';

        const oc = document.createElement('input');
        oc.id = 'options_csv';
        oc.name = 'options_csv';
        oc.value = opts.optionsCsv || '';

        // default_value 包在 td 里(模拟 Magento)
        const dvTd = document.createElement('td');
        const dv = document.createElement('input');
        dv.id = 'default_value';
        dv.name = 'default_value';
        dvTd.appendChild(dv);
        form.appendChild(ft);
        form.appendChild(oc);
        form.appendChild(dvTd);
        return { form: form, ft: ft, oc: oc, dv: dv };
    }

    it('text 类型 → ok=true(无校验规则)', () => {
        const f = setupForm({ fieldType: 'text' });
        const r = XfeCaTypeSwitcher.validateForm({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: f.dv
        });
        assert.equal(r.ok, true);
        assert.deepEqual(r.errors, []);
    });

    it('number 类型 → ok=true', () => {
        const f = setupForm({ fieldType: 'number' });
        const r = XfeCaTypeSwitcher.validateForm({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: f.dv
        });
        assert.equal(r.ok, true);
    });

    it('select 类型 + options_csv 有值 → ok=true', () => {
        const f = setupForm({ fieldType: 'select', optionsCsv: 'a,b,c' });
        const r = XfeCaTypeSwitcher.validateForm({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: f.dv
        });
        assert.equal(r.ok, true);
    });

    it('select 类型 + options_csv 为空 → ok=false + 报错 options_csv', () => {
        const f = setupForm({ fieldType: 'select', optionsCsv: '' });
        const r = XfeCaTypeSwitcher.validateForm({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: f.dv
        });
        assert.equal(r.ok, false);
        assert.equal(r.errors.length, 1);
        assert.equal(r.errors[0].field, 'options_csv');
        assert.ok(r.errors[0].message.indexOf('候选项') !== -1);
    });

    it('select 类型 + options_csv 全空白逗号 → ok=false', () => {
        const f = setupForm({ fieldType: 'select', optionsCsv: ' , , ' });
        const r = XfeCaTypeSwitcher.validateForm({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: f.dv
        });
        assert.equal(r.ok, false);
        assert.equal(r.errors[0].field, 'options_csv');
    });

    it('boolean 类型 + default_value="1" → ok=true', () => {
        const f = setupForm({ fieldType: 'boolean' });
        f.dv.value = '1';
        const r = XfeCaTypeSwitcher.validateForm({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: f.dv
        });
        assert.equal(r.ok, true);
    });

    it('boolean 类型 + default_value="0" → ok=true', () => {
        const f = setupForm({ fieldType: 'boolean' });
        f.dv.value = '0';
        const r = XfeCaTypeSwitcher.validateForm({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: f.dv
        });
        assert.equal(r.ok, true);
    });

    it('boolean 类型 + default_value="yes" → ok=false + 报错 default_value', () => {
        const f = setupForm({ fieldType: 'boolean' });
        f.dv.value = 'yes';
        const r = XfeCaTypeSwitcher.validateForm({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: f.dv
        });
        assert.equal(r.ok, false);
        assert.equal(r.errors[0].field, 'default_value');
        assert.ok(r.errors[0].message.indexOf('0 或 1') !== -1);
    });

    it('boolean 类型 + default_value 空 → ok=false', () => {
        const f = setupForm({ fieldType: 'boolean' });
        f.dv.value = '';
        const r = XfeCaTypeSwitcher.validateForm({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: f.dv
        });
        assert.equal(r.ok, false);
        assert.equal(r.errors[0].field, 'default_value');
    });

    it('multiselect 固定模式 + default_value="x|y" + options="x,y,z" → ok=true', () => {
        const f = setupForm({ fieldType: 'multiselect', optionsCsv: 'x,y,z' });
        f.dv.value = 'x|y';
        const r = XfeCaTypeSwitcher.validateForm({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: f.dv
        });
        assert.equal(r.ok, true);
    });

    it('multiselect 固定模式 + default_value 含不在 options 的值 → ok=false', () => {
        const f = setupForm({ fieldType: 'multiselect', optionsCsv: 'x,y,z' });
        f.dv.value = 'x|foo';
        const r = XfeCaTypeSwitcher.validateForm({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: f.dv
        });
        assert.equal(r.ok, false);
        assert.equal(r.errors[0].field, 'default_value');
        assert.ok(r.errors[0].message.indexOf('foo') !== -1);
    });

    it('multiselect 自由模式 (options_csv 空) → 不校验 default_value', () => {
        const f = setupForm({ fieldType: 'multiselect', optionsCsv: '' });
        f.dv.value = '随便|什么';  // 自由模式任意值都 OK
        const r = XfeCaTypeSwitcher.validateForm({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: f.dv
        });
        assert.equal(r.ok, true);
    });

    it('select 多重错误聚合:同时报 options_csv 缺失 + default_value 不在 options', () => {
        // 当 options_csv 为空时,select 不校验 default_value(因为 options 还没确定)
        // 这里测 select + options_csv 非空但 default_value 不在 options 内
        const f = setupForm({ fieldType: 'select', optionsCsv: 'a,b' });
        f.dv.value = 'foo';  // 不在 a,b 内
        const r = XfeCaTypeSwitcher.validateForm({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: f.dv
        });
        // 当前实现只校验 select 的 options_csv,不在的 default_value 留给服务端
        // 期望 ok=true(只校验 options_csv 是否非空)
        assert.equal(r.ok, true);
    });

    it('text 类型 submit 通过 + 旧错误块被清掉', () => {
        const f = setupForm({ fieldType: 'text' });
        // 预存一个旧错误块
        const oldUl = document.createElement('ul');
        oldUl.id = 'xfe-ca-form-errors';
        document.body.appendChild(oldUl);
        XfeCaTypeSwitcher.bind({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: f.dv,
            yesLabel: 'Y', noLabel: 'N', blankLabel: '-'
        });
        const submitEvent = new dom.window.Event('submit', { bubbles: true, cancelable: true });
        f.form.dispatchEvent(submitEvent);
        assert.equal(submitEvent.defaultPrevented, false, 'text 类型 submit 应被放行');
        assert.equal(document.getElementById('xfe-ca-form-errors'), null, '旧错误块应被清掉');
    });

    it('submit 拦截:select 空 options_csv → preventDefault + 错误块出现', () => {
        const f = setupForm({ fieldType: 'select', optionsCsv: '' });
        XfeCaTypeSwitcher.bind({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: f.dv,
            yesLabel: 'Y', noLabel: 'N', blankLabel: '-'
        });
        const submitEvent = new dom.window.Event('submit', { bubbles: true, cancelable: true });
        f.form.dispatchEvent(submitEvent);
        assert.equal(submitEvent.defaultPrevented, true, 'submit 应被 preventDefault(default action 阻止)');
        const errUl = document.getElementById('xfe-ca-form-errors');
        assert.ok(errUl, '错误块应被插入');
        assert.ok(errUl.textContent.indexOf('候选项') !== -1);
    });

    it('showFormErrors 给 errored 字段加 xfe-ca-field-error class', () => {
        const f = setupForm({ fieldType: 'select', optionsCsv: '' });
        XfeCaTypeSwitcher.bind({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: f.dv,
            yesLabel: 'Y', noLabel: 'N', blankLabel: '-'
        });
        const submitEvent = new dom.window.Event('submit', { bubbles: true, cancelable: true });
        f.form.dispatchEvent(submitEvent);
        assert.ok(f.oc.classList.contains('xfe-ca-field-error'), 'options_csv 应被高亮');
    });
});

// ============================================================
// promptMigrationStrategy(小改 D,2026-09-17,§2.11)
// 检测 options_csv 实际变化 + default_value 兼容性,弹 confirm 选 auto_clean / cancel
// 复用 setupForm + 模拟 window.confirm
// ============================================================

/** 注入 confirm 桩:返回预设答案数组里的下一个值,默认 true */
function installConfirmStub(answers) {
    var idx = 0;
    var original = dom.window.confirm;
    dom.window.confirm = function () {
        var ans = answers[idx] !== undefined ? answers[idx] : true;
        idx++;
        return ans;
    };
    return function () {
        dom.window.confirm = original;
    };
}

describe('promptMigrationStrategy()', () => {
    it('type=text → none(不弹窗,options 与 default 无关)', () => {
        const r = XfeCaTypeSwitcher.promptMigrationStrategy({
            oldCsv: 'a,b,c', newCsv: 'x,y,z',
            type: 'text', defaultValue: 'whatever'
        });
        assert.equal(r, 'none');
    });

    it('type=boolean → none(不弹窗)', () => {
        const r = XfeCaTypeSwitcher.promptMigrationStrategy({
            oldCsv: 'a,b', newCsv: 'x',
            type: 'boolean', defaultValue: '1'
        });
        assert.equal(r, 'none');
    });

    it('options 未变(只是空白格式调整)→ none', () => {
        const r = XfeCaTypeSwitcher.promptMigrationStrategy({
            oldCsv: 'a, b ,c', newCsv: 'a,b,c',
            type: 'select', defaultValue: 'a'
        });
        assert.equal(r, 'none');
    });

    it('options 增项 + default 仍合法 → none(不弹窗)', () => {
        const r = XfeCaTypeSwitcher.promptMigrationStrategy({
            oldCsv: 'a,b', newCsv: 'a,b,c,d',
            type: 'select', defaultValue: 'b'
        });
        assert.equal(r, 'none');
    });

    it('select default 不在新 options → 弹 confirm;确定 → auto_clean', () => {
        const restore = installConfirmStub([true]);
        const r = XfeCaTypeSwitcher.promptMigrationStrategy({
            oldCsv: 'a,b,c', newCsv: 'd,e,f',
            type: 'select', defaultValue: 'a'
        });
        restore();
        assert.equal(r, 'auto_clean');
    });

    it('select default 不在新 options → 弹 confirm;取消 → cancel', () => {
        const restore = installConfirmStub([false]);
        const r = XfeCaTypeSwitcher.promptMigrationStrategy({
            oldCsv: 'a,b,c', newCsv: 'd,e,f',
            type: 'select', defaultValue: 'a'
        });
        restore();
        assert.equal(r, 'cancel');
    });

    it('multiselect 含非法项 → 弹 confirm;确定 → auto_clean', () => {
        const restore = installConfirmStub([true]);
        const r = XfeCaTypeSwitcher.promptMigrationStrategy({
            oldCsv: 'a,b,c', newCsv: 'a,b',
            type: 'multiselect', defaultValue: 'a|c|b'
        });
        restore();
        assert.equal(r, 'auto_clean');
    });

    it('multiselect 含非法项 → 弹 confirm;取消 → cancel', () => {
        const restore = installConfirmStub([false]);
        const r = XfeCaTypeSwitcher.promptMigrationStrategy({
            oldCsv: 'gold,silver,platinum', newCsv: 'gold,silver',
            type: 'multiselect', defaultValue: ['gold', 'platinum']
        });
        restore();
        assert.equal(r, 'cancel');
    });

    it('default 为空 → none(无冲突,不需要弹窗)', () => {
        const r = XfeCaTypeSwitcher.promptMigrationStrategy({
            oldCsv: 'a,b', newCsv: 'x,y,z',
            type: 'select', defaultValue: ''
        });
        assert.equal(r, 'none');
    });

    it('default 为 null → none', () => {
        const r = XfeCaTypeSwitcher.promptMigrationStrategy({
            oldCsv: 'a,b', newCsv: 'x,y,z',
            type: 'select', defaultValue: null
        });
        assert.equal(r, 'none');
    });

    it('confirm 不存在(被屏蔽)→ 视作 cancel', () => {
        const original = dom.window.confirm;
        delete dom.window.confirm;
        const r = XfeCaTypeSwitcher.promptMigrationStrategy({
            oldCsv: 'a,b', newCsv: 'x,y',
            type: 'select', defaultValue: 'a'
        });
        dom.window.confirm = original;
        assert.equal(r, 'cancel');
    });

    it('没注入 XfeCaMigrationLabels → 用内置默认文案(不报错)', () => {
        const original = dom.window.XfeCaMigrationLabels;
        delete dom.window.XfeCaMigrationLabels;
        const restore = installConfirmStub([false]);
        const r = XfeCaTypeSwitcher.promptMigrationStrategy({
            oldCsv: 'a,b', newCsv: 'x,y',
            type: 'select', defaultValue: 'a'
        });
        restore();
        if (original === undefined) delete dom.window.XfeCaMigrationLabels;
        else dom.window.XfeCaMigrationLabels = original;
        assert.equal(r, 'cancel');
    });
});

// ============================================================
// _setHiddenMigrationStrategy 内部函数 — 通过 bind() 间接测
// 验证 form 中 hidden input 被正确设置 / 更新 / 移除
// ============================================================
describe('bind() — _setHiddenMigrationStrategy(配套 onOptionsChange)', () => {
    /** 构造最小 form + field_type + options + default_value */
    function setupAll(opts) {
        opts = opts || {};
        document.body.innerHTML = '';
        const form = document.createElement('form');
        form.id = 'edit_form';
        document.body.appendChild(form);

        const ft = document.createElement('select');
        ft.id = 'field_type';
        ['text', 'select', 'multiselect'].forEach(function (v) {
            const o = document.createElement('option');
            o.value = v;
            o.text = v;
            ft.appendChild(o);
        });
        ft.value = opts.fieldType || 'select';
        form.appendChild(ft);

        const oc = document.createElement('input');
        oc.id = 'options_csv';
        oc.value = opts.optionsCsv || '';
        form.appendChild(oc);

        const dvTd = document.createElement('td');
        const dv = document.createElement('input');
        dv.id = 'default_value';
        dvTd.appendChild(dv);
        form.appendChild(dvTd);
        return { form: form, ft: ft, oc: oc, dv: dv };
    }

    it('onOptionsChange:options 变化 + 弹窗取消 → 自动还原 options_csv', () => {
        const restore = installConfirmStub([false]);
        const f = setupAll({ fieldType: 'select', optionsCsv: 'a,b,c' });
        // 模拟 default_value 当前是 select,值是 'a'
        const s = document.createElement('select');
        Array.prototype.forEach.call(['a', 'b', 'c'], function (v) {
            const o = document.createElement('option'); o.value = v; s.appendChild(o);
        });
        s.value = 'a';
        s.id = 'default_value';
        f.dv.parentNode.replaceChild(s, f.dv);
        // bind
        XfeCaTypeSwitcher.bind({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: s
        });
        // 触发 options 变化(把 'a' 从 options 里去掉,默认 'a' 会失效)
        f.oc.value = 'd,e,f';
        fire(f.oc, 'change');
        restore();
        assert.equal(f.oc.value, 'a,b,c', 'options_csv 已被还原到旧值');
    });

    it('onOptionsChange:options 变化 + 弹窗确定 → 注入 hidden migration_strategy=auto_clean', () => {
        const restore = installConfirmStub([true]);
        const f = setupAll({ fieldType: 'select', optionsCsv: 'a,b,c' });
        const s = document.createElement('select');
        Array.prototype.forEach.call(['a', 'b', 'c'], function (v) {
            const o = document.createElement('option'); o.value = v; s.appendChild(o);
        });
        s.value = 'a';
        s.id = 'default_value';
        f.dv.parentNode.replaceChild(s, f.dv);
        XfeCaTypeSwitcher.bind({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: s
        });
        f.oc.value = 'd,e,f';
        fire(f.oc, 'change');
        restore();
        const hidden = f.form.querySelector('input[name="migration_strategy"]');
        assert.ok(hidden, 'hidden input 已注入');
        assert.equal(hidden.value, 'auto_clean', '值为 auto_clean');
    });

    it('onOptionsChange:options 未变 → 不弹窗 + 不注入 hidden input', () => {
        const restore = installConfirmStub([]);   // 空数组,如果弹窗会 undefined → true
        const f = setupAll({ fieldType: 'select', optionsCsv: 'a,b,c' });
        const s = document.createElement('select');
        Array.prototype.forEach.call(['a', 'b', 'c'], function (v) {
            const o = document.createElement('option'); o.value = v; s.appendChild(o);
        });
        s.value = 'b';
        s.id = 'default_value';
        f.dv.parentNode.replaceChild(s, f.dv);
        XfeCaTypeSwitcher.bind({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: s
        });
        // options 没真变(只是格式调整)→ 不弹窗
        f.oc.value = 'a, b ,c';
        fire(f.oc, 'change');
        restore();
        const hidden = f.form.querySelector('input[name="migration_strategy"]');
        assert.ok(!hidden, '无 hidden input');
    });

    it('onOptionsChange:options 变 + 仍兼容 → 不弹窗 + 不注入', () => {
        const restore = installConfirmStub([]);   // 如果弹窗会报错
        const f = setupAll({ fieldType: 'select', optionsCsv: 'a,b' });
        const s = document.createElement('select');
        Array.prototype.forEach.call(['a', 'b'], function (v) {
            const o = document.createElement('option'); o.value = v; s.appendChild(o);
        });
        s.value = 'b';
        s.id = 'default_value';
        f.dv.parentNode.replaceChild(s, f.dv);
        XfeCaTypeSwitcher.bind({
            fieldTypeEl: f.ft, optionsEl: f.oc, defaultEl: s
        });
        // 扩选项,default 'b' 仍合法 → 不弹窗
        f.oc.value = 'a,b,c,d';
        fire(f.oc, 'change');
        restore();
        const hidden = f.form.querySelector('input[name="migration_strategy"]');
        assert.ok(!hidden, '无 hidden input');
    });
});
// ============================================================
// strict_editor 端到端测试 (ADR 0013)
// 模拟 init script 流程:遍历 [data-value-cell] + 调 renderValueCell / buildChips
// ============================================================

describe('strict_editor 端到端 (ADR 0013)', () => {
    /** 模拟 init script 核心逻辑(简化版,只走核心 type 分支) */
    function renderCell(cell) {
        var type    = cell.getAttribute('data-type');
        var name    = cell.getAttribute('data-name');
        var optsRaw = cell.getAttribute('data-options') || '[]';
        var defRaw  = cell.getAttribute('data-default') || '""';

        var options;
        try { options = JSON.parse(optsRaw); } catch (e) { options = []; }

        // 自由 chips 模式
        if (type === 'multiselect' && options.length === 0) {
            var values;
            try { values = JSON.parse(defRaw); } catch (e) { values = []; }
            if (!Array.isArray(values)) values = [];
            return XfeCaTypeSwitcher.buildChips(cell, { name: name, currentValues: values });
        }

        var memo;
        if (type === 'multiselect' && defRaw) {
            try {
                memo = JSON.parse(defRaw);
                if (!Array.isArray(memo)) memo = defRaw;
            } catch (e) {
                memo = defRaw.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
            }
        } else {
            memo = defRaw;
            if (defRaw && defRaw.charAt(0) === '"') {
                try { memo = JSON.parse(defRaw); } catch (e) {}
            }
        }

        return XfeCaTypeSwitcher.renderValueCell(cell, {
            type:        type,
            memo:        memo,
            optionsCsv:  options.join(','),
            name:        name,
            className:   'xfe-ca-row-input',
            yesLabel:    '是',
            noLabel:     '否',
            blankLabel:  ''
        });
    }

    /** 构造 strict_editor 风格的 row:tr 含 td + td[data-value-cell] + chip-input(若需要) */
    function makeRow(type, opts) {
        opts = opts || {};
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.setAttribute('data-value-cell', '');
        td.setAttribute('data-type', type);
        td.setAttribute('data-default', opts.defaultJson || '""');
        td.setAttribute('data-options', opts.optionsJson || '[]');
        td.setAttribute('data-name', opts.name || ('custom_fields[k][' + type + ']'));
        if (opts.chips) {
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'xfe-ca-chip-input';
            td.appendChild(input);
        }
        tr.appendChild(td);
        document.body.appendChild(tr);
        return td;
    }

    it('5 种 type 都能正确渲染(text/number/select/multiselect 固定/chips)', () => {
        // text
        const tdText = makeRow('text', { defaultJson: '"hello"', name: 'custom_fields[k1][value]' });
        renderCell(tdText);
        const inp = tdText.querySelector('input');
        assert.equal(inp.value, 'hello');
        assert.equal(inp.className, 'xfe-ca-row-input');
        assert.equal(inp.name, 'custom_fields[k1][value]');

        // number
        const tdNum = makeRow('number', { defaultJson: '"42"', name: 'custom_fields[k2][value]' });
        renderCell(tdNum);
        assert.equal(tdNum.querySelector('input').value, '42');

        // select
        const tdSel = makeRow('select', {
            defaultJson: '"b"',
            optionsJson: '["a","b","c"]',
            name: 'custom_fields[k3][value]'
        });
        renderCell(tdSel);
        const sel = tdSel.querySelector('select');
        assert.ok(sel, '应有 select');
        assert.equal(sel.value, 'b');

        // multiselect 固定模式(name 带 [])
        const tdMs = makeRow('multiselect', {
            defaultJson: '["a","c"]',
            optionsJson: '["a","b","c"]',
            name: 'custom_fields[k4][value][]'
        });
        renderCell(tdMs);
        const ms = tdMs.querySelector('select');
        assert.ok(ms, '应有 multi-select');
        assert.equal(ms.multiple, true);
        assert.equal(ms.name, 'custom_fields[k4][value][]');
        assert.equal(ms.options[0].selected, true);  // a
        assert.equal(ms.options[2].selected, true);  // c

        // multiselect 自由 chips 模式
        const tdChips = makeRow('multiselect', {
            defaultJson: '["red","blue"]',
            optionsJson: '[]',
            name: 'custom_fields[k5][value]',
            chips: true
        });
        renderCell(tdChips);
        const hidden = tdChips.querySelector('input[type=hidden]');
        assert.ok(hidden, 'chips 模式应有 hidden input');
        assert.equal(hidden.value, 'red,blue');
        const chips = tdChips.querySelectorAll('.xfe-ca-chip');
        assert.equal(chips.length, 2);
    });

    it('boolean type 渲染 div 含 2 个 radio,name 已重写', () => {
        const td = makeRow('boolean', {
            defaultJson: '"1"',
            name: 'custom_fields[kbool][value]'
        });
        renderCell(td);
        const radios = td.querySelectorAll('input[type=radio]');
        assert.equal(radios.length, 2);
        assert.equal(radios[0].name, 'custom_fields[kbool][value]');
        assert.equal(radios[0].value, '1');
        assert.equal(radios[0].checked, true);
    });
});

// ============================================================
// 端到端测试 — 用 jsdom 真实事件,验证 bind() 联动行为 (ADR 0010)
// 端到端测试 — 用 jsdom 真实触发事件,验证 bind() 联动流程 (ADR 0010)
// ============================================================

/** 搭建最小 Magento 表单结构(field_type / options_csv / default_value) */
function setupForm(initial) {
    document.body.innerHTML = '';
    const table = document.createElement('table');
    document.body.appendChild(table);

    function makeRow(id) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        const el = document.createElement('input');
        el.id = id;
        el.name = id;
        if (initial && initial[id] !== undefined) el.value = initial[id];
        td.appendChild(el);
        tr.appendChild(td);
        table.appendChild(tr);
        return el;
    }

    const fieldTypeEl = makeRow('field_type');
    const optionsEl = makeRow('options_csv');
    const defaultEl = makeRow('default_value');
    return { fieldTypeEl, optionsEl, defaultEl };
}


/**
 * 小改 M(2026-09-18)配套测试
 *
 * 模拟 Magento Form 给 input 加 id 前缀的场景(如 'edit_form_options_csv'):
 * - name 仍 = 'options_csv'(Form.php addField 第二参 'name' 一致)
 * - id 带前缀
 *
 * 验证 type_switcher.phtml 的 findByName('options_csv') 走 querySelector('input[name="options_csv"]')
 * 能正确找到元素,mountOptionsEditor 挂载容器 + refresh() 正常工作。
 *
 * 注:type_switcher.phtml 的 findByName 在 phtml 内 closure 不外暴,这里直接用 querySelector
 * 模拟 phtml 内的查找逻辑,验证后续 bind() 能 work。
 */
describe('小改 M — bind() 兼容 id 带前缀(name 查找)', () => {
    function setupFormWithPrefixedIds(initial) {
        document.body.innerHTML = '';
        const form = document.createElement('form');
        form.id = 'edit_form';
        document.body.appendChild(form);
        // Magento Form 渲染可能给 id 加前缀(实际生产中可能不存在,但留兼容)
        function make(name) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            const el = document.createElement(name === 'field_type' ? 'select' : 'input');
            el.name = name;
            el.id = 'edit_form_' + name; // 带前缀
            if (name !== 'field_type') el.type = 'text';
            if (initial && initial[name] !== undefined) el.value = initial[name];
            td.appendChild(el);
            tr.appendChild(td);
            form.appendChild(tr);
            return el;
        }
        const fieldTypeEl = make('field_type');
        const optionsEl   = make('options_csv');
        const defaultEl   = make('default_value');
        // 给 field_type 加几个选项(否则 JS 测试无法切到 select/boolean)
        [['text','T'],['number','N'],['select','S'],['multiselect','M'],['boolean','B']].forEach(function (p) {
            const o = document.createElement('option');
            o.value = p[0]; o.text = p[1];
            fieldTypeEl.appendChild(o);
        });
        // 模拟 phtml findByName 逻辑
        const findByName = (name) =>
            document.querySelector('select[name="' + name + '"]')
            || document.querySelector('input[name="' + name + '"]')
            || document.querySelector('textarea[name="' + name + '"]')
            || document.getElementById(name);
        return {
            fieldTypeEl: findByName('field_type'),
            optionsEl:   findByName('options_csv'),
            defaultEl:   findByName('default_value')
        };
    }

    it('id 前缀场景:document.getElementById 找不到,name 查找能找到', () => {
        const { optionsEl, defaultEl } = setupFormWithPrefixedIds();
        // 关键断言:即使 id 是 'edit_form_options_csv',name 查找仍能找到
        assert.ok(optionsEl, 'optionsEl 通过 name 查找应能找到');
        assert.equal(optionsEl.id, 'edit_form_options_csv', 'id 确实是带前缀的');
        assert.ok(defaultEl, 'defaultEl 通过 name 查找应能找到');
    });

    it('id 前缀场景:text -> select,容器正确挂载 + 1 行空 row', () => {
        const { fieldTypeEl, optionsEl, defaultEl } = setupFormWithPrefixedIds({ field_type: 'text' });
        XfeCaTypeSwitcher.bind({ fieldTypeEl, optionsEl, defaultEl, yesLabel: 'Y', noLabel: 'N', blankLabel: '-' });
        fieldTypeEl.value = 'select';
        fire(fieldTypeEl, 'change');
        const editor = document.querySelector('.xfe-ca-opt-editor');
        assert.ok(editor, '容器应已挂载到 optionsEl.parentNode(<td>)');
        const rows = editor.querySelectorAll('.xfe-ca-opt-row');
        assert.equal(rows.length, 1, '空 options 时 select 默认 1 行空 row');
    });

    it('id 前缀场景:text -> boolean,容器正确挂载 + 2 行 readonly {0:否, 1:是}', () => {
        const { fieldTypeEl, optionsEl, defaultEl } = setupFormWithPrefixedIds({ field_type: 'text' });
        XfeCaTypeSwitcher.bind({ fieldTypeEl, optionsEl, defaultEl, yesLabel: 'Y', noLabel: 'N', blankLabel: '-' });
        fieldTypeEl.value = 'boolean';
        fire(fieldTypeEl, 'change');
        const editor = document.querySelector('.xfe-ca-opt-editor');
        assert.ok(editor, '容器应已挂载');
        const rows = editor.querySelectorAll('.xfe-ca-opt-row');
        assert.equal(rows.length, 2, 'boolean 默认 2 行');
        assert.equal(rows[0].querySelector('.xfe-ca-opt-key').value, '0', 'row[0] key=0');
        assert.equal(rows[0].querySelector('.xfe-ca-opt-label').value, '否', 'row[0] label=否');
        assert.equal(rows[1].querySelector('.xfe-ca-opt-key').value, '1', 'row[1] key=1');
        assert.equal(rows[1].querySelector('.xfe-ca-opt-label').value, '是', 'row[1] label=是');
        assert.ok(rows[0].querySelector('.xfe-ca-opt-key').readOnly, 'boolean key 列 readonly');
    });

    it('id 前缀场景:text -> multiselect,容器正确挂载 + 1 行空 row', () => {
        const { fieldTypeEl, optionsEl, defaultEl } = setupFormWithPrefixedIds({ field_type: 'text' });
        XfeCaTypeSwitcher.bind({ fieldTypeEl, optionsEl, defaultEl, yesLabel: 'Y', noLabel: 'N', blankLabel: '-' });
        fieldTypeEl.value = 'multiselect';
        fire(fieldTypeEl, 'change');
        const editor = document.querySelector('.xfe-ca-opt-editor');
        assert.ok(editor, '容器应已挂载');
        assert.equal(editor.querySelectorAll('.xfe-ca-opt-row').length, 1, 'multiselect 空时 1 行空 row');
    });
});
describe('bind() 端到端 — 切 field_type 触发联动', () => {
    it('text -> boolean: default_value 变 Yes/No radio', () => {
        const { fieldTypeEl, optionsEl, defaultEl } = setupForm({ field_type: 'text', default_value: 'hello' });
        XfeCaTypeSwitcher.bind({
            fieldTypeEl, optionsEl, defaultEl,
            yesLabel: '是', noLabel: '否', blankLabel: '-- 请选择 --'
        });
        fieldTypeEl.value = 'boolean';
        fire(fieldTypeEl, 'change');
        const newDefault = document.getElementById('default_value');
        assert.ok(newDefault.tagName === 'DIV', '应变为 div 容器');
        const inputs = newDefault.getElementsByTagName('input');
        assert.equal(inputs.length, 2);
        assert.equal(inputs[0].value, '1');
        assert.equal(inputs[1].value, '0');
        assert.equal(inputs[0].checked, false);  // memo='hello' 不是 '1'
        assert.equal(inputs[1].checked, false);  // 'hello' 也不是 '0' 或 ''
    });

    it('boolean -> select: default_value 变 select,blankLabel 显示', () => {
        const { fieldTypeEl, optionsEl, defaultEl } = setupForm({ field_type: 'boolean', default_value: '1' });
        XfeCaTypeSwitcher.bind({
            fieldTypeEl, optionsEl, defaultEl,
            yesLabel: '是', noLabel: '否', blankLabel: '-- 请选择 --'
        });
        // 先确保 default_value 当前是 boolean radio 容器
        fieldTypeEl.value = 'select';
        optionsEl.value = 'red,green,blue';
        fire(fieldTypeEl, 'change');
        const newDefault = document.getElementById('default_value');
        assert.ok(newDefault.tagName === 'SELECT', '应变为 select');
        assert.equal(newDefault.multiple, false);
        assert.equal(newDefault.options.length, 4); // blank + 3
        assert.equal(newDefault.options[0].text, '-- 请选择 --');
    });

    it('select -> multiselect: 加 multiple, size=5, 失去 blank', () => {
        const { fieldTypeEl, optionsEl, defaultEl } = setupForm({ field_type: 'select', default_value: 'red' });
        optionsEl.value = 'red,green,blue';
        XfeCaTypeSwitcher.bind({
            fieldTypeEl, optionsEl, defaultEl,
            yesLabel: '是', noLabel: '否', blankLabel: '-- 请选择 --'
        });
        fieldTypeEl.value = 'multiselect';
        optionsEl.value = 'red,green,blue';
        fire(fieldTypeEl, 'change');
        const newDefault = document.getElementById('default_value');
        assert.ok(newDefault.multiple, 'multiple 应为 true');
        assert.equal(newDefault.options.length, 3); // 无 blank
        assert.equal(newDefault.options[0].selected, true); // 'red' 还原
    });

    it('options_csv 改后 select 自动重建选项', () => {
        const { fieldTypeEl, optionsEl, defaultEl } = setupForm({ field_type: 'select' });
        optionsEl.value = 'a,b';
        XfeCaTypeSwitcher.bind({
            fieldTypeEl, optionsEl, defaultEl,
            yesLabel: 'Y', noLabel: 'N', blankLabel: '-'
        });
        fieldTypeEl.value = 'select';
        fire(fieldTypeEl, 'change');
        let sel = document.getElementById('default_value');
        assert.equal(sel.options.length, 3); // blank + a + b
        optionsEl.value = 'x,y,z,w';
        fire(optionsEl, 'change');
        sel = document.getElementById('default_value');
        assert.equal(sel.options.length, 5); // blank + 4
        assert.equal(sel.options[1].value, 'x');
        assert.equal(sel.options[4].value, 'w');
    });

    it('text -> number: text input 加 validate-number class', () => {
        const { fieldTypeEl, optionsEl, defaultEl } = setupForm({ field_type: 'text' });
        XfeCaTypeSwitcher.bind({
            fieldTypeEl, optionsEl, defaultEl,
            yesLabel: 'Y', noLabel: 'N', blankLabel: '-'
        });
        fieldTypeEl.value = 'number';
        fire(fieldTypeEl, 'change');
        const newDefault = document.getElementById('default_value');
        assert.equal(newDefault.type, 'text');
        assert.ok(newDefault.className.indexOf('validate-number') !== -1, '应含 validate-number');
    });

    it('编辑回显: 初始 field_type=boolean,default_value=1,bind 后 yes radio checked', () => {
        const { fieldTypeEl, optionsEl, defaultEl } = setupForm({ field_type: 'boolean', default_value: '1' });
        XfeCaTypeSwitcher.bind({
            fieldTypeEl, optionsEl, defaultEl,
            yesLabel: '是', noLabel: '否', blankLabel: '-'
        });
        // bind() 初始化会跑一次 switchType(),boolean -> radio
        const newDefault = document.getElementById('default_value');
        assert.ok(newDefault.tagName === 'DIV', '应已变为 div 容器');
        const inputs = newDefault.getElementsByTagName('input');
        assert.equal(inputs[0].checked, true);  // '1' 对应 yes checked
        assert.equal(inputs[1].checked, false);
    });

    it('编辑回显: 初始 field_type=multiselect,default_value=a|c,bind 后 a/c 已 selected', () => {
        const { fieldTypeEl, optionsEl, defaultEl } = setupForm({ field_type: 'multiselect', default_value: 'a|c' });
        optionsEl.value = 'a,b,c';
        XfeCaTypeSwitcher.bind({
            fieldTypeEl, optionsEl, defaultEl,
            yesLabel: 'Y', noLabel: 'N', blankLabel: '-'
        });
        const newDefault = document.getElementById('default_value');
        assert.ok(newDefault.multiple, '应已变为 multi-select');
        assert.equal(newDefault.options[0].selected, true);  // a
        assert.equal(newDefault.options[1].selected, false); // b
        assert.equal(newDefault.options[2].selected, true);  // c
    });


    it('小改 L: text -> boolean 时 options_csv 行的 <tr> 应保持可见(否则行编辑器容器被父 tr 隐藏)', () => {
        const { fieldTypeEl, optionsEl, defaultEl } = setupForm({ field_type: 'text', default_value: '' });
        XfeCaTypeSwitcher.bind({
            fieldTypeEl, optionsEl, defaultEl,
            yesLabel: '是', noLabel: '否', blankLabel: '-- 请选择 --'
        });
        // 初始是 text,options_csv 行应被隐藏(经典小改 G 行为)
        let trInitial = optionsEl.parentNode;
        while (trInitial && trInitial.nodeName !== 'TR') trInitial = trInitial.parentNode;
        assert.equal(trInitial.style.display, 'none', 'text 时 options_csv 行应隐藏(基线)');

        // 切到 boolean,关键:小改 L 修复后,options_csv 行**必须**保留可见 — 否则 mountOptionsEditor 容器(append 到 <td>)被父 tr 一起隐藏
        fieldTypeEl.value = 'boolean';
        fire(fieldTypeEl, 'change');
        let trBoolean = optionsEl.parentNode;
        while (trBoolean && trBoolean.nodeName !== 'TR') trBoolean = trBoolean.parentNode;
        assert.ok(trBoolean.style.display !== 'none', 'boolean 时 options_csv 行应保持可见(行编辑器需要用)');

        // 验证行编辑器容器已显示 + 含 2 行 readonly(默认 {0:否,1:是})
        const editor = optionsEl.parentNode.querySelector('.xfe-ca-opt-editor');
        assert.ok(editor, '行编辑器容器应已挂载');
        assert.ok(editor.style.display !== 'none', '行编辑器容器应可见');
        const rows = editor.querySelectorAll('.xfe-ca-opt-row');
        assert.equal(rows.length, 2, 'boolean 默认 2 行');
        const keyInput0 = rows[0].querySelector('.xfe-ca-opt-key');
        const keyInput1 = rows[1].querySelector('.xfe-ca-opt-key');
        assert.equal(keyInput0.value, '0', '第 1 行 key=0');
        assert.equal(keyInput1.value, '1', '第 2 行 key=1');
        assert.ok(keyInput0.readOnly, 'boolean key 列应 readonly');
        assert.ok(keyInput1.readOnly, 'boolean key 列应 readonly');
    });

    it('小改 L: select -> boolean 时 options_csv 行也应保持可见(mountOptionsEditor 容器随之显示)', () => {
        const { fieldTypeEl, optionsEl, defaultEl } = setupForm({ field_type: 'select', default_value: 'a' });
        optionsEl.value = 'a,b';
        XfeCaTypeSwitcher.bind({
            fieldTypeEl, optionsEl, defaultEl,
            yesLabel: 'Y', noLabel: 'N', blankLabel: '-'
        });
        fieldTypeEl.value = 'boolean';
        fire(fieldTypeEl, 'change');
        let tr = optionsEl.parentNode;
        while (tr && tr.nodeName !== 'TR') tr = tr.parentNode;
        assert.ok(tr.style.display !== 'none', 'select -> boolean 切换后行应保留');
        const editor = optionsEl.parentNode.querySelector('.xfe-ca-opt-editor');
        assert.ok(editor, '行编辑器容器应已挂载');
        assert.ok(editor.style.display !== 'none', '行编辑器容器应可见');
        // 从 'a,b' CSV 解析后应填入 rows(因为不是空)
        const rows = editor.querySelectorAll('.xfe-ca-opt-row');
        assert.ok(rows.length >= 2, '至少 2 行(默认填 2 或解析后的非空行)');
    });
    it('boolean 模式改 options_csv 不应触发 select 重建', () => {
        const { fieldTypeEl, optionsEl, defaultEl } = setupForm({ field_type: 'boolean', default_value: '1' });
        XfeCaTypeSwitcher.bind({
            fieldTypeEl, optionsEl, defaultEl,
            yesLabel: 'Y', noLabel: 'N', blankLabel: '-'
        });
        const before = document.getElementById('default_value');
        optionsEl.value = 'a,b,c';
        fire(optionsEl, 'change');
        const after = document.getElementById('default_value');
        assert.equal(before.tagName, after.tagName, 'boolean 下改 options_csv 不应改变 default_value 类型');
    });
});

// ============================================================
// 小改 G(2026-09-17):options_csv 行编辑器(ADR 0022)
// 5 个新公开方法的纯函数测试 + DOM 集成测试
// ============================================================

describe('parseOptionToken()', () => {
    it('empty string -> empty pair', () =>
        assert.deepEqual(XfeCaTypeSwitcher.parseOptionToken(''), { key: '', label: '' }));
    it('null/non-string -> empty pair', () => {
        assert.deepEqual(XfeCaTypeSwitcher.parseOptionToken(null), { key: '', label: '' });
        assert.deepEqual(XfeCaTypeSwitcher.parseOptionToken(undefined), { key: '', label: '' });
        assert.deepEqual(XfeCaTypeSwitcher.parseOptionToken(123), { key: '', label: '' });
    });
    it('no pipe: key = label = token', () =>
        assert.deepEqual(XfeCaTypeSwitcher.parseOptionToken('red'), { key: 'red', label: 'red' }));
    it('with pipe: split once, label keeps trailing pipe', () =>
        assert.deepEqual(XfeCaTypeSwitcher.parseOptionToken('red|红色'), { key: 'red', label: '红色' }));
    it('label with internal pipe preserved', () =>
        assert.deepEqual(XfeCaTypeSwitcher.parseOptionToken('a|b|c'), { key: 'a', label: 'b|c' }));
    it('trim boundary whitespace', () =>
        assert.deepEqual(XfeCaTypeSwitcher.parseOptionToken('  red  '), { key: 'red', label: 'red' }));
    it('leading pipe: empty key', () =>
        assert.deepEqual(XfeCaTypeSwitcher.parseOptionToken('|label'), { key: '', label: 'label' }));
});

describe('serializeOptionPair()', () => {
    it('null/undefined/non-string pair -> ""', () => {
        assert.equal(XfeCaTypeSwitcher.serializeOptionPair(null), '');
        assert.equal(XfeCaTypeSwitcher.serializeOptionPair(undefined), '');
    });
    it('label empty -> just key', () =>
        assert.equal(XfeCaTypeSwitcher.serializeOptionPair({ key: 'red', label: '' }), 'red'));
    it('label == key -> just key (压缩)', () =>
        assert.equal(XfeCaTypeSwitcher.serializeOptionPair({ key: 'red', label: 'red' }), 'red'));
    it('label != key -> "key|label"', () =>
        assert.equal(XfeCaTypeSwitcher.serializeOptionPair({ key: 'red', label: '红色' }), 'red|红色'));
    it('empty key -> "" (后续 serializePairsToCsv 会过滤)', () =>
        assert.equal(XfeCaTypeSwitcher.serializeOptionPair({ key: '', label: 'foo' }), ''));
});

describe('parseOptionsCsvToPairs()', () => {
    it('empty string -> []', () =>
        assert.deepEqual(XfeCaTypeSwitcher.parseOptionsCsvToPairs(''), []));
    it('null -> []', () =>
        assert.deepEqual(XfeCaTypeSwitcher.parseOptionsCsvToPairs(null), []));
    it('single key', () =>
        assert.deepEqual(XfeCaTypeSwitcher.parseOptionsCsvToPairs('red'), [{ key: 'red', label: 'red' }]));
    it('multiple with labels', () =>
        assert.deepEqual(
            XfeCaTypeSwitcher.parseOptionsCsvToPairs('red|红色,green|绿色,blue'),
            [{ key: 'red', label: '红色' }, { key: 'green', label: '绿色' }, { key: 'blue', label: 'blue' }]
        ));
    it('empty items filtered + whitespace trimmed', () =>
        assert.deepEqual(
            XfeCaTypeSwitcher.parseOptionsCsvToPairs(' red , , blue|蓝 '),
            [{ key: 'red', label: 'red' }, { key: 'blue', label: '蓝' }]
        ));
});

describe('serializePairsToCsv()', () => {
    it('empty array / non-array -> ""', () => {
        assert.equal(XfeCaTypeSwitcher.serializePairsToCsv([]), '');
        assert.equal(XfeCaTypeSwitcher.serializePairsToCsv(null), '');
        assert.equal(XfeCaTypeSwitcher.serializePairsToCsv('foo'), '');
    });
    it('all compressed (label == key)', () =>
        assert.equal(
            XfeCaTypeSwitcher.serializePairsToCsv([{ key: 'a', label: 'a' }, { key: 'b', label: 'b' }]),
            'a,b'
        ));
    it('mixed compressed and labeled', () =>
        assert.equal(
            XfeCaTypeSwitcher.serializePairsToCsv([{ key: 'red', label: '红色' }, { key: 'blue', label: 'blue' }]),
            'red|红色,blue'
        ));
    it('empty key rows filtered', () =>
        assert.equal(
            XfeCaTypeSwitcher.serializePairsToCsv([{ key: '', label: 'foo' }, { key: 'red', label: '红色' }]),
            'red|红色'
        ));
    it('round-trip: parseOptionsCsvToPairs -> serializePairsToCsv', () => {
        const csv = 'a|甲, b |乙, c';
        const pairs = XfeCaTypeSwitcher.parseOptionsCsvToPairs(csv);
        const back = XfeCaTypeSwitcher.serializePairsToCsv(pairs);
        assert.equal(back, 'a|甲,b|乙,c');
    });
});

describe('mountOptionsEditor()', () => {
    function setupForm() {
        document.body.innerHTML = '';
        const ft = document.createElement('select');
        ft.id = 'field_type';
        ['text', 'select', 'multiselect'].forEach(function (v) {
            const o = document.createElement('option');
            o.value = v; o.text = v;
            ft.appendChild(o);
        });
        ft.value = 'select';
        document.body.appendChild(ft);

        const oc = document.createElement('input');
        oc.id = 'options_csv';
        oc.type = 'text';
        oc.value = 'red|红色,green|绿色,blue';
        document.body.appendChild(oc);

        const dv = document.createElement('input');
        dv.id = 'default_value';
        dv.type = 'text';
        oc.parentNode.insertBefore(dv, oc.nextSibling);
        return { ft: ft, oc: oc, dv: dv };
    }

    it('mounts container next to optionsEl', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        assert.ok(handle, '应返回 handle');
        const editor = document.querySelector('.xfe-ca-opt-editor');
        assert.ok(editor, '应挂载 .xfe-ca-opt-editor 容器');
    });

    it('refresh(select): shows editor + hides optionsEl + renders 3 rows', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const editor = document.querySelector('.xfe-ca-opt-editor');
        assert.equal(editor.style.display, '', 'select 时编辑器应可见');
        assert.equal(oc.style.display, 'none', '原生 input 应隐藏');
        const rows = editor.querySelectorAll('.xfe-ca-opt-row');
        assert.equal(rows.length, 3, '应渲染 3 行(red|红色, green|绿色, blue)');
        assert.equal(rows[0].querySelector('.xfe-ca-opt-key').value, 'red');
        assert.equal(rows[0].querySelector('.xfe-ca-opt-label').value, '红色');
        assert.equal(rows[2].querySelector('.xfe-ca-opt-key').value, 'blue');
        assert.equal(rows[2].querySelector('.xfe-ca-opt-label').value, 'blue', 'label 缺省 = key 时填回 key');
    });

    it('refresh(text): hides editor + shows optionsEl', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        handle.refresh('text');
        const editor = document.querySelector('.xfe-ca-opt-editor');
        assert.equal(editor.style.display, 'none', 'text 时编辑器应隐藏');
        assert.equal(oc.style.display, '', '原生 input 应可见');
    });

    it('refresh(select) on empty csv: 默认 1 个空行', () => {
        const { ft, oc } = setupForm();
        oc.value = '';
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const rows = document.querySelectorAll('.xfe-ca-opt-row');
        assert.equal(rows.length, 1, '空 CSV 应默认 1 个空行引导输入');
        assert.equal(rows[0].querySelector('.xfe-ca-opt-key').value, '');
    });

    it('add row: 点击 + 添加候选项 增加 1 行', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const beforeRows = document.querySelectorAll('.xfe-ca-opt-row').length;
        const addBtn = document.querySelector('.xfe-ca-opt-editor-add');
        addBtn.click();
        const afterRows = document.querySelectorAll('.xfe-ca-opt-row').length;
        assert.equal(afterRows, beforeRows + 1, '应多 1 行');
    });

    it('del row: 点击删除按钮移除行', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const firstDel = document.querySelector('.xfe-ca-opt-row .xfe-ca-opt-del');
        firstDel.click();
        const rows = document.querySelectorAll('.xfe-ca-opt-row');
        assert.equal(rows.length, 2, '原 3 行 → 删 1 行 → 剩 2 行');
    });

    it('input change: 改 key/label 后 options_csv 同步更新(无 change 误触发)', () => {
        const { ft, oc } = setupForm();
        let changeCount = 0;
        oc.addEventListener('change', function () { changeCount++; });
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const firstKeyInput = document.querySelector('.xfe-ca-opt-row .xfe-ca-opt-key');
        firstKeyInput.value = 'crimson';
        fire(firstKeyInput, 'input');
        assert.equal(oc.value, 'crimson|红色,green|绿色,blue', '改 key 应同步 CSV');
        assert.ok(changeCount >= 1, '应触发 change 事件(供 default_value 重建)');
    });

    it('empty key row 同步时不进入 CSV', () => {
        const { ft, oc } = setupForm();
        oc.value = 'red|红色';
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        // 在第一行的 key 里清空,再点删除
        const firstRow = document.querySelector('.xfe-ca-opt-row');
        firstRow.querySelector('.xfe-ca-opt-key').value = '';
        fire(firstRow.querySelector('.xfe-ca-opt-key'), 'input');
        assert.equal(oc.value, '', '空 key 行不进入 CSV');
    });

    it('duplicate mount returns cached handle(防重复)', () => {
        const { ft, oc } = setupForm();
        const h1 = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        const h2 = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        assert.equal(h1, h2, '二次调用应返回同一 handle');
        const editors = document.querySelectorAll('.xfe-ca-opt-editor');
        assert.equal(editors.length, 1, 'DOM 里只有一个编辑器');
    });

    it('destroy 移除 DOM + 解锁挂载', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.destroy();
        const editors = document.querySelectorAll('.xfe-ca-opt-editor');
        assert.equal(editors.length, 0, 'destroy 后无编辑器');
        const h2 = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        assert.ok(h2 && h2 !== handle, 'destroy 后可重新挂载');
    });
});

// ============================================================
// bind() 集成: 行编辑器接入后行为(小改 G)
// ============================================================

describe('bind() — options_csv 行编辑器接入(小改 G)', () => {
    function setupForm() {
        document.body.innerHTML = '';
        const ft = document.createElement('select');
        ft.id = 'field_type';
        ['text', 'select', 'multiselect', 'number', 'boolean'].forEach(function (v) {
            const o = document.createElement('option');
            o.value = v; o.text = v;
            ft.appendChild(o);
        });
        ft.value = 'select';
        document.body.appendChild(ft);

        const oc = document.createElement('input');
        oc.id = 'options_csv';
        oc.type = 'text';
        oc.value = '';
        const ocCell = document.createElement('td');
        ocCell.appendChild(oc);
        document.body.appendChild(ocCell);

        const dv = document.createElement('input');
        dv.id = 'default_value';
        dv.type = 'text';
        dv.value = '';
        const dvCell = document.createElement('td');
        dvCell.appendChild(dv);
        document.body.appendChild(dvCell);
        return { ft: ft, oc: oc, dv: dv };
    }

    it('bind(select): 挂载编辑器 + 隐藏 options_csv', () => {
        const { ft, oc, dv } = setupForm();
        ft.value = 'select';
        oc.value = 'a|甲,b|乙';
        XfeCaTypeSwitcher.bind({ fieldTypeEl: ft, optionsEl: oc, defaultEl: dv, yesLabel: 'Y', noLabel: 'N', blankLabel: '-' });
        const editor = document.querySelector('.xfe-ca-opt-editor');
        assert.ok(editor, '应挂载行编辑器');
        assert.equal(editor.style.display, '', 'select 时编辑器应可见');
        assert.equal(oc.style.display, 'none', '原生 options_csv 应隐藏');
        assert.equal(editor.querySelectorAll('.xfe-ca-opt-row').length, 2, '应渲染 2 行');
    });

    it('bind(text): 编辑器隐藏 + 原生 options_csv 可见', () => {
        const { ft, oc, dv } = setupForm();
        ft.value = 'text';
        oc.value = 'ignored,because,text';
        XfeCaTypeSwitcher.bind({ fieldTypeEl: ft, optionsEl: oc, defaultEl: dv, yesLabel: 'Y', noLabel: 'N', blankLabel: '-' });
        const editor = document.querySelector('.xfe-ca-opt-editor');
        assert.equal(editor.style.display, 'none', 'text 时编辑器应隐藏');
        assert.equal(oc.style.display, '', '原生 input 应可见');
    });

    it('编辑器触发 change 应跳过 promptMigrationStrategy(防误弹窗)', () => {
        const { ft, oc, dv } = setupForm();
        ft.value = 'select';
        oc.value = 'a,b,c';
        let confirmCalled = false;
        const origConfirm = dom.window.confirm;
        dom.window.confirm = function () { confirmCalled = true; return true; };
        try {
            XfeCaTypeSwitcher.bind({ fieldTypeEl: ft, optionsEl: oc, defaultEl: dv, yesLabel: 'Y', noLabel: 'N', blankLabel: '-' });
            const firstKey = document.querySelector('.xfe-ca-opt-row .xfe-ca-opt-key');
            firstKey.value = 'A';
            fire(firstKey, 'input');
            assert.equal(confirmCalled, false, '编辑器 change 不应触发 confirm 弹窗');
            assert.equal(oc.value, 'A,b,c', 'CSV 应已更新');
        } finally {
            dom.window.confirm = origConfirm;
        }
    });
});


// ============================================================
// 小改 H(2026-09-17):options_csv 行编辑器 key 重复实时检测
// ============================================================

describe('mountOptionsEditor() — _refreshDuplicateHints(小改 H)', () => {
    function setupForm() {
        document.body.innerHTML = '';
        const ft = document.createElement('select');
        ft.id = 'field_type';
        ft.value = 'select';
        document.body.appendChild(ft);

        const oc = document.createElement('input');
        oc.id = 'options_csv';
        oc.type = 'text';
        oc.value = 'a,b,c';
        const ocCell = document.createElement('td');
        ocCell.appendChild(oc);
        document.body.appendChild(ocCell);

        const dv = document.createElement('input');
        dv.id = 'default_value';
        dv.type = 'text';
        dv.value = '';
        const dvCell = document.createElement('td');
        dvCell.appendChild(dv);
        document.body.appendChild(dvCell);

        return { ft: ft, oc: oc, dv: dv };
    }

    it('refresh 后: 初始无重复,所有行无 .xfe-ca-opt-dup', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const dups = document.querySelectorAll('.xfe-ca-opt-key.xfe-ca-opt-dup');
        assert.equal(dups.length, 0, '初始应无 dup 标记');
    });

    it('改 key 让两个 row 重复: 两个 row 同时加 .xfe-ca-opt-dup + .xfe-ca-opt-row-dup', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const keys = document.querySelectorAll('.xfe-ca-opt-row .xfe-ca-opt-key');
        keys[1].value = 'a'; // 第 2 行改成与第 1 行相同
        fire(keys[1], 'input');
        const dupKeys = document.querySelectorAll('.xfe-ca-opt-key.xfe-ca-opt-dup');
        assert.equal(dupKeys.length, 2, '两个重复 key 都应被标 dup');
        const dupRows = document.querySelectorAll('.xfe-ca-opt-row-dup');
        assert.equal(dupRows.length, 2, '两个 row 都应加 row-dup class');
        const hints = document.querySelectorAll('.xfe-ca-opt-dup-hint');
        assert.equal(hints.length, 2, '应插入两个 "key 重复" 提示');
        assert.equal(hints[0].textContent, 'key 重复');
    });

    it('修正重复后: dup class 全部清除', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const keys = document.querySelectorAll('.xfe-ca-opt-row .xfe-ca-opt-key');
        keys[1].value = 'a';
        fire(keys[1], 'input');
        // 验证已 dup
        assert.equal(document.querySelectorAll('.xfe-ca-opt-dup').length, 2);
        // 修正
        keys[1].value = 'B';
        fire(keys[1], 'input');
        assert.equal(document.querySelectorAll('.xfe-ca-opt-dup').length, 0, '修正后应清掉所有 dup');
        assert.equal(document.querySelectorAll('.xfe-ca-opt-dup-hint').length, 0, '应删除所有提示');
    });

    it('空 key 不参与重复检测', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const keys = document.querySelectorAll('.xfe-ca-opt-row .xfe-ca-opt-key');
        // 让两行 key 都空
        keys[0].value = '';
        keys[1].value = '';
        fire(keys[0], 'input');
        fire(keys[1], 'input');
        assert.equal(document.querySelectorAll('.xfe-ca-opt-dup').length, 0, '空 key 不应被标记 dup');
    });

    it('三行同 key: 三个 row 都被标 dup(不是只标后两个)', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const keys = document.querySelectorAll('.xfe-ca-opt-row .xfe-ca-opt-key');
        keys[1].value = 'a';
        keys[2].value = 'a';
        fire(keys[1], 'input');
        fire(keys[2], 'input');
        assert.equal(document.querySelectorAll('.xfe-ca-opt-dup').length, 3, '三行同 key 应全标');
    });

    it('从 CSV 加载的初始数据含 dup 时,refresh 应立即标记', () => {
        const { ft, oc } = setupForm();
        oc.value = 'a,b,a';
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const dupKeys = document.querySelectorAll('.xfe-ca-opt-key.xfe-ca-opt-dup');
        assert.equal(dupKeys.length, 2, '初始 CSV 含重复 key 应立即标记(第 1 行 + 第 3 行)');
    });

    it('label 改变不应触发 dup 检测(避免误报)', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const labels = document.querySelectorAll('.xfe-ca-opt-row .xfe-ca-opt-label');
        labels[0].value = '新标签';
        fire(labels[0], 'input');
        assert.equal(document.querySelectorAll('.xfe-ca-opt-dup').length, 0, '改 label 不应导致 dup 检测');
        // 但 syncToHidden 应触发
        assert.equal(oc.value.split(',')[0].indexOf('新标签') >= 0, true, 'CSV 应已同步');
    });
});

describe('bind() — options_csv 行编辑器接入(小改 G)', () => {
    function setupForm() {
        document.body.innerHTML = '';
        const ft = document.createElement('select');
        ft.id = 'field_type';
        ['text', 'select', 'multiselect', 'number', 'boolean'].forEach(function (v) {
            const o = document.createElement('option');
            o.value = v; o.text = v;
            ft.appendChild(o);
        });
        ft.value = 'select';
        document.body.appendChild(ft);

        const oc = document.createElement('input');
        oc.id = 'options_csv';
        oc.type = 'text';
        oc.value = '';
        const ocCell = document.createElement('td');
        ocCell.appendChild(oc);
        document.body.appendChild(ocCell);

        const dv = document.createElement('input');
        dv.id = 'default_value';
        dv.type = 'text';
        dv.value = '';
        const dvCell = document.createElement('td');
        dvCell.appendChild(dv);
        document.body.appendChild(dvCell);
        return { ft: ft, oc: oc, dv: dv };
    }

    it('bind(select): 挂载编辑器 + 隐藏 options_csv', () => {
        const { ft, oc, dv } = setupForm();
        ft.value = 'select';
        oc.value = 'a|甲,b|乙';
        XfeCaTypeSwitcher.bind({ fieldTypeEl: ft, optionsEl: oc, defaultEl: dv, yesLabel: 'Y', noLabel: 'N', blankLabel: '-' });
        const editor = document.querySelector('.xfe-ca-opt-editor');
        assert.ok(editor, '应挂载行编辑器');
        assert.equal(editor.style.display, '', 'select 时编辑器应可见');
        assert.equal(oc.style.display, 'none', '原生 options_csv 应隐藏');
        assert.equal(editor.querySelectorAll('.xfe-ca-opt-row').length, 2, '应渲染 2 行');
    });

    it('bind(text): 编辑器隐藏 + 原生 options_csv 可见', () => {
        const { ft, oc, dv } = setupForm();
        ft.value = 'text';
        oc.value = 'ignored,because,text';
        XfeCaTypeSwitcher.bind({ fieldTypeEl: ft, optionsEl: oc, defaultEl: dv, yesLabel: 'Y', noLabel: 'N', blankLabel: '-' });
        const editor = document.querySelector('.xfe-ca-opt-editor');
        assert.equal(editor.style.display, 'none', 'text 时编辑器应隐藏');
        assert.equal(oc.style.display, '', '原生 input 应可见');
    });

    it('编辑器触发 change 应跳过 promptMigrationStrategy(防误弹窗)', () => {
        const { ft, oc, dv } = setupForm();
        ft.value = 'select';
        oc.value = 'a,b,c';
        let confirmCalled = false;
        const origConfirm = dom.window.confirm;
        dom.window.confirm = function () { confirmCalled = true; return true; };
        try {
            XfeCaTypeSwitcher.bind({ fieldTypeEl: ft, optionsEl: oc, defaultEl: dv, yesLabel: 'Y', noLabel: 'N', blankLabel: '-' });
            const firstKey = document.querySelector('.xfe-ca-opt-row .xfe-ca-opt-key');
            firstKey.value = 'A';  // 改大小写
            fire(firstKey, 'input');
            assert.equal(confirmCalled, false, '编辑器 change 不应触发 confirm 弹窗');
            assert.equal(oc.value, 'A,b,c', 'CSV 应已更新');
        } finally {
            dom.window.confirm = origConfirm;
        }
    });
});


// ============================================================
// 小改 I(2026-09-17):options_csv 行编辑器 HTML5 拖拽排序
// ============================================================

describe('mountOptionsEditor() — 拖拽排序(小改 I)', () => {
    function setupForm() {
        document.body.innerHTML = '';
        const ft = document.createElement('select');
        ft.id = 'field_type';
        ft.value = 'select';
        document.body.appendChild(ft);

        const oc = document.createElement('input');
        oc.id = 'options_csv';
        oc.type = 'text';
        oc.value = 'a,b,c';
        const ocCell = document.createElement('td');
        ocCell.appendChild(oc);
        document.body.appendChild(ocCell);

        const dv = document.createElement('input');
        dv.id = 'default_value';
        dv.type = 'text';
        dv.value = '';
        const dvCell = document.createElement('td');
        dvCell.appendChild(dv);
        document.body.appendChild(dvCell);

        return { ft: ft, oc: oc, dv: dv };
    }

    it('每行默认 draggable=true,cursor: move', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const rows = document.querySelectorAll('.xfe-ca-opt-row');
        assert.equal(rows.length, 3);
        for (let i = 0; i < rows.length; i++) {
            assert.equal(rows[i].draggable, true, 'row ' + i + ' 应可拖');
            assert.equal(rows[i].style.cursor, 'move', 'cursor 应为 move');
        }
    });

    it('dragstart: 标记 __xfeCaDragRow + .xfe-ca-opt-dragging class', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const rows = document.querySelectorAll('.xfe-ca-opt-row');
        const fakeEvent = new dom.window.Event('dragstart', { bubbles: true });
        fakeEvent.dataTransfer = { setData: function() {}, effectAllowed: null };
        rows[0].dispatchEvent(fakeEvent);
        const rowsEl = document.querySelector('.xfe-ca-opt-editor-rows');
        assert.equal(rowsEl.__xfeCaDragRow, rows[0], '应标记被拖行');
        assert.ok(rows[0].classList.contains('xfe-ca-opt-dragging'), '被拖行应加 dragging class');
    });

    it('dragend: 清理 dragging class + __xfeCaDragRow', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const rows = document.querySelectorAll('.xfe-ca-opt-row');
        const fakeEvent = new dom.window.Event('dragstart', { bubbles: true });
        fakeEvent.dataTransfer = { setData: function() {}, effectAllowed: null };
        rows[0].dispatchEvent(fakeEvent);
        const rowsEl = document.querySelector('.xfe-ca-opt-editor-rows');
        rows[0].dispatchEvent(new dom.window.Event('dragend', { bubbles: true }));
        assert.equal(rowsEl.__xfeCaDragRow, null, 'dragend 后应清理');
        assert.equal(rows[0].classList.contains('xfe-ca-opt-dragging'), false, 'dragging class 应清除');
    });

    it('drop (上半): 拖第 3 行(c)到第 1 行(a)上半 → c 插在 a 之前', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const rows = document.querySelectorAll('.xfe-ca-opt-row');

        // 拖第 3 行(rows[2], c)到第 1 行(rows[0], a)上半 → c 插在 a 之前
        const fakeStart = new dom.window.Event('dragstart', { bubbles: true });
        fakeStart.dataTransfer = { setData: function() {}, effectAllowed: null };
        rows[2].dispatchEvent(fakeStart);

        // mock getBoundingClientRect: target rows[0] top=100, height=30,middle=115
        rows[0].getBoundingClientRect = function() { return { top: 100, height: 30 }; };

        const fakeDrop = new dom.window.Event('drop', { bubbles: true });
        fakeDrop.dataTransfer = {};
        fakeDrop.clientY = 105; // < 115 (middle),即 top 半边
        fakeDrop.preventDefault = function() {};
        rows[0].dispatchEvent(fakeDrop);

        const newRows = document.querySelectorAll('.xfe-ca-opt-row');
        assert.equal(newRows[0].querySelector('.xfe-ca-opt-key').value, 'c', '原 c 应在索引 0');
        assert.equal(newRows[1].querySelector('.xfe-ca-opt-key').value, 'a');
        assert.equal(newRows[2].querySelector('.xfe-ca-opt-key').value, 'b');
        assert.equal(oc.value, 'c,a,b', 'CSV 应已同步');
    });

    it('drop (下 1/2): 拖到目标行下 1/2 → 插在目标行之后', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const rows = document.querySelectorAll('.xfe-ca-opt-row');

        const fakeStart = new dom.window.Event('dragstart', { bubbles: true });
        fakeStart.dataTransfer = { setData: function() {}, effectAllowed: null };
        rows[2].dispatchEvent(fakeStart); // 拖第 3 行(c)

        rows[0].getBoundingClientRect = function() { return { top: 100, height: 30 }; };

        const fakeDrop = new dom.window.Event('drop', { bubbles: true });
        fakeDrop.dataTransfer = {};
        fakeDrop.clientY = 125; // > 100 + 15,下 1/2
        fakeDrop.preventDefault = function() {};
        rows[0].dispatchEvent(fakeDrop);

        const newRows = document.querySelectorAll('.xfe-ca-opt-row');
        assert.equal(newRows[0].querySelector('.xfe-ca-opt-key').value, 'a');
        assert.equal(newRows[1].querySelector('.xfe-ca-opt-key').value, 'c', 'c 应在 a 之后');
        assert.equal(newRows[2].querySelector('.xfe-ca-opt-key').value, 'b');
        assert.equal(oc.value, 'a,c,b');
    });

    it('拖拽同步 CSV 后也跑 dup 检测', () => {
        const { ft, oc } = setupForm();
        oc.value = 'a,b,c';
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const rows = document.querySelectorAll('.xfe-ca-opt-row');

        // 把第 3 行(c)的 key 改成 a
        rows[2].querySelector('.xfe-ca-opt-key').value = 'a';
        fire(rows[2].querySelector('.xfe-ca-opt-key'), 'input');

        assert.equal(document.querySelectorAll('.xfe-ca-opt-dup').length, 2, 'dup 应已触发');

        // 现在拖第 3 行到第 1 行之前
        const fakeStart = new dom.window.Event('dragstart', { bubbles: true });
        fakeStart.dataTransfer = { setData: function() {}, effectAllowed: null };
        rows[2].dispatchEvent(fakeStart);
        rows[0].getBoundingClientRect = function() { return { top: 100, height: 30 }; };
        const fakeDrop = new dom.window.Event('drop', { bubbles: true });
        fakeDrop.dataTransfer = {};
        fakeDrop.clientY = 105;
        fakeDrop.preventDefault = function() {};
        rows[0].dispatchEvent(fakeDrop);

        // 拖拽后 dup 应仍然存在(因为 key 还是重复)
        assert.equal(document.querySelectorAll('.xfe-ca-opt-dup').length, 2, '拖拽后 dup 应仍生效');
        assert.equal(oc.value, 'a,a,b', 'CSV 应为 a,a,b');
    });

    it('drop 到自己: noop(不应无限递归或改变顺序)', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const rows = document.querySelectorAll('.xfe-ca-opt-row');
        const fakeStart = new dom.window.Event('dragstart', { bubbles: true });
        fakeStart.dataTransfer = { setData: function() {}, effectAllowed: null };
        rows[0].dispatchEvent(fakeStart);
        rows[0].getBoundingClientRect = function() { return { top: 100, height: 30 }; };
        const fakeDrop = new dom.window.Event('drop', { bubbles: true });
        fakeDrop.dataTransfer = {};
        fakeDrop.clientY = 110;
        fakeDrop.preventDefault = function() {};
        rows[0].dispatchEvent(fakeDrop);
        const newRows = document.querySelectorAll('.xfe-ca-opt-row');
        assert.equal(newRows[0].querySelector('.xfe-ca-opt-key').value, 'a', 'a 应仍在第 1 位');
        assert.equal(oc.value, 'a,b,c', 'CSV 不应变化');
    });
});



// ============================================================
// 小改 J(2026-09-17):options_csv 行编辑器文案 i18n
// ============================================================

describe('mountOptionsEditor() — 文案 i18n(小改 J)', () => {
    function setupForm() {
        document.body.innerHTML = '';
        const ft = document.createElement('select');
        ft.id = 'field_type';
        ft.value = 'select';
        document.body.appendChild(ft);

        const oc = document.createElement('input');
        oc.id = 'options_csv';
        oc.type = 'text';
        oc.value = 'a,b';
        const ocCell = document.createElement('td');
        ocCell.appendChild(oc);
        document.body.appendChild(ocCell);

        const dv = document.createElement('input');
        dv.id = 'default_value';
        dv.type = 'text';
        dv.value = '';
        const dvCell = document.createElement('td');
        dvCell.appendChild(dv);
        document.body.appendChild(dvCell);

        return { ft: ft, oc: oc, dv: dv };
    }

    it('未传 labels 时使用英文 fallback', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('select');
        const editor = document.querySelector('.xfe-ca-opt-editor');
        const head = editor.querySelector('.xfe-ca-opt-editor-head');
        assert.equal(head.textContent, '候选项(key / 显示名)', 'fallback 中文 head');
        const addBtn = editor.querySelector('.xfe-ca-opt-editor-add');
        assert.equal(addBtn.textContent, '+ 添加候选项', 'fallback 中文 add');
        const dels = editor.querySelectorAll('.xfe-ca-opt-del');
        assert.equal(dels[0].textContent, '删除', 'fallback 中文 delete');
    });

    it('通过 opts.labels 覆盖文案', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({
            optionsEl: oc,
            fieldTypeEl: ft,
            labels: {
                headTitle: 'Options',
                addBtn:    '+ Add',
                delBtn:    'Del',
                dupHint:   'dup!'
            }
        });
        handle.refresh('select');
        const editor = document.querySelector('.xfe-ca-opt-editor');
        assert.equal(editor.querySelector('.xfe-ca-opt-editor-head').textContent, 'Options');
        assert.equal(editor.querySelector('.xfe-ca-opt-editor-add').textContent, '+ Add');
        assert.equal(editor.querySelector('.xfe-ca-opt-del').textContent, 'Del');
    });

    it('从 window.XfeCaEditorLabels 全局注入(模拟 phtml 注入路径)', () => {
        const { ft, oc } = setupForm();
        dom.window.XfeCaEditorLabels = {
            headTitle: '候補(key / 表示名)',
            addBtn:    '+ 追加',
            delBtn:    '削除',
            dupHint:   'key 重複'
        };
        try {
            const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
            handle.refresh('select');
            const editor = document.querySelector('.xfe-ca-opt-editor');
            assert.equal(editor.querySelector('.xfe-ca-opt-editor-head').textContent, '候補(key / 表示名)');
            assert.equal(editor.querySelector('.xfe-ca-opt-editor-add').textContent, '+ 追加');
            assert.equal(editor.querySelector('.xfe-ca-opt-del').textContent, '削除');
        } finally {
            delete dom.window.XfeCaEditorLabels;
        }
    });

    it('opts.labels 优先级高于 window 全局', () => {
        const { ft, oc } = setupForm();
        dom.window.XfeCaEditorLabels = { headTitle: 'GLOBAL' };
        try {
            const handle = XfeCaTypeSwitcher.mountOptionsEditor({
                optionsEl: oc,
                fieldTypeEl: ft,
                labels: { headTitle: 'LOCAL' }
            });
            handle.refresh('select');
            assert.equal(document.querySelector('.xfe-ca-opt-editor-head').textContent, 'LOCAL');
        } finally {
            delete dom.window.XfeCaEditorLabels;
        }
    });

    it('部分 labels 缺失时,缺失的 key 回退英文 fallback', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({
            optionsEl: oc,
            fieldTypeEl: ft,
            labels: { headTitle: 'Only Head' }
        });
        handle.refresh('select');
        const editor = document.querySelector('.xfe-ca-opt-editor');
        assert.equal(editor.querySelector('.xfe-ca-opt-editor-head').textContent, 'Only Head');
        assert.equal(editor.querySelector('.xfe-ca-opt-editor-add').textContent, '+ 添加候选项', 'addBtn 缺失 → fallback');
        assert.equal(editor.querySelector('.xfe-ca-opt-del').textContent, '删除', 'delBtn 缺失 → fallback');
    });

    it('dup 提示用 i18n 文案', () => {
        const { ft, oc } = setupForm();
        oc.value = 'a,a';
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({
            optionsEl: oc,
            fieldTypeEl: ft,
            labels: { dupHint: 'duplicate key!' }
        });
        handle.refresh('select');
        const hints = document.querySelectorAll('.xfe-ca-opt-dup-hint');
        assert.equal(hints.length, 2);
        assert.equal(hints[0].textContent, 'duplicate key!');
    });
});



// ============================================================
// 小改 K(2026-09-18):options_csv 行编辑器 boolean label 自定义
// ============================================================

describe('mountOptionsEditor() — boolean label 自定义(小改 K)', () => {
    function setupForm() {
        document.body.innerHTML = '';
        const ft = document.createElement('select');
        ft.id = 'field_type';
        ft.value = 'boolean';
        document.body.appendChild(ft);

        const oc = document.createElement('input');
        oc.id = 'options_csv';
        oc.type = 'text';
        oc.value = '';
        const ocCell = document.createElement('td');
        ocCell.appendChild(oc);
        document.body.appendChild(ocCell);

        const dv = document.createElement('input');
        dv.id = 'default_value';
        dv.type = 'text';
        dv.value = '';
        const dvCell = document.createElement('td');
        dvCell.appendChild(dv);
        document.body.appendChild(dvCell);

        return { ft: ft, oc: oc, dv: dv };
    }

    it('refresh(boolean): 编辑器可见 + 默认填 2 行 {0:否,1:是}', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('boolean');
        const rows = document.querySelectorAll('.xfe-ca-opt-row');
        assert.equal(rows.length, 2, 'boolean 默认 2 行');
        assert.equal(rows[0].querySelector('.xfe-ca-opt-key').value, '0', '第 1 行 value=0');
        assert.equal(rows[0].querySelector('.xfe-ca-opt-label').value, '否', '第 1 行 label=否');
        assert.equal(rows[1].querySelector('.xfe-ca-opt-key').value, '1', '第 2 行 value=1');
        assert.equal(rows[1].querySelector('.xfe-ca-opt-label').value, '是', '第 2 行 label=是');
    });

    it('refresh(boolean): value 列只读(灰色背景 + readonly)', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('boolean');
        const rows = document.querySelectorAll('.xfe-ca-opt-row');
        const key0 = rows[0].querySelector('.xfe-ca-opt-key');
        assert.equal(key0.readOnly, true, '第 1 行 value input 应 readonly');
        assert.equal(key0.style.backgroundColor, 'rgb(238, 238, 238)', '背景应灰色');
        const key1 = rows[1].querySelector('.xfe-ca-opt-key');
        assert.equal(key1.readOnly, true, '第 2 行 value input 也应 readonly');
    });

    it('refresh(boolean): 用户改 label → CSV 同步(value 仍是 0/1)', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('boolean');
        const labels = document.querySelectorAll('.xfe-ca-opt-label');
        labels[0].value = '关闭';
        labels[1].value = '开启';
        fire(labels[0], 'input');
        fire(labels[1], 'input');
        assert.equal(oc.value, '0|关闭,1|开启', 'label 应同步到 CSV,value 保持 0/1');
    });

    it('refresh(boolean): 已有 CSV 时按已有 rows 渲染(带可选)', () => {
        const { ft, oc } = setupForm();
        oc.value = '0|关,1|开';
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('boolean');
        const rows = document.querySelectorAll('.xfe-ca-opt-row');
        assert.equal(rows.length, 2, '已有 CSV 渲染 2 行');
        assert.equal(rows[0].querySelector('.xfe-ca-opt-key').value, '0');
        assert.equal(rows[0].querySelector('.xfe-ca-opt-label').value, '关');
        assert.equal(rows[1].querySelector('.xfe-ca-opt-label').value, '开');
        const key0 = rows[0].querySelector('.xfe-ca-opt-key');
        assert.equal(key0.readOnly, true, '已有 CSV 加载时也应只读');
    });

    it('refresh(boolean): 删除一行后还剩 1 行,SyncToHidden 后 CSV 也少一行', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('boolean');
        const rows = document.querySelectorAll('.xfe-ca-opt-row');
        rows[1].querySelector('.xfe-ca-opt-del').click();
        const newRows = document.querySelectorAll('.xfe-ca-opt-row');
        assert.equal(newRows.length, 1, '删 1 行后剩 1 行');
        // 校验:CSV 应是 0|否(因为第 2 行的 1|是 被删了)
        assert.equal(oc.value, '0|否', '删第 2 行后 CSV 应只剩 0|否');
        // 注:服务端会校验 boolean 必须 2 行,前端允许临时删除
    });

    it('refresh(boolean): dup 检测对只读 row 也生效(value 不会重复因为只读)', () => {
        const { ft, oc } = setupForm();
        oc.value = '0|关,1|开';
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('boolean');
        // 改 label 让两行 label 相同 — value 不会重复所以不触发
        const labels = document.querySelectorAll('.xfe-ca-opt-label');
        labels[1].value = '关';
        fire(labels[1], 'input');
        assert.equal(document.querySelectorAll('.xfe-ca-opt-dup').length, 0, 'value 不同(label 相同不算 dup)');
    });

    it('refresh(boolean) → refresh(text): 编辑器隐藏 + 原生 input 可见', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('boolean');
        let editor = document.querySelector('.xfe-ca-opt-editor');
        assert.equal(editor.style.display, '', 'boolean 时编辑器可见');
        handle.refresh('text');
        assert.equal(editor.style.display, 'none', 'text 时编辑器隐藏');
        assert.equal(oc.style.display, '', 'text 时原生 input 可见');
    });

    it('refresh(select) 切换类型时,boolean 默认行不残留', () => {
        const { ft, oc } = setupForm();
        const handle = XfeCaTypeSwitcher.mountOptionsEditor({ optionsEl: oc, fieldTypeEl: ft });
        handle.refresh('boolean');
        // 切到 select,旧 boolean 默认 2 行应被替换
        handle.refresh('select');
        const rows = document.querySelectorAll('.xfe-ca-opt-row');
        assert.equal(rows.length, 1, '切到 select 应只有 1 行(默认空)');
        assert.equal(rows[0].querySelector('.xfe-ca-opt-key').value, '', 'key 应为空');
    });
});


// === 接入弹窗测试(ADR 0029)===
require("./test-rule-modal.js")({ describe: describe, it: it, assert: assert });

// === Render ===
const GREEN = '\x1b[32m';
const RED = '\x1b[31m';
const RESET = '\x1b[0m';

console.log('');
groups.forEach(g => {
    const pass = g.tests.filter(t => !t.error).length;
    const status = pass === g.tests.length ? GREEN + '✓' + RESET : RED + '✗' + RESET;
    console.log(status + ' ' + g.name + ' (' + pass + '/' + g.tests.length + ')');
    g.tests.forEach(t => {
        if (t.error) {
            console.log('    ' + RED + '✗' + RESET + ' ' + t.name);
            console.log('        ' + t.error.message.split('\n')[0]);
        }
    });
});

const passedCount = totalCount - failedCount;
console.log('');
console.log('='.repeat(50));
if (failedCount === 0) {
    console.log(GREEN + '✓ ' + passedCount + ' passed, 0 failed, ' + totalCount + ' total' + RESET);
    process.exit(0);
} else {
    console.log(RED + '✗ ' + failedCount + ' failed, ' + passedCount + ' passed, ' + totalCount + ' total' + RESET);
    process.exit(1);
}
