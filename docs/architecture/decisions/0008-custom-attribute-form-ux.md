# 0008. 自定义属性表单的 `field_type` 联动 UX

- 状态:Accepted
- 日期:2026-09-14
- 决策者:AI 助手(经用户确认)
- 关联文档:
  - [`../carrier-custom-attribute-form-types.md`](../carrier-custom-attribute-form-types.md)
  - [`../carrier-global-custom-field-defs.md`](../carrier-global-custom-field-defs.md)(§4.4.2 表 4.4)
  - [`0005-custom-field-multiselect.md`](./0005-custom-field-multiselect.md)

---

## 背景

在 `XFE_Carrier` 模块的「新增自定义属性」页面,Form block
`XFE_Carrier_Block_Adminhtml_CustomAttribute_Edit_Form` 静态创建了 10 个字段,
其中 `field_type` / `options_csv` / `default_value` 三个字段**没有任何联动**。

架构文档 `carrier-global-custom-field-defs.md` §4.4.2 已规定:
> `default_value | text(按 type 切换:multiselect 用 `|` 分隔)`

设计意图清晰,但前端没有实现,导致:
- 「候选项」对 `boolean` / `text` / `number` 类型始终显示(冗余输入)
- 「默认值」对所有类型都是普通 text 框(类型错配)
- 切到 `select` / `multiselect` 又会忘记填 `options_csv`,Service 层保存报错

---

## 决策

**新增独立 Block `XFE_Carrier_Block_Adminhtml_CustomAttribute_Edit_Form_TypeSwitcher`,
只渲染 `<script>`,在 prototype.js 层完成联动。**

### 关键约束

1. **不动 Form block** — 表单字段定义已完整,改写引入回归风险
2. **不动后端** — Service / Domain / Resource / Controller 都不动
3. **不动 layout 主结构** — 仅追加 child block 挂载

### 联动矩阵(摘要,完整版见架构文档 §4)

| `field_type` | `options_csv` 行 | `default_value` 渲染 |
|---|---|---|
| `text` | 隐藏 | text input |
| `number` | 隐藏 | text input(加 `validate-number`) |
| `select` | 显示 | `<select>`(来自 options_csv) |
| `multiselect` | 显示 | `<select multiple>`(来自 options_csv) |
| `boolean` | 隐藏 | Yes / No radio |

---

## 备选方案

### A. 在 Form.php `_prepareForm()` 末尾 `$this->setChild(...)` 内嵌 JS

- **优点**:改 1 个文件
- **缺点**:Form.php 同时承担「表单生成」与「UI 联动」两职责,违反 AGENTS.md §1「模块化」原则
- **否决**:Form block 单一职责

### B. 重写 Form.php,把所有字段按 type 分组,用 JS 整组切换

- **优点**:结构清晰
- **缺点**:重写已有稳定的表单代码,回归风险大
- **否决**:违反「小步可逆」(AGENTS.md §5.2)

### C. 服务端按 type 渲染不同字段(放弃 Form.php 的统一注册)

- **优点**:无需 JS
- **缺点**:违反 Form block 抽象,Form block 必须知道全部字段;且失去 prototype 实时切换能力
- **否决**:与 Magento 表单机制反向

### D. ✅ 新增独立 TypeSwitcher Block(本决策)

- **优点**:Form block 单一职责、增量小(2 个新文件 + layout 1 处追加)、无回归风险
- **优点**:复用 Magento prototype.js,零新依赖
- **缺点**:无
- **采纳**

---

## 后果

### 正面

- ✅ Form block 保持原状,「字段定义」职责单一
- ✅ 后端零改动,Service / Domain / Resource / Controller 完全无影响
- ✅ 编辑回显自动适配(JS 初始化时根据当前 `field_type` 跑一次联动)
- ✅ `default_value` 后端字符串契约不变,Domain `coerceValue()` 自然消化

### 负面

- ⚠️ 新增 2 个文件(1 个 Block + 1 个模板),需要项目 owner 跟进代码审查
- ⚠️ JS 用 prototype.js(老版本),不与现代前端框架兼容 — 但 Magento 1 项目统一约定如此,可接受
- ⚠️ `options_csv` 修改后,若当前 `field_type=select` / `multiselect`,默认 select 需重建 —
  已在 JS 内通过监听 `options_csv` 的 `change` 事件解决

---

## 实施检查清单

- [x] 架构文档 `carrier-custom-attribute-form-types.md` 已创建
- [x] ADR 0008 已创建(本文档)
- [ ] Block `XFE_Carrier_Block_Adminhtml_CustomAttribute_Edit_Form_TypeSwitcher` 已创建
- [ ] 模板 `type_switcher.phtml` 已创建
- [ ] layout `xfecarrier.xml` 已挂载
- [ ] `php -l` 通过
- [ ] 后台手动覆盖 5 种 `field_type`,联动行为符合预期
