# XFE_Logistic — GLS Collect POD「无签收图片」排查报告

> 状态：Draft（2026-09-21，已接入实测响应）
> 类型：diagnostics（排查报告，**不含代码改动**）
> 关联文档：
> - [`logistic-architecture.md`](./logistic-architecture.md) — 模块分层
> - [`logistic-gls-api.md`](./logistic-gls-api.md) — GLS Collect POD 接口契约（本报告已修正其 404 语义）
> - [`decouple-podservice-main-path.md`](./decouple-podservice-main-path.md) — PodService 凭据来源改造
> - 需求来源：`GLS-Web-API ShipIT_Development documentation_V01-00.pdf`（Collect POD，印刷页 37）
> 遵循根目录 [`AGENTS.md`](../../AGENTS.md) 总章程。

---

## 1. 问题描述

**报告现象**：运单 `TrackID = ZWLNVW8X` 无法生成 PoD（Proof of Delivery）文档。

**报告给出的原因**：该运单没有 Signature / Geo PoD 图片。

**结论摘要（实测响应已回收，根因已确证）**：

1. **GLS 侧已确证该运单无凭证**：`POST /parcelpod` 返回 `404 Not Found`，并带机器可读错误码 **`Error: NO_POD_IMAGE_FOUND`**（见第 3.8 节实测响应）。这是业务事实，代码无法改变。
2. **真正可修的缺陷是「机器可读错误码被丢弃」**：`GlsGateway::_extractGlsErrorMessage()` 按候选顺序取**第一个**命中的 header，`message`（人类可读英文）排在 `error`（枚举码）之前，导致 `NO_POD_IMAGE_FOUND` 永远读不到，上层只能拿英文句子做字符串匹配。
3. **404 语义过载**：`404` 既可能是「端点/资源名配错」，也可能是「该运单无 PoD 图片」。现有 `logistic-gls-api.md` 把 404 单向解释为前者，会**误导排障方向**；`GlsApiException::isClientError()=true` 又会把后者误判成「请求错误，重试无益，请修正 TrackID」。
4. 仓库内 PoD 只有**唯一一条**实现链路，且**不存在 L4 消费者**（无控制器 / 后台入口 / Observer），即当前没有任何用户可达的「生成 PoD 文档」入口。
5. 本机环境**未配置 GLS 凭据**，该单无法在本地复现；本次响应由报告方在可用环境取得。

---

## 2. 排查范围与方法

| 方法 | 范围 | 结果 |
|------|------|------|
| 全仓库关键词检索 | `PoD` / `POD` / `Proof of Delivery` / `Signature` / `Geo` / `latitude` / `longitude` / `getPOD` / `podDocument` | XFE 业务代码中 `Signature` 仅命中测试里的「方法签名」语义；`Geo` / `latitude` / `longitude` **零命中** |
| PoD 相关文件枚举 | `app/code/community/XFE/**`（区分大小写 `POD`/`PoD`） | 共 15 个文件，全部属 `XFE_Logistic` + 3 个测试，**无任何 L4 层文件** |
| 实现逐行核对 | `PodService.php` / `GlsGateway.php` / `GlsApiConfig.php` / `PodResult.php` / `Helper/Data.php` | 见第 3 节 |
| **实测响应分析** | 报告方提供的 `Zend_Http_Response` 转储（`ZWLNVW8X`） | 见 3.8；**根因由此确证** |
| 框架行为核对 | `lib/Zend/Http/Response.php` header 读写 | header 键经 `ucwords(strtolower())` 规范化，见 3.8 |
| 本地数据库核对 | `core_config_data`、`xfe_carrier`、`xfe_carrier_account` | 见第 5 节 |
| 官方文档核对 | `GLS-Web-API ShipIT_Development documentation_V01-00.pdf`（42 页，`pypdf` 全文提取） | 见第 6 节 |

**未执行**：未做任何代码修改；未在本地发起真实 GLS API 调用。

---

## 3. 现状事实（含代码位置）

### 3.1 唯一调用链

```
L4  （不存在消费者）
 └─► L3  PodService::getProofOfDelivery($trackId)          Service/PodService.php:37-93
      ├─► 凭据解析：XML 注入优先，失败回退 system config     Service/PodService.php:49-63
      └─► L2  GlsGateway::requestParcelPod($trackId,$url,$auth)
                                                            Model/Print/Gls/GlsGateway.php:25-62
           └─► POST {base_url}/parcelpod  (Basic Auth)      Model/Print/Gls/GlsGateway.php:185-225
      ├─► base64_decode(ImageData) + 空值校验               Service/PodService.php:75-83
      ├─► detectMimeType(raw)  （magic bytes）              Model/Print/Gls/GlsGateway.php:74-113
      └─► return XFE_Logistic_Domain_PodResult              Service/PodService.php:88-92
```

### 3.2 PoD 值对象只有「运单号 + MIME + 原始字节」

```12:33:app/code/community/XFE/Logistic/Domain/PodResult.php
final class XFE_Logistic_Domain_PodResult
{
    /** @var string GLS 运单号 */
    private $_trackId;

    /** @var string POD 文件 MIME 类型 */
    private $_mimeType;

    /** @var string 解码后的 POD 原始字节 */
    private $_rawData;
```

**没有任何**「凭证类型（Signature / Geo / 无）」或「可用性状态」字段。上层拿到 `PodResult` 就只能理解为「拿到了文件」。

### 3.3 协议层只有一个 `ImageData`

```36:42:app/code/community/XFE/Logistic/Domain/Constant/GlsApiConfig.php
    /** @var string 响应顶层节点 */
    const RESPONSE_POD_ITEM = 'PODItem';

    /** @var string 响应 POD 项字段：运单号 */
    const RESPONSE_TRACK_ID = 'TrackID';

    /** @var string 响应 POD 项字段：Base64 图片数据 */
    const RESPONSE_IMAGE_DATA = 'ImageData';
```

GLS 文档（Collect POD 章节）同样只定义 `PODItem.{TrackID, ImageData}`。**成功响应结构不区分 Signature 图与 Geo 图**。「no Signature/Geo PoD image」只出现在**失败响应**的文本里（见 3.8），是 GLS 对凭证缺失的业务描述，不是数据结构字段。

### 3.4 当前「无凭证」的实际报错文本

**分支 A —— GLS 返回非 2xx（`ZWLNVW8X` 走这里，实测 404）**

```43:54:app/code/community/XFE/Logistic/Model/Print/Gls/GlsGateway.php
        $httpCode = (int) $response['http_code'];
        $successCodes = array($config::HTTP_OK, $config::HTTP_CREATED);
        if (!in_array($httpCode, $successCodes)) {
            // GLS 错误消息位于响应 header，而非 body（见文档 Error Messages 章节）。
            // 抛带 HTTP 状态码的异常，供上层区分 400（请求错误）/5xx（服务端错误）。
            $glsMessage = $this->_extractGlsErrorMessage($response['headers']);
            $message = $glsMessage !== ''
                ? sprintf('GLS POD 请求失败（HTTP %s）: %s', $httpCode, $glsMessage)
                : sprintf('GLS POD 请求失败（HTTP %s）', $httpCode);

            throw new XFE_Logistic_Domain_Exception_GlsApiException($message, $httpCode);
        }
```

**结合实测响应，最终抛出的异常消息为**：

```
GLS POD 请求失败（HTTP 404）: PoD (Proof of Delivery) document cannot be created for parcel with TrackID "ZWLNVW8X", it has no Signature/Geo PoD image.
```

> 注：人类可读消息**确实被成功取出**（`message` 是候选列表第 1 位且命中），因此**不是**「消息丢失」问题——问题在于**机器可读码被丢弃**（见 3.5）。

**分支 B —— GLS 返回 2xx 但 `ImageData` 缺失/为空**

```75:83:app/code/community/XFE/Logistic/Service/PodService.php
        $imageData = isset($podItem[$config::RESPONSE_IMAGE_DATA])
            ? (string) $podItem[$config::RESPONSE_IMAGE_DATA]
            : '';

        // ImageData 为 Base64 编码，解码后得到原始 POD 文件字节
        $raw = base64_decode($imageData);
        if ($raw === false || $raw === '') {
            Mage::throwException('GLS POD 返回的 ImageData 无效或为空');
        }
```

**分支 A 的问题（逐条）**：

| # | 问题 | 后果 |
|---|------|------|
| A1 | 异常类型是 `GlsApiException(404)`，且 `isClientError() === true` | 上层按 `logistic-gls-api.md` 现有契约理解为「请求错误 / 重试无益 / 检查 TrackID 合法性」——**判定完全错误**，这不是调用方的问题 |
| A2 | 唯一的机器可读码 `Error: NO_POD_IMAGE_FOUND` **被丢弃** | 上层只能用**英文自然语言句子**做字符串匹配来识别该场景，脆弱（GLS 改文案即失效）、且不利于本地化 |
| A3 | 消息前缀 `GLS POD 请求失败（HTTP 404）:` 掩盖业务语义，且中英混排 | 直接展示给用户不合适 |
| A4 | `Args` / `ServerExecutionTime` 也未采集 | 排障时缺少 GLS 侧参数与耗时 |

### 3.5 【已确证】机器可读错误码被静默丢弃

```158:175:app/code/community/XFE/Logistic/Model/Print/Gls/GlsGateway.php
        $candidates = array(
            'message',
            'errormessage',
            'error',
            'description',
            'x-glserror',
        );

        foreach ($candidates as $key) {
            if (isset($headers[$key]) && trim($headers[$key]) !== '') {
                return (string) $headers[$key];
            }
        }

        return '';
```

**实测响应下该方法的实际行为**：

| 候选序 | 候选键 | 实测 header（小写化后） | 是否命中 |
|-------|-------|----------------------|---------|
| 1 | `message` | `message` = `PoD (Proof of Delivery) document cannot be created...` | **✅ 命中并 `return`，方法结束** |
| 2 | `errormessage` | 不存在 | — |
| 3 | `error` | `error` = **`NO_POD_IMAGE_FOUND`** | ❌ **永不执行到** |
| 4 | `description` | 不存在 | — |
| 5 | `x-glserror` | 不存在 | — |

**结论**：该方法的**返回类型（单个字符串）本身就是缺陷根源**——`Message`（给人看）与 `Error`（给程序看）是两个不同用途的字段，用「取第一个非空」的单值策略必然丢掉枚举码。这是必须修正的设计问题，而非字段名猜测问题。

### 3.6 异常类型不携带业务语义

```46:59:app/code/community/XFE/Logistic/Domain/Exception/GlsApiException.php
    public function isClientError()
    {
        return $this->_httpCode >= 400 && $this->_httpCode < 500;
    }

    public function isServerError()
    {
        return $this->_httpCode >= 500 && $this->_httpCode < 600;
    }
```

只有「4xx 可重试无益 / 5xx 可重试」两个维度，**没有**「业务上不存在凭证」这一维度。上层无法用 `catch` 区分「无 PoD 图片」与「端点配错 / 认证失败 / TrackID 不存在」。

### 3.7 没有 L4 入口

`app/code/community/XFE/**` 下 PoD 相关文件共 15 个，全部位于 `XFE_Logistic` 内部 + `XFE_Injection/Test` 测试。**不存在**控制器、后台菜单/按钮、Observer。因此「PoD 文档无法创建」目前只可能来自**直接调用 Service 的脚本或外部集成**。

### 3.8 实测响应（2026-09-21，报告方提供）

报告方在可用环境对 `ZWLNVW8X` 取得原始响应：

```
HTTP/1.1 404 Not Found
Date: Mon, 21 Sep 2026 01:17:36 GMT
Content-Length: 0
Connection: close
Message: PoD (Proof of Delivery) document cannot be created for parcel with TrackID "ZWLNVW8X", it has no Signature/Geo PoD image.
ServerExecutionTime: 41
Error: NO_POD_IMAGE_FOUND
Args: ["ZWLNVW8X"]

（body 为空）
```

**header 键名的规范化说明**：报告方转储来自 `Zend_Http_Response` 的 `print_r`。`lib/Zend/Http/Response.php:178` 在存储时执行 `ucwords(strtolower($name))`，因此出现 `Content-length`、`Serverexecutiontime` 这类非常规大小写。**线上真实 header 名**为 `Message` / `Error` / `Args` / `ServerExecutionTime` / `Content-Length`（HTTP header 名本身大小写不敏感）。

**我们的网关能否读到这些字段**：`GlsGateway::_httpRequest()` 通过 `CURLOPT_HEADERFUNCTION` 捕获原始 header 行并执行 `strtolower(trim($name))`，因此两侧键名一致，可直接对齐：

| Header（真实名） | 值 | 性质 | 当前网关是否采集 |
|-----------------|-----|------|----------------|
| `Message` | 人类可读英文，含 TrackID 插值 | 展示用 | ✅ 已采集（候选第 1 位） |
| `Error` | **`NO_POD_IMAGE_FOUND`** | **机器可读枚举，判定依据** | ❌ **未采集（候选第 3 位，永不执行）** |
| `Args` | `["ZWLNVW8X"]` | JSON 数组，GLS 侧参数 | ❌ 未采集 |
| `ServerExecutionTime` | `41` | GLS 内部耗时（推测单位 ms） | ❌ 未采集 |
| `Content-Length` | `0` | 说明 body 为空 | — |
| `Connection` | `close` | 连接管理 | — |

**该响应确立的三条事实**：

1. **无凭证判定有权威依据**：`Error: NO_POD_IMAGE_FOUND` 是稳定枚举码，**不需要**对 `Message` 做关键词模糊匹配。
2. **`404` 语义过载已被证实**：既可能「资源不存在」（文档定义），也可能「无 PoD 图片」（实测）。判别依据是 **`Error` header 是否存在并等于 `NO_POD_IMAGE_FOUND`**。
3. **失败响应 body 为空**（`Content-Length: 0`），所有错误信息只在 header —— 与 `_extractGlsErrorMessage()` 的设计前提一致。

---

## 4. 根因判定（修订版）

| # | 根因 | 性质 | 证据 | 是否可修 |
|---|------|------|------|---------|
| R1 | `ZWLNVW8X` 在 GLS 侧确实无签收凭证（无 Signature、无 Geo） | 业务事实（外部依赖） | 3.8 实测 `Error: NO_POD_IMAGE_FOUND` | ❌ 无解，GLS 没有就是没有 |
| R2 | **`_extractGlsErrorMessage()` 采用「单值 + 候选顺序」策略，丢弃 `Error` 枚举码** | 实现缺陷（**主因**） | 3.5 候选表 | ✅ 可修 |
| R3 | `GlsApiException` 只有 HTTP 维度，无「业务无凭证」维度；404 被 `isClientError()` 归为调用方错误 | 实现缺陷 | 3.6 / 3.4 A1 | ✅ 可修 |
| R4 | `logistic-gls-api.md` 将 404 单向定义为「资源不存在 → 检查 Base URL/资源名配置」 | **文档缺陷（会误导排障）** | 第 6 节 | ✅ 已在本轮修正 |
| R5 | 缺失 L4 入口，无稳定的用户提示出口 | 功能缺口 | 3.7 | 需产品排期 |
| R6 | 本机无 GLS 凭据，无法本地复现 | 环境缺口 | 第 5 节 | 需配置 |

> **结论**：R1 是触发条件且**不可修复**；**R2 是本 issue「无法解释」的根本原因**，R3/R4 放大了误导性。修正 R2 **不需要任何模糊匹配或猜测**——直接用 `Error` 枚举码即可精确判定。

---

## 5. 环境核对结果（本地无法复现的依据）

| 检查项 | 命令 | 结果 |
|--------|------|------|
| Logistic system config | `SELECT path,value FROM core_config_data WHERE path LIKE '%gls%'` | **0 行**（`xfe_logistic/gls_api/*` 未配置） |
| GLS 承运商主档 | `SELECT entity_id,code,name,status FROM xfe_carrier WHERE code LIKE '%gls%'` | 存在：`entity_id=56, code=gls, name=GLS, status=1` |
| GLS API 账号 | `SELECT ... FROM xfe_carrier_account WHERE endpoint_url<>''` | **0 行** |

**推论**：本机 `PodService::getProofOfDelivery()` 会走 fallback 分支，`Helper::getParcelPodUrl()` 返回空 Base URL，请求必然失败——**与 `ZWLNVW8X` 本身的凭证有无无关**。要本地复现，必须先配置一条 `status=1` 且 `endpoint_url` 非空的 GLS 账号。

---

## 6. 官方文档核对结论

**Common Status Codes（印刷页 8）**

| Code | Text | Description（原文要点） |
|------|------|----------------------|
| 201 | Created | 请求成功 |
| 400 | Bad Request | **请求不完整或结构无效** |
| 401 | Unauthorized | 认证信息缺失或无效，可重试 |
| 403 | Forbidden | 认证成功但无权访问；账号被锁定也返回此码 |
| 404 | Not Found | **请求的资源不存在** |
| 405 | Method Not Allowed | HTTP 方法不被支持 |
| 406 | Not Acceptable | Accept 头含不支持的内容类型 |
| 415 | Media Type | Content-Type / 编码被拒 |
| 500 | Internal Server Error | 服务端处理失败 |
| 503 | Service Unavailable | API 过载，稍后重试 |

**Error Messages（印刷页 9）**

> 「When the webservice returns a response with an error message, it has to be retrieved from **the header** of the webservice response. In the appendix, you will find examples showcase possible responses to specific errors or missing parameters.」

**Help and download（印刷页 39）** 指向另一份独立文档：`GLS-Web-API-ShipIT_Development-documentation_API-Error-messages.pdf`（**本仓库缺失**，是 `Error` 码完整枚举的权威来源）。

### 6.1 【关键修正】404 的双重语义

官方文档只给出「资源不存在」一种解释，但实测（3.8）证明 404 至少有两种含义：

| 404 的实际语义 | 判别依据 | 正确处理 |
|--------------|---------|---------|
| 端点 / 资源名不存在 | 响应**无** `Error` header（或 `Error` 非 `NO_POD_IMAGE_FOUND`） | 检查 `base_url` / `parcelpod_resource` 配置 |
| **该运单无 PoD 图片** | `Error: NO_POD_IMAGE_FOUND` | **业务结论**：不可重试，应向用户明示「该运单无签收凭证」，**不是**配置或 TrackID 格式错误 |

> 该修正已同步写入 `logistic-gls-api.md`（见第 9 节待办 1）。

### 6.2 与本实现契约的差异

`logistic-gls-api.md` 第 3.2 节当前把 404 单一描述为「资源不存在 → 检查 Base URL/资源名配置」，且「实现要点」声明「非 2xx 统一抛 `GlsApiException`」。实测表明：

- 404 不能统一归入「调用方错误」；
- 错误 header 中**除人类可读消息外，还有机器可读的 `Error` 枚举码**，该契约此前完全未记录。

---

## 7. 剩余确认清单（已缩小）

**已由实测响应回答（无需再问）**

| # | 问题 | 答案 |
|---|------|------|
| A1 | 无凭证时返回什么状态码？ | `404 Not Found` |
| A2 | 是否返回 body？`PODItem` 结构？ | body 空（`Content-Length: 0`），无 `PODItem` |
| A3 | 错误消息在哪个 header？ | `Message`（人类可读）+ `Error`（枚举码） |
| A4 | 是否有专用业务码？ | 有：`Error: NO_POD_IMAGE_FOUND` |
| A5 | 消息语言与稳定性？ | 英文；（稳定性待 B5 确认） |

**仍需向 GLS 索取（阻塞精确实现的部分）**

| # | 待确认 | 为什么重要 |
|---|--------|-----------|
| B1 | `Error` header 的**完整取值枚举** | 目前只知一个值；枚举决定我们能否安全地把「有 `Error` 即业务错误」作为规则 |
| B2 | **成功响应**（200/201）是否会携带 `Error` header | 若成功响应也可能带 `Error`，则不能仅凭「有 Error」判定失败 |
| B3 | **其它错误**（400/401/403/500/503）是否也带 `Error` 码 | 决定 `_extractGlsErrorCode()` 的通用性与 4xx 分类逻辑 |
| B4 | `Error` 码与 HTTP 状态码是否为**多对一/一对多**关系 | 决定判定时应以 `Error` 为主还是状态码为主 |
| B5 | `Message` 文案是否**稳定且不随语言变化** | 决定 `Message` 能否作为兜底判定依据（若不稳，只能用于展示） |
| B6 | `Args` 数组的**语义、顺序、格式**是否稳定 | 目前仅观测到 `["ZWLNVW8X"]` |
| B7 | 无凭证是**永久**状态还是**延迟生成**？若有延迟，建议重试窗口 | 决定是否需要「稍后重试」提示 |
| B8 | 同一 TrackID 重复查询是否**幂等**（稳定返回同一结果） | 决定能否缓存/重试 |
| B9 | 是否存在**其它端点**可拿到替代凭证（Geo 坐标 / POD 扫描件） | 决定「无 Signature 时能否降级获取」 |
| B10 | 是否有**按产品/服务**决定是否生成签名的规则表 | 可从源头降低该问题发生率（下单前预判） |
| B11 | 请提供 `..._API-Error-messages.pdf` | B1/B3 的权威来源 |
| B12 | 该端点是否有**频率限制** | 影响批量补查 PoD 的设计 |

---

## 8. 判定方案建议（修订：改用精确枚举码，废弃关键词模糊匹配）

> 本轮**不实现**，仅为设计建议。落地前建议按 AGENTS.md 4.3 补一份 ADR。

### 8.1 方案变更说明

原方案基于「GLS 未提供机器可读码」的假设，设计了 `Message` 关键词模糊匹配。**实测后该假设被推翻**——GLS 已提供精确枚举码 `Error: NO_POD_IMAGE_FOUND`。因此：

- **首选判定改为精确匹配枚举码**，可靠性远高于自然语言匹配；
- 关键词模糊匹配**降级为未来兜底**（仅当 `Error` header 缺失且出现未知文案时使用），并保留反例排除规则。

### 8.2 判定优先级（修订版）

| 优先级 | 判定条件 | 数据来源 | 置信度 |
|-------|---------|---------|--------|
| **P0（首选，已确证）** | `Error` ⇔ `NO_POD_IMAGE_FOUND` | 响应 header `Error` | **高** |
| P1 | 2xx 且 `ImageData` 缺失，或 base64 解码为空 | 响应 body | 高（防御性，覆盖未观测分支） |
| P2 | `Error` 缺失时，对 `Message` 做 `(凭证类 AND 缺失类)` 组合关键词匹配 | 响应 header `Message` | 中（需 B5 确认文案稳定性） |
| P3 | `Error` 缺失且无 4xx/5xx | — | 兜底：维持 `GlsApiException` 技术异常 |

**P2 关键词候选**（仅在 P0 不可用时启用）：

- 凭证类：`pod`、`image`、`signature`、`geo`、`geolocation`、`proof`
- 缺失类：`not available`、`not found`、`no data`、`missing`、`empty`、`none`
- **必须组合命中**（`凭证类 AND 缺失类`），单个关键词命中不算，避免把「Signature service enabled」误判为缺失
- 反例排除：含 `unauthorized` / `forbidden` / `account` 的消息直接排除，交回 401/403 分类

### 8.3 实现要点（供后续落地时遵循）

| # | 要点 | 位置 | 说明 |
|---|------|------|------|
| 1 | 新增能取回**枚举码**的解析能力：`_extractGlsErrorCode()`（读 `Error`），或让 header 解析返回结构化 `{message, error, args, server_execution_time}` | `Model/Print/Gls/GlsGateway.php`（L2） | **必须放弃「取第一个非空单值」策略**（R2 主因）。注意 L2 只做解析 |
| 2 | 新增 L1 领域异常表达「业务上无凭证」（如 `PodNotAvailableException`），与 `GlsApiException`（技术失败）**分离** | `Domain/Exception/` | 修复 R3；异常需能被 `catch` 精确区分 |
| 3 | 业务分类与抛错放 L3 | `Service/PodService.php` | 遵守 `logistic-architecture.md` 第 2 节「L2 不含业务判断」 |
| 4 | 新常量集中定义 | `Domain/Constant/GlsApiConfig.php` | 至少需：`RESPONSE_HEADER_ERROR = 'Error'`、`ERROR_NO_POD_IMAGE_FOUND = 'NO_POD_IMAGE_FOUND'`、`RESPONSE_HEADER_MESSAGE`、`RESPONSE_HEADER_ARGS`。遵守 AGENTS.md 5.2「不写魔法常量」 |
| 5 | 原始 `Message` / `Error` / `Args` / `ServerExecutionTime` **全部落日志** | L2/L3 边界 | 避免 A2/A4 信息丢失，便于后续遇到未知 `Error` 码时快速取证 |
| 6 | 用户可见文案与 GLS 原文**分离** | L4 或 L3 | 避免出现 `GLS POD 请求失败（HTTP 404）: PoD (Proof of Delivery)...` 这类中英混排前缀 |
| 7 | 保留 404 的双重语义分支 | L3 | 见 6.1；无 `Error` 的 404 仍按「端点配置错误」处理 |

### 8.4 尚存的不确定性（不可忽略）

按 8.2 的 P0 落地**即可解决本 issue**；但若未取得 B1–B4，以下风险仍在：

1. `NO_POD_IMAGE_FOUND` 可能不是唯一的「无凭证」码（如未来出现 `NO_GEO_DATA`、`POD_NOT_YET_AVAILABLE`）——需要枚举才能穷举。
2. 若成功响应也可能带 `Error` header（B2），则「有 `Error` 即失败」的规则不成立，必须结合状态码判断。
3. 若 `Error` 并非在所有错误响应中出现（B3），则 `Error` 缺失时的兜底策略必须明确（走 P2 还是 P3）。

---

## 9. 后续待办（按依赖顺序）

| # | 待办 | 依赖 | 状态 |
|---|------|------|------|
| 1 | 修正 `logistic-gls-api.md`：补充错误 header 契约（`Message` / `Error` / `Args` / `ServerExecutionTime`）+ 修正 404 双重语义 | — | ✅ **本轮已完成** |
| 2 | 向 GLS 索取 B1–B12（重点 B1/B3/B11） | — | ⏳ 阻塞精确实现 |
| 3 | 在具备凭据的环境配置 GLS 账号，本地复现 `ZWLNVW8X` 并核对 header 名与大小写 | 具备凭据 | ⏳ 待做 |
| 4 | 修正 L2 解析：新增 `Error` 枚举码读取，废弃单值策略 | 3（或 2） | ⏳ 待做 |
| 5 | 新增「无凭证」领域异常，改由 L3 分类抛出 | 4 设计定稿 | ⏳ 待做 |
| 6 | 按 AGENTS.md 4.3 补 ADR（新增 L1 异常 + 错误码判定规则） | 4、5 设计定稿 | ⏳ 待做 |
| 7 | 补 `PodService` / `GlsGateway` 单测（含 404 + `NO_POD_IMAGE_FOUND` 用例） | 4、5 | ⏳ 待做 |
| 8 | 需用户可达入口时再补 L4（后台按钮 / 接口） | 产品排期 | ⏳ 待做 |
| 9 | 决策是否保留 system config fallback 凭据路径 | `decouple-podservice-main-path.md` 后续工作 | ⏳ 与本 issue 无关但影响复现步骤 |

---

## 10. 复现与验证步骤

### 10.1 隔离复现（推荐，绕过 Magento）

```bash
curl -i -sS -X POST '<base_url>/parcelpod' \
  -H 'Authorization: Basic <base64(user:pass)>' \
  -H 'Accept: application/glsVersion1+json, application/json' \
  -H 'Content-Type: application/glsVersion1+json' \
  -d '{"TrackID":"ZWLNVW8X"}'
```

**期望输出（与 3.8 一致）**：

```
HTTP/1.1 404 Not Found
Content-Length: 0
Message: PoD (Proof of Delivery) document cannot be created for parcel with TrackID "ZWLNVW8X", it has no Signature/Geo PoD image.
Error: NO_POD_IMAGE_FOUND
Args: ["ZWLNVW8X"]
```

### 10.2 端到端复现（经 Magento）

```bash
# 1) 确认凭据已就位（任一来源）
mysql -uroot -p<pass> magento1937 -e \
  "SELECT account_id,carrier_id,username,endpoint_url FROM xfe_carrier_account WHERE endpoint_url<>''; \
   SELECT path,value FROM core_config_data WHERE path LIKE 'xfe_logistic/gls_api/%';"

# 2) 调用 Service，观察异常类型与消息
php -r "require 'app/Mage.php'; Mage::app(); \
  try { Mage::getModel('xfe_logistic/pod_service')->getProofOfDelivery('ZWLNVW8X'); } \
  catch (Exception \$e) { echo get_class(\$e), ' | ', \$e->getMessage(), PHP_EOL; }"
```

**当前期望**：`XFE_Logistic_Domain_Exception_GlsApiException | GLS POD 请求失败（HTTP 404）: PoD (Proof of Delivery) document cannot be created for parcel with TrackID "ZWLNVW8X", it has no Signature/Geo PoD image.`

**修复后期望**：抛「无凭证」领域异常，消息为中文业务提示，且不归类为「调用方请求错误」。

### 10.3 对照组

| TrackID | 用途 |
|---------|------|
| `ZWLNVW8X` | 故障单（无凭证）→ 期望 `404` + `Error: NO_POD_IMAGE_FOUND` |
| 一个已妥投且有 Signature 的单 | 验证成功路径（200/201 + `PODItem.ImageData`）；**同时验证成功响应是否带 `Error`（B2）** |
| 一个格式非法的单 | 验证 400 与「无凭证」可区分，并确认 400 是否也带 `Error`（B3） |

---

## 11. 修订记录

| 日期 | 变更 | 作者 |
|------|------|------|
| 2026-09-21 | 初版：`ZWLNVW8X` 无 PoD 图片排查报告；确认真实原因未确认，给出 GLS 确认清单与关键词判定方案建议 | AI 助手 |
| 2026-09-21 | 接入实测响应（404 + `Error: NO_POD_IMAGE_FOUND`）：根因确证为「机器可读错误码被单值策略丢弃」；判定方案由关键词模糊匹配改为精确枚举码匹配；补充 404 双重语义修正；确认清单由 12 条缩减为 5 答 + 12 待；新增 L2/L3 实现要点 7 条 | AI 助手 |
