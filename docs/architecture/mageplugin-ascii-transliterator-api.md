# XFE_MagePlugin ASCII 转写适配器接口契约

- **模块**：XFE_MagePlugin
- **文档类型**：接口契约（AGENTS.md 4.1）
- **状态**：Proposed
- **日期**：2026-09-16
- **关联架构**：`docs/architecture/mageplugin-ascii-transliterator-architecture.md`

---

## 1. 接口定位

`XFE_MagePlugin_Api_AsciiTransliteratorInterface` 是 XFE_MagePlugin 模块对外提供的 ASCII 转写能力唯一入口。任何调用方（L3 Service / L4 Controller / Observer / CLI）必须**只依赖此接口**，不直接 `new` 具体 Gateway、不直接调用 voku 库。

接口路径：`app/code/community/XFE/MagePlugin/Api/AsciiTransliteratorInterface.php`

---

## 2. 完整方法签名

```php
<?php

interface XFE_MagePlugin_Api_AsciiTransliteratorInterface
{
    /**
     * 根据国家上下文把文本转写为 ASCII 形式。
     *
     * @param string $text        待转写文本（UTF-8）
     * @param string $countryCode ISO 3166-1 alpha-2 国家代码（大写或小写均可，内部 strtoupper 规范化）
     * @return string ASCII 化后的文本
     */
    public function transliterate($text, $countryCode);
}
```

---

## 3. 参数契约

### 3.1 `$text`

| 属性 | 约束 |
|------|------|
| 类型 | string |
| 编码 | UTF-8 |
| 允许空字符串 | ✅ 是，返回空字符串 |
| 允许 null | ❌ 否；调用前需自行转换为 `''` |
| 允许含控制字符 | ✅ 是，会在最终阶段被静默删除 |

### 3.2 `$countryCode`

| 属性 | 约束 |
|------|------|
| 类型 | string |
| 格式 | ISO 3166-1 alpha-2（如 `DE`、`CN`、`BR`） |
| 大小写 | 不敏感，内部 `strtoupper` 规范化 |
| 允许空字符串 | ✅ 是，按未命中映射表处理 |
| 允许 null | ❌ 否；调用前需自行转换为 `''` |
| 未在映射表中 | 走 voku `en` fallback 规则 |

---

## 4. 返回契约

返回值必须满足以下**全部**约束：

| # | 约束 | 测试方法 |
|---|------|----------|
| 1 | 仅含可打印 ASCII（0x20-0x7E） | `preg_match('/[^\x20-\x7E]/', $result) === 0` |
| 2 | 命中非拉丁白名单时与输入完全一致 | `assert($result === $text)` |
| 3 | 纯 ASCII 输入与输入完全一致 | `assert($result === $text)` |
| 4 | 非空输入产生非空输出 | `strlen($result) > 0` 或与输入等长 |

---

## 5. 实现约束（强制）

任何实现 `XFE_MagePlugin_Api_AsciiTransliteratorInterface` 的类都必须满足以下全部约束：

1. **非拉丁语系白名单跳过**：当 `$countryCode` 命中以下 22 个国家代码时，**原样返回 `$text`**，不触发任何转写：
   - CJK：`CN`、`TW`、`HK`、`MO`、`JP`、`KR`、`KP`
   - 西里尔：`RU`、`UA`、`BY`、`BG`
   - 东南亚/南亚：`TH`、`VN`、`IN`
   - 阿拉伯中东：`AE`、`SA`、`QA`、`EG`
   - 纯英语国家：`US`、`GB`、`AU`、`NZ`、`IE`
2. **纯 ASCII 短路**：当 `$text` 仅含 `U+0000..U+007F` 时，原样返回。
3. **降级语义**：转写后所有非可打印 ASCII（`0x20-0x7E` 之外）字符**被静默删除**（不是替换成 `?`）。
4. **不发起 I/O**：不读写数据库、不调用 HTTP、不写文件。
5. **不写日志**：调用方负责日志，Gateway 只负责纯函数式转写。
6. **不可变**：同一实例的多次调用不应相互影响（不持有跨调用的状态）。

---

## 6. 错误处理

| 情况 | 行为 |
|------|------|
| `$text` 为空字符串 | 返回空字符串（不抛异常） |
| `$countryCode` 为空字符串 | 走 fallback 规则（voku `en`），原样返回若是空文本 |
| voku 库未部署到约定路径 | 抛 `\RuntimeException`（在 `XFE_MagePlugin_Model_Gateway_AsciiTransliterator::_invokeVoku()` 首次被真实调用时触发，错误信息含期望路径 `{BP}/lib/voku/helper/ASCII.php`） |
| ext-intl 未安装 | 不抛异常（voku 用 PHP 数组表 fallback） |
| 转写过程中未知异常 | 沿用 PHP 默认未捕获异常行为（抛 `\Exception` / `\Error`） |

---

## 7. 性能特征（参考值）

| 操作 | 期望耗时 |
|------|----------|
| 纯 ASCII 短路 | < 1 μs |
| 白名单跳过 | < 1 μs |
| voku 转写（短字符串 < 100 字符） | < 1 ms |
| voku 转写（长字符串 > 1000 字符） | < 10 ms |

---

## 8. 版本演进

接口语义变更必须：

1. 先更新本文档 + `mageplugin-ascii-transliterator-architecture.md`
2. 写 ADR 说明变更理由
3. 保留旧实现至少 1 个 sprint（双轨并行）

| 版本 | 变更 | 日期 |
|------|------|------|
| 1.0.0 | 初版：`transliterate($text, $countryCode)` | 2026-09-16 |

---

## 9. 测试用例

测试文件位置：`app/code/community/XFE/MagePlugin/Test/Gateway/AsciiTransliteratorTest.php`

### 9.1 白名单跳过

| 输入 | 国家 | 期望输出 |
|------|------|----------|
| `北京市朝阳区` | `CN` | `北京市朝阳区` |
| `東京タワー` | `JP` | `東京タワー` |
| `Москва` | `RU` | `Москва` |
| `القاهرة` | `EG` | `القاهرة` |
| `123 Main St` | `US` | `123 Main St` |

### 9.2 纯 ASCII 短路

| 输入 | 国家 | 期望输出 |
|------|------|----------|
| `Hello World` | `DE` | `Hello World` |
| `12345` | `DE` | `12345` |
| （空字符串） | `DE` | （空字符串） |

### 9.3 德语转写（voku 数组表）

| 输入 | 国家 | 期望输出 |
|------|------|----------|
| `Müller Straße 5` | `DE` | `Mueller Strasse 5` |
| `Müller．5`（全角句点） | `DE` | `Muller.5` |

### 9.4 法语转写

| 输入 | 国家 | 期望输出 |
|------|------|----------|
| `Café Crème` | `FR` | `Cafe Creme` |
| `Naïve` | `FR` | `Naive` |

### 9.5 未命中映射表

| 输入 | 国家 | 期望输出 |
|------|------|----------|
| `Müller` | `SG` | `Muller`（voku `en` fallback） |
| `Müller` | `XX` | `Muller`（fallback） |
| `Café` | `''` | `Cafe`（fallback） |

---

## 10. 关联文档

- 架构文档：`docs/architecture/mageplugin-ascii-transliterator-architecture.md`
- 仓库总章程：`AGENTS.md`
- 待补 ADR：`docs/architecture/decisions/NNNN-migrate-to-voku-helper.md`
