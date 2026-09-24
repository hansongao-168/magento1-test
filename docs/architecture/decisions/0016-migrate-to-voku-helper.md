# 0016. ASCII 地址转写：从 ICU Transliterator 迁移到 voku/helper

- **状态**：Accepted
- **日期**：2026-09-16
- **决策者**：hanson.gao
- **替代原方案**：`XFE_LogisticImport_Helper_Normalizer::toAscii`（参考伪代码，仓库内不存在）
- **新实现**：`XFE_MagePlugin_Model_Gateway_AsciiTransliterator`（L2 Gateway，参见 `mageplugin-ascii-transliterator-architecture.md`）

---

## 1. 背景

仓库内最初讨论的地址/TrackID 转写逻辑 `XFE_LogisticImport_Helper_Normalizer::toAscii($text, $countryCode)` 是**参考伪代码**，经全仓库搜索确认**该类并不存在**：

```
$ find . -name "*LogisticImport*" -o -name "*Normalizer*"
（无任何结果）
```

引用该伪代码的两处均位于本次新写的 PHPDoc 注释里（`CountryLanguageMap.php`、`AsciiTransliterator.php`），作为业务语义来源说明。

**业务动机**（即便原代码不存在，仍需落地新方案）：

| # | 动机 | 影响 |
|---|------|------|
| 1 | 未来地址标准化需求 | 缺统一入口，调用点会散落到 N 个 Helper/Block |
| 2 | 多语言国家策略需集中管理 | 避免 BE/CH/LU 等国家在 N 处重复映射 |
| 3 | 与现有 MagePlugin Gateway 模式对齐 | 参考 `SmsGatewayInterface` 的 L2 Gateway + Api 接口范本 |
| 4 | 业务方已认可策略矩阵（详见 §5） | 6 项决策项全部接受推荐方案 |

---

## 2. 决策

**采用 voku/helper 库作为转写引擎**，按 AGENTS.md 单向依赖分层重构到 `XFE_MagePlugin` 模块下。

- **L1 Domain**：`XFE_MagePlugin_Domain_Constant_CountryLanguageMap`（常量集中）
- **L2 Gateway**：`XFE_MagePlugin_Model_Gateway_AsciiTransliterator`（voku 适配器）
- **公开契约**：`XFE_MagePlugin_Api_AsciiTransliteratorInterface`

完整架构参见 `docs/architecture/mageplugin-ascii-transliterator-architecture.md`。

---

## 3. 备选方案

### 3.1 方案 A：维持现状（不推荐）❌

不引入新转写能力，未来按需在调用方散落实现。

| 维度 | 评价 |
|------|------|
| 改动量 | 最小 |
| **结论** | ❌ 不解决模块化、常量集中、与现有 Gateway 模式对齐的诉求 |

### 3.2 方案 B：自研 PHP 数组转写表（不推荐）❌

抛弃 voku，自己维护约 1300 条字符映射。

| 维度 | 评价 |
|------|------|
| 维护成本 | 极高 |
| **结论** | ❌ 跟 voku 内置数组表 90% 重复，不划算 |

### 3.3 方案 C：迁移到 voku/helper（✅ **采用**） | 维度 | 评价 |
|------|------|
| 改动量 | 中（替换调用点 + 加 composer 依赖） |
| 依赖 | +1 Composer 包（`voku/helper:^4.0`） |
| 风险 | voku 4.x 方法签名稳定（`to_transliterate` 自 3.x 延续） |
| **结论** | ✅ PHP 数组表 fallback、AGENTS.md 合规、与现有 MagePlugin 风格一致 |

### 3.4 方案 D：用 Symfony String 组件（不推荐）⚠️

| 维度 | 评价 |
|------|------|
| **结论** | ❌ 对 Magento 1 来说过度引入 Symfony 生态 |

---

## 4. 关键策略差异（业务方必读）

⚠️ **需要业务方明确** ICU 与 voku 的设计差异：

| 输入 | 国家 | 原 `toAscii` 假设（ICU `de-ASCII`）| 新实现（voku 数组表 `de`） | 差异说明 |
|------|------|-----------------------------------|------------------------------|----------|
| `Müller` | DE | `Muller` | `Mueller` | ⚠️ **ä→a** vs **ä→ae** |
| `Straße` | DE | `Strabe` | `Strasse` | ✅ ß→ss（结果一致） |
| `Bülow` | DE | `Bulow` | `Buelow` | ⚠️ ü→u vs ü→ue |
| `García` | ES | `Garcia` | `Garcia` | ✅ 一致 |
| `François` | FR | `Francois` | `Francois` | ✅ 一致 |
| `Łódź` | PL | `Lodz` | `Lodz` | ✅ 一致 |
| `Müller Straße 5` | DE | `Muller Strabe 5` | `Mueller Strasse 5` | 整体差异 |

### 4.1 为什么会有这个差异

- **ICU `de-ASCII; Any-Latin; Latin-ASCII`**：`de-ASCII` 规则直接丢变音（ä→a），结果短但不利于人读。
- **voku 数组表 `de`**：保留发音对（ä→ae），人读友好，是德语转写的国际惯例（DIN 5007-2）。

---

## 5. 业务方决策项（已采纳推荐）

| # | 决策项 | 采纳 | 理由 |
|---|--------|------|------|
| A | 德语默认策略 | ✅ `ä→ae` | DIN 5007-2 国际惯例、收件人姓名可读 |
| B | 比利时默认语言 | ✅ `fr` | 可通过系统配置 `xfe_mageplugin/ascii_transliterator/be_language` 覆盖 |
| C | 瑞士默认语言 | ✅ `de` | 可通过 `ch_language` 配置覆盖 |
| D | 卢森堡默认语言 | ✅ `de` | 可通过 `lu_language` 配置覆盖 |
| E | 索引归一化位置 | ✅ Service 层后处理 | Gateway 只关心人读形式，Service 用 `str_replace([ae,oe,ue,ss], [a,o,u,s])` 再归一化 |
| F | 原 Helper 废弃期 | ✅ **N/A** | 仓库内不存在原 Helper，无废弃对象 |

### 5.1 索引归一化实现示例

```php
$transliterator = Mage::getSingleton('xfe_mageplugin/gateway_asciiTransliterator');
$human = $transliterator->transliterate($address, 'DE');        // Müller → Mueller

// 索引场景：Service 层再归一化
$index = str_replace(['ae', 'oe', 'ue', 'ss'], ['a', 'o', 'u', 's'], $human);
```

这种"Gateway 输出人读形式，Service 层按场景再归一化"的分层，比把策略硬编码到 Gateway 里灵活。

### 5.2 voku 版本固定

- **固定**：`voku/helper:^4.0`
- **理由**：`ASCII::to_transliterate` 在 4.x 签名稳定；5.x 若有破坏性变更会单独评估。

---

## 6. 后果

### 6.1 正面

| # | 影响 |
|---|------|
| 1 | ✅ 消除 ext-intl 硬依赖（voku 数组表 fallback） |
| 0 | ✅ 不引入 Composer 依赖，保持 Magento 1 现有部署模式 |

| 2 | ✅ CJK 不被拼音化（白名单 22 个国家 100% 一致） |
| 3 | ✅ 字符映射表集中管理（`Domain/Constant/CountryLanguageMap.php`） |
| 4 | ✅ 符合 AGENTS.md 单向依赖分层（Api → Gateway → Domain） |
| 5 | ✅ 德语转写对人读更友好（`Müller` → `Mueller`） |
| 6 | ✅ 与现有 MagePlugin 模块风格一致（参考 `SmsGatewayInterface`） |

### 6.2 负面

| # | 影响 | 缓解 |
|---|------|------|
| 1 | ⚠️ **德语 ä→ae**，下游索引场景需重写 | Service 层 `str_replace` 后处理（§5.1） |
| 2 | ⚠️ 不引入 Composer 依赖 | voku 库通过 `require_once \'{BP}/lib/voku/helper/ASCII.php\'` 懒加载（部署路径约定由部署层保证），不依赖 `vendor/autoload.php` |
| 3 | ⚠️ voku 4.x 内部 `$TRANSLITERATOR` 静态属性在 PHP-FPM 下进程共享 | **不依赖** voku 内部静态属性；每次按请求新建 Gateway 实例 |
| 4 | ⚠️ 仓库内不存在原 Helper，文档中部分表述需修正 | 本 ADR §1 已澄清 |

---

## 7. 落地步骤

| # | 步骤 | 状态 |
|---|------|------|
| 1 | 写架构文档 `mageplugin-ascii-transliterator-architecture.md` | ✅ |
| 2 | 写 API 契约 `mageplugin-ascii-transliterator-api.md` | ✅ |
| 3 | 创建 3 个 PHP 文件（Domain/Api/Gateway） | ✅ |
| 4 | （无需修改 config.xml） | — |
| 5 | ~~composer.json 添加 voku/helper~~ | ❌ **不适用**：正式站不引入 Composer |
| 6 | ~~全局替换调用点~~ | ❌ **不适用**：仓库内不存在原 Helper |
| 7 | ~~给原 Helper 加 @deprecated~~ | ❌ **不适用**：同上 |
| 8 | 写单元测试 `MagePlugin/Test/Gateway/AsciiTransliteratorTest.php` | ⏳ 待业务方决策 |

---

## 8. 关联文档

- 架构文档：`docs/architecture/mageplugin-ascii-transliterator-architecture.md`
- 接口契约：`docs/architecture/mageplugin-ascii-transliterator-api.md`
- 仓库总章程：`AGENTS.md`
- MagePlugin 现有 Gateway 接口范本：`app/code/community/XFE/MagePlugin/Model/Sms/Gateway/SmsGatewayInterface.php`
