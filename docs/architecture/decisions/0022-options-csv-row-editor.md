# 0022. options_csv 可视化行编辑器

- 状态：Proposed
- 日期：2026-09-17
- 决策者：AI 助手
- 关联 ADR：0013（strict_editor 全面 JS 渲染）
- 关联模块：小改 F 之后的 UX 增强；属 ADR 0008（form-ux）第三阶段

## 背景

用户反馈（小改 F 收尾答疑之后）：

> 新增自定义属性如果选择下列，不是应该显示可以添加 options 数据吗？

当前 `XfeCaTypeSwitcher.bind()` 在 `field_type = select | multiselect` 时只把 `options_csv` 设为可见 + 切换 note 文案（小改 A）。`options_csv` 本身始终是 Magento `<input type="text">`，让用户手动逗号分隔。

问题：

- 用户期望"选择下列"时出现专门的"候选项编辑 UI"（key|label 动态行编辑器）
- 当前必须手输 CSV（`红,绿,蓝`）易出错（多余空白、漏分隔、Unicode 逗号误判）
- 提交时没有"key 重复 / 空 key"反馈
- multiselect 留空 = 自由标签输入（小改 C 的设计）也需要清晰告诉用户"不填就是任意标签"

## 决策

为 `options_csv` 增加可视化行编辑器（key|label 动态行），同时保留原生 `<input>` 作为 CSV 提交载体：

### 架构

1. **`Form.php` 不动**：仍输出 `<input id="options_csv" name="options_csv" type="text">`，提交语义不变
2. **JS 接管视觉**：在 options_csv 所在 `<tr>` 内动态挂入编辑器 DOM，type=select/multiselect 时**隐藏原生 input + 显示编辑器**；type=text/number/boolean 时反过来
3. **CSV ↔ 行编辑器的双向同步**：
   - 初始化时把 `options_csv` 的当前值解析为 `[{key, label}]` 行
   - 每次行变化（添加 / 删除 / 编辑 key / 编辑 label）触发同步写回隐藏 input 的 value
   - CSV 格式：`key|label,key|label,...`，label 缺省或等于 key 时压缩为 `key`
4. **复用现有迁移策略**：行变化若导致 default_value 不兼容，复用小改 D 的 `promptMigrationStrategy()` 弹窗（auto_clean / cancel）
5. **空 key 行不写入**：编辑器允许临时"空行"（用户正在添加中），但同步到 CSV 时过滤掉

### JS 公开 API（XfeCaTypeSwitcher 新增）

```
parseOptionToken(s)         string -> {key, label}        // 拆 "key|label" 或 "key"
serializeOptionPair(pair)   {key, label} -> string        // 反向（含压缩）
parseOptionsCsvToPairs(s)   string -> [{key, label}]      // 全量解析
serializePairsToCsv(pairs)  [{key, label}] -> string      // 全量序列化
mountOptionsEditor(opts)    {optionsEl, fieldTypeEl, ...} -> void   // 主入口
```

### UI 形态

每行：

```
[ key input ] [ label input ] [ 删除按钮 ]
```

底部：

```
[ + 添加候选项 ]
```

### 不做（本次）

- ❌ 拖拽排序（候选项顺序由 CSV 顺序决定；后续可加）
- ❌ key 实时重复检测（提交时通过 Service 校验即可）
- ❌ 国际化（编辑器内文字硬编码；后续可走 i18n）
- ❌ 替换 Form.php 的原生 input（保留它作为提交载体 + JS 备用路径）
- ❌ noscript fallback（JS 失败时回到 CSV 文本框；自然降级）

## 备选方案

| 方案 | 描述 | 否决理由 |
|---|---|---|
| A 预览 | 保留 CSV 输入框 + 下方实时解析预览 | 用户明确否决（用户选了 B） |
| C 完全替 | 删除 Form.php 的原生 input，强制 JS 挂载 | 破坏 noscript 兼容 + 增加 Service 单测矩阵 |
| D 弹窗编 | options_csv 旁边加"编辑候选项"按钮，弹 modal 行编辑器 | 增加弹窗跳出感，与当前"扁平表单"风格不一致 |

## 后果

- 正面：
  - 新增/编辑自定义属性时 UX 显著提升（所见即所得）
  - 复用现有 `parseOptionsCsv` / `promptMigrationStrategy`，无重复实现
  - Form.php 不动，PHP 端零回归
- 负面：
  - JS 模块体积继续增长（当前 27015 字节 → 估计 31-32 KB）
  - 多 5 个公开方法需补充单元测试
  - type_switcher.phtml DOM 结构变复杂（+10 行 HTML）

## 实施范围

| 文件 | 改动 |
|---|---|
| `js/xfe_carrier/custom-attribute-form-switcher.js` | +5 个公开方法（13 → 18） |
| `app/design/adminhtml/default/default/template/xfe_carrier/custom_attribute/edit/form/type_switcher.phtml` | +编辑器容器 DOM + 一处 init 调用 |
| `tests/js/custom-attribute-form-switcher.html` | +6 组 describe（parseOptionToken / serializeOptionPair / parseOptionsCsvToPairs / serializePairsToCsv / mountOptionsEditor 集成） |
| `docs/architecture/xfe-carrier-custom-attribute-form-switcher-js.md` | §2.14 行编辑器章节 + §8 修订记录 |
| `task_plan.md` / `progress.md` | 阶段 14 / 小改 G |

预期测试基线：JS 94 → 110+ passed，PHP 407 维持。
