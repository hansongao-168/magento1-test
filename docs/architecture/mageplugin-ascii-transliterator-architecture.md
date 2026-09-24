# XFE_MagePlugin ASCII 转写适配器架构

- **模块**：XFE_MagePlugin
- **文档类型**：模块架构（AGENTS.md 4.1）
- **状态**：Proposed
- **日期**：2026-09-16
- **关联契约**：`docs/architecture/mageplugin-ascii-transliterator-api.md`
- **关联 ADR**：`docs/architecture/decisions/NNNN-migrate-to-voku-helper.md`（待补）

---

## 1. 背景与动机

现有代码 `XFE_LogisticImport_Helper_Normalizer::toAscii($text, $countryCode)` 使用 PHP `ext-intl` 的 `Transliterator` 配合 ICU 规则实现地址标准化转写。该实现在以下场景存在局限：

1. **强依赖 ext-intl**：服务器未安装 intl 扩展时直接报错，没有 PHP 层 fallback。
2. **CJK 被错误拼音化**：`Transliterator::create('Any-Latin; Latin-ASCII')` 对 `日本東京` 会输出 `Dong Jing`（汉语拼音），而非保留原文。
3. **德语 ä 输出不稳定**：`de-ASCII` 规则在 ICU 不同版本下可能输出 `a` 或 `ae`，下游业务（面单打印 vs 数据库索引）无法稳定对齐。
4. **常量散落**：`nonLatinCountries` 与国家→语言映射以硬编码形式写在 Helper 方法体内，不符合 AGENTS.md 5.2「业务阈值常量集中放」原则。
5. **架构错位**：原 Helper 跨层耦合了「按国家决定怎么转写」「调用 ICU 规则」「降级删除非可打印 ASCII」三件业务无关但策略相关的事，违反 AGENTS.md 1「模块化」「低耦合」。

本次重构把转写能力迁移到 `voku/helper` 库（Composer 包），并按 AGENTS.md 单向依赖分层重新组织到 XFE_MagePlugin 模块下。

---

## 2. 业务目标

| # | 目标 | 度量 |
|---|------|------|
| 1 | 替换原 `XFE_LogisticImport_Helper_Normalizer::toAscii` | 全部调用点迁移完毕，删除原 Helper |
| 2 | 消除 ext-intl 硬依赖 | voku PHP 数组表作为 fallback |
| 3 | 保留非拉丁语系国家原文 | 白名单语义与原代码 100% 一致 |
| 4 | 转写策略与业务调用解耦 | 所有调用点只依赖 `Api/` 接口 |
| 5 | 国家→语言映射集中管理 | 唯一真源在 `Domain/Constant/CountryLanguageMap.php` |
| 6 | 单向依赖分层落地 | 新代码严格遵循 L1→L2→L3→L4 自上而下依赖箭头 |

---

## 3. 分层映射

按 AGENTS.md 4 层单向依赖：

```
┌─────────────────────────────────────────────────────────────┐
│  L4  Controllers / Observer / CLI                              │
│      ↓ 只能调用                                                │
│  L3  Services（编排）                                          │
│      ↓ 只能调用                                                │
│  L2  Repositories / Gateways（voku 适配器落在这一层）          │
│      ↓ 只能调用                                                │
│  L1  Domain / Value Objects（CountryLanguageMap 常量类）       │
└─────────────────────────────────────────────────────────────┘
```

| 文件 | 分层 | 职责 |
|------|------|------|
| `Api/AsciiTransliteratorInterface.php` | 对外契约 | 调用方唯一依赖入口 |
| `Model/Gateway/AsciiTransliterator.php` | L2 Gateway | voku 库适配器，无业务判断 |
| `Domain/Constant/CountryLanguageMap.php` | L1 Domain | 静态常量与查询方法 |

依赖箭头方向：

- L4 → L3 → L2 → L1（允许）
- L2 → L1（允许）
- L1 ❌ 不引用任何上层类（强制，AGENTS.md 4.2）

---

## 4. 模块清单

### 4.1 新增文件

| 路径 | 类型 | 说明 |
|------|------|------|
| `app/code/community/XFE/MagePlugin/Api/AsciiTransliteratorInterface.php` | 接口 | 对外契约 |
| `app/code/community/XFE/MagePlugin/Model/Gateway/AsciiTransliterator.php` | 类 | L2 Gateway 实现 |
| `app/code/community/XFE/MagePlugin/Domain/Constant/CountryLanguageMap.php` | 类 | 映射常量集中 |

### 4.2 修改文件

**无需修改任何现有文件**。

Magento 1 默认的 Model factory 已经能解析 `xfe_mageplugin/gateway_asciiTransliterator` → `XFE_MagePlugin_Model_Gateway_AsciiTransliterator`，前提是 MagePlugin 现有 `<models><xfe_mageplugin><class>XFE_MagePlugin_Model</class></models>` 配置存在。

可参照同模块现有用法 `Mage::getModel('xfe_mageplugin/service_clientIp')` → `XFE_MagePlugin_Model_Service_ClientIp`（同样无 rewrite）。

### 4.3 依赖说明

Gateway 依赖 voku/helper ASCII 库的 `\voku\helper\ASCII::to_transliterate()` 静态方法。**voku 库的加载策略**（详见 `XFE_MagePlugin_Model_Gateway_AsciiTransliterator::_ensureVokuLoaded()`）：

- 不引入 Composer 依赖，不依赖 `vendor/autoload.php`
- 部署约定：`voku/helper/src/voku/helper/ASCII.php`（含其依赖文件）复制到 `{BP}/lib/voku/helper/ASCII.php`（BP 为 Magento 1 根目录常量）
- 加载时机：首次真实调用 `_invokeVoku()` 时，类内部 `_ensureVokuLoaded()` 静态方法同步 `require_once`
- 类可被 Magento autoloader 加载、被测试 Stub 继承，无需 voku 文件就位
- 若约定路径不存在或文件不可读，抛 `\RuntimeException`（错误信息含期望路径，便于运维定位）

---

## 5. 调用方与依赖关系

```
调用点（L3 / L4）
   │ Mage::getSingleton('xfe_mageplugin/gateway_asciiTransliterator')
   ↓
L2 Gateway（AsciiTransliterator）
   │
   ↓ 静态调用
Domain/Constant/CountryLanguageMap
   │
   ↓ 静态调用
第三方库：voku\helper\ASCII::to_transliterate
```

替换映射：

```php
// 替换前
$result = XFE_LogisticImport_Helper_Normalizer::toAscii($text, $countryCode);

// 替换后
/** @var XFE_MagePlugin_Api_AsciiTransliteratorInterface $transliterator */
$transliterator = Mage::getSingleton('xfe_mageplugin/gateway_asciiTransliterator');
$result = $transliterator->transliterate($text, $countryCode);
```

---

## 6. 关键语义对齐表

| 输入 | 国家 | 原 `toAscii` 期望 | 新 `transliterate` 输出 | 备注 |
|------|------|-------------------|-------------------------|------|
| `Müller Straße 5` | DE | `Muller Strabe 5` | `Mueller Strasse 5` | ⚠️ 策略差异：ä→a vs ä→ae |
| `北京市朝阳区` | CN | 原样 | 原样 | ✅ 白名单一致 |
| `東京タワー` | JP | 原样 | 原样 | ✅ 白名单一致 |
| `Москва` | RU | 原样 | 原样 | ✅ 白名单一致 |
| `123 Main St` | US | 原样 | 原样 | ✅ 白名单一致 |
| `Café Straße` | FR | `Cafe Strabe` | `Cafe Strasse` | ⚠️ FR 命中 `'fr'`，但内容是德语字符 |
| `Estradão` | BR | `Estradao` | `Estradao` | ✅ `pt-BR` 数组表 |
| `Müller．5`（全角句点） | DE | `Muller5` | `Muller.5` | ✅ 一致（删除发生在 voku 之后） |

### 6.1 ⚠️ 关键策略差异

原代码用 ICU `de-ASCII` 规则，`ä→a`（直接丢变音）。新实现用 voku PHP 数组表 `de` 规则，`ä→ae`（保留发音）。这不是 bug，是策略差异：

- **物流面单打印**：通常要 `ae`（收件人姓名可读性更好）
- **数据库索引/去重**：通常要 `a`（统一归一化）

业务侧需在 `docs/architecture/decisions/NNNN-migrate-to-voku-helper.md` 中明确哪种策略为默认。

---

## 7. 风险与边界

| 风险 | 影响 | 缓解 |
|------|------|------|
| voku 4.x 方法签名变更 | `to_transliterate` 在新版本可能改名 | 在 ADR 中固定 voku 版本 `^4.0` |
| 多语言国家（BE/CH/LU）业务决策 | 国家映射有歧义 | 通过系统配置覆盖（`xfe_mageplugin/ascii_transliterator/<country>_language`） |
| voku 数组表覆盖率不足 | 部分语言字符可能不转写 | 兜底用 voku 的 `use_transliterate` 参数开启 ICU fallback |
| 并发请求静态状态 | voku `$TRANSLITERATOR` 是进程共享 | 不依赖 voku 内部静态属性；调用时按请求新建 Gateway 实例 |
| 原 Helper 类残留 | 调用方未及时迁移 | 在 PR 中标注 `XFE_LogisticImport_Helper_Normalizer` 为 `@deprecated`，给 2 个 sprint 缓冲期 |
| 模块归属争议 | 用户原代码用 `XFE_LogisticImport_*` 命名但该模块不存在 | 本文档确认归属到 `XFE_MagePlugin`（用户确认） |

---

## 8. 与现有 MagePlugin 模块的兼容性

MagePlugin 模块现有 Api 接口范本：

- `Model/Sms/Gateway/SmsGatewayInterface.php`（命名：`XFE_MagePlugin_Model_Sms_Gateway_SmsGatewayInterface`）

⚠️ **风格不一致**：MagePlugin 现有 Api 接口放在 `Model/Sms/Gateway/` 下，不符合 AGENTS.md 3.1「在 `Api/` 顶级目录下定义接口」的要求。

本次新代码**严格按 AGENTS.md 强制规则**，把接口放 `Api/` 顶级目录。已存在的 `Model/Sms/Gateway/SmsGatewayInterface.php` 属于历史代码，不在本次改造范围内；后续可在单独的 PR 中迁移。

---

## 9. 落地步骤

| # | 步骤 | 状态 |
|---|------|------|
| 1 | 写架构文档（本文件） | ✅ |
| 2 | 写 API 契约文档 | ✅ |
| 3 | 创建 3 个 PHP 文件 | 待用户确认 |
| 4 | （无需操作：Magento factory 自动解析 gateway_asciiTransliterator） | — |
| 5 | `composer.json` 添加 `voku/helper` 依赖 | 待用户执行 |
| 6 | 全局替换 `XFE_LogisticImport_Helper_Normalizer::toAscii` | 待用户执行 |
| 7 | 删除或废弃原 Helper 类 | 待用户决策 |
| 8 | 写 ADR（`NNNN-migrate-to-voku-helper.md`） | 待用户决策 |

---

## 10. 关联文档

- 接口契约：`docs/architecture/mageplugin-ascii-transliterator-api.md`
- 待补 ADR：`docs/architecture/decisions/NNNN-migrate-to-voku-helper.md`
- 仓库总章程：`AGENTS.md`



