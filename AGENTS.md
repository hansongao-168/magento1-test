# AGENTS.md — AI 开发守则（项目级）

> 本文件是 AI 助手（Claude / Cursor / CodeBuddy 等）在本仓库内进行**任何**改动时必须遵守的总章程。
> 业务模块级补充规则写在 `docs/architecture/` 下各自的 `*-architecture.md` 文档中，本文件优先于业务文档，业务文档不得与本文件冲突。

---

## 1. 不可妥协的开发原则

本项目所有代码改动都必须满足以下 **5 项硬性原则**，任何一项违反都必须先回到设计阶段：

| # | 原则 | 一句话定义 | 失败信号 |
|---|------|-----------|----------|
| 1 | **模块化** | 一个模块只负责一个明确的业务边界，对外暴露稳定的接口 | 出现 "Utils"、"Helpers"、"Common" 等大杂烩命名空间 |
| 2 | **低耦合** | 模块之间通过接口/事件/契约通信，禁止直接读取彼此的私有数据 | `grep -r "->_" app/code/community/XFE/` 出现跨模块私有属性访问 |
| 3 | **高内聚** | 一个模块内部的类彼此紧密协作完成同一业务目标 | 一个控制器同时处理 3 个以上不相关的业务领域 |
| 4 | **单向依赖** | 依赖箭头只允许从"上层"指向"下层"，禁止反向依赖与循环依赖 | 出现 `A -> B -> A` 或 `Model -> Block` 反向引用 |
| 5 | **文档先行** | 任何新模块、新接口、跨模块调用，必须**先**写文档，**后**写代码 | 出现没有对应架构文档的 `app/code/community/XFE/<NewModule>/` |

> **铁律**：当 5 项原则发生冲突时（如"模块化"和"复用现有实现"），**必须以原则为准停下来重新设计**，而不是绕过原则打补丁。

---

## 2. 单向依赖方向（架构骨架）

本项目采用**严格的 4 层单向依赖**。箭头方向只能自上而下：

```
┌─────────────────────────────────────────────────────────────┐
│  L4  Controllers / 外部入口（HTTP/CLI/Event Observer）         │  ← 用户交互、路由
│      ↓ 只能
│  L3  Services（用例编排、业务规则）                              │  ← 业务用例
│      ↓ 只能
│  L2  Repositories / Gateways（数据访问、第三方适配）            │  ← I/O 边界
│      ↓ 只能
│  L1  Domain / Value Objects（纯业务实体与不变量）               │  ← 无外部依赖
└─────────────────────────────────────────────────────────────┘
```

**禁止的依赖方向**：

- L1 ❌ 引用任何上层类（Model 不能 new Controller）
- L2 ❌ 调用 L3 的 Service
- L3 ❌ 直接 `new` L4 的 Controller 或读取其渲染结果
- 任何层 ❌ 直接 `new` 其他模块的 Service（必须通过接口/契约）

**Magento 1 适配**：传统 Magento 模块的 `Block/Model/Helper/Controller` 不天然符合以上分层。
新代码必须按以下映射落地：

| 分层 | Magento 1 落地形态 | 备注 |
|------|------------------|------|
| L1 Domain | `app/code/community/XFE/<Module>/Domain/*.php` | 纯 PHP 类，不继承 `Mage_*` |
| L2 Repository | `app/code/community/XFE/<Module>/Model/Repository.php` | 通过接口注入 Service |
| L2 Gateway | `app/code/community/XFE/<Module>/Model/Gateway/*.php` | 第三方 API 适配器 |
| L3 Service | `app/code/community/XFE/<Module>/Service/*.php` | 编排 Repository + Domain |
| L4 Controller | `app/code/community/XFE/<Module>/controllers/*.php` | 仅做请求/响应转换 |
| L4 Observer | `app/code/community/XFE/<Module>/Model/Observer/*.php` | 仅作为事件入口 |

---

## 3. 模块边界与契约

### 3.1 模块定义
模块是部署与命名的基本单位：`app/code/community/XFE/<Module>/`。
**模块只能通过两类方式被外部访问**：

1. **公开接口（Public API）**：在 `app/code/community/XFE/<Module>/Api/` 下定义的接口与对应实现。
2. **领域事件（Domain Event）**：通过 `Mage::dispatchEvent()` 广播的事件，载荷在文档中固化。

### 3.2 禁止的跨模块访问
- ❌ 直接 `Mage::getModel('<OtherModule>/...')`
- ❌ 直接 `Mage::helper('<OtherModule>/...')`
- ❌ 直接读取其他模块的数据库表
- ❌ `include` / `require` 其他模块的非 Api 文件

### 3.3 必须的跨模块访问方式
- ✅ 通过 `Api/*` 接口调用
- ✅ 通过事件订阅（Observer）+ 文档化的事件契约
- ✅ 通过共享的 `Domain/ValueObject` 值对象传递数据

---

## 4. AI 文档体系（强制）

### 4.1 文档位置与命名

| 文档类型 | 路径 | 命名规则 | 触发时机 |
|---------|------|---------|---------|
| 项目总章程 | `AGENTS.md`（仓库根） | 固定 | 项目初始化时一次性创建 |
| 模块架构 | `docs/architecture/<module>-architecture.md` | `<module>-architecture.md` | 新建模块前 |
| 模块契约 | `docs/architecture/<module>-api.md` | `<module>-api.md` | 新增对外接口前 |
| 事件契约 | `docs/architecture/<module>-events.md` | `<module>-events.md` | 新增 `dispatchEvent` 前 |
| 设计决策 | `docs/architecture/decisions/NNNN-<slug>.md`（ADR） | `NNNN-<slug>.md` | 出现不可逆架构决策时 |
| 任务计划 | `task_plan.md`（仓库根） | 固定 | 任何 ≥5 步的任务 |
| 调研笔记 | `findings.md`（仓库根） | 固定 | 探索代码过程中 |
| 进度日志 | `progress.md`（仓库根） | 固定 | 任务执行期间 |

### 4.2 文档先行流程

```
需求 → 模块架构文档（-architecture.md）
     → 接口/事件契约文档（-api.md / -events.md）
     → 编写代码（仅参考上述文档）
     → 更新文档（如果实现偏离设计）
     → PR 描述链接到这些文档
```

**禁止先写代码再补文档**——任何在 `app/code/community/XFE/<Module>/` 下新增的目录、类、方法，**必须**先有对应文档链接。

### 4.3 ADR（架构决策记录）

当出现以下情形时**必须**新建 `docs/architecture/decisions/` 下的 ADR：

- 引入新依赖（Composer 包、第三方 SDK）
- 跨模块共享数据
- 调整单向依赖分层（如新增第 5 层）
- 弃用现有公开接口
- 数据库 schema 破坏性变更

ADR 模板：

```markdown
# NNNN. <决策标题>

- 状态：Proposed / Accepted / Deprecated
- 日期：YYYY-MM-DD
- 决策者：<name>

## 背景
<什么迫使我们要做这个决策>

## 决策
<我们决定做什么>

## 备选方案
<考虑过的其他方案>

## 后果
- 正面：…
- 负面：…
```

### 4.4 语言要求（强制）

> 适用范围：**所有 AI 返回 / 输出 / 文档**，不限于本仓库代码注释。

1. **所有 AI 输出的内容必须使用中文**：包括对话回复、调研结论、进度日志、架构文档、接口契约、注释说明等。
2. 任何**非中文**内容（如英文 API 文档、英文搜索/检索结果、英文报错、英文原文摘录等）在写入任何 AI 文档或回复给用户前，**必须翻译成中文**再输出。
3. 术语/专有名词（如 `TrackID`、`POD`、`Base64`、类名、接口名、配置路径等）保留原文，避免歧义。
4. 若原内容为中文，则无需重复翻译，直接使用。
5. 翻译后的内容必须按第 4 节各文档类型**写入对应的 AI 文档**（如 `findings.md`、`progress.md`、`docs/architecture/*.md`），不得只停留在对话中。

---

## 5. AI 工作流强制规则

### 5.1 接到任务时
1. 先读本文件 `AGENTS.md`
2. 再读相关模块的 `docs/architecture/<module>-*.md`
3. 若文档缺失或与现状不符，**先更新文档**，再开始改动代码
4. 使用 `task_plan.md` 拆解 ≥5 步的任务

### 5.2 写代码时
1. **小步可逆**：每个 commit 独立可回退；不在一个 commit 里混合"重构+新功能+格式化"
2. **不绕开类型**：新增类必须有 `final`/`abstract` 显式声明；公开方法必须有 PHPDoc
3. **不写魔法常量**：业务阈值、状态码、错误消息常量集中放在 `Domain/Constant` 或对应模块的 `etc/constants.php`
4. **不引入循环依赖**：每次新增 `use` 语句前，确认箭头方向

### 5.3 提交前自检清单

```
[ ] 新模块/新类有对应 docs/architecture 文档
[ ] 没有出现跨模块私有访问（_ 前缀属性/方法）
[ ] 没有从 L1/L2 直接调到 L3/L4
[ ] 没有从 L3/L4 new 出其他模块的 Service
[ ] PR 描述里链接到了相关 AGENTS.md / ADR / 架构文档
[ ] 跑过 php -l 语法检查
[ ] 没有遗留 var_dump / die / echo 调试输出
```

### 5.4 禁止行为清单（红线）

- ❌ 在 `app/Mage.php` 或 `index.php` 等核心入口直接 `require`
- ❌ 修改 `app/code/core/`
- ❌ 直接修改 `app/etc/modules/*.xml` 的 `active` 为 `true` 而不写配置说明
- ❌ 在 `Block/Helper/Model` 之外的目录随意新建类
- ❌ 把数据库连接字符串、API Key 硬编码到代码里
- ❌ 提交 `.env`、`*credentials*`、个人 PDF 到 Git（参考 `.gitignore`）

---

## 6. 与现有文档的关系

| 已存在文档 | 关系 |
|-----------|------|
| `docs/architecture/carrier-facade.md` | 隶属 `XFE_Carrier` 模块，对应 `carrier-architecture.md` 风格 |
| `docs/architecture/carrier-observer-events.md` | 隶属 `XFE_Carrier` 模块，对应 `carrier-events.md` |
| `docs/architecture/carrier-line-examples.md` | 隶属 `XFE_Carrier` 模块的示例文档 |
| `findings.md` / `task_plan.md` / `progress.md` | 全局任务/调研/进度日志，AI 助手主动维护 |

未来若 `docs/architecture/<module>-*.md` 与本 `AGENTS.md` 冲突，**以本文件为准**并在 PR 中说明。

---

## 7. 修订记录

| 日期 | 变更 | 作者 |
|------|------|------|
| 2026-08-20 | 初版：定义 5 项原则、单向依赖 4 层、AI 文档体系、工作流红线 | hanson.gao |
| 2026-08-20 | 新增 4.4 语言要求（强制）：所有 AI 返回/输出必须为中文，非中文一律翻译后写入对应 AI 文档 | AI 助手 |