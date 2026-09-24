# XFE_Logistic — GLS Web API 接口契约

> 状态：Accepted（2026-08-20；**2026-09-21 依据实测响应修正错误响应契约与 404 语义**）
> 数据来源：《GLS-Web-API ShipIT_Development documentation_V01-00.pdf》Collect POD 章节（第 37 页）+ 实测响应（见 `logistic-gls-pod-diagnostics.md` 第 3.8 节）。
> 本模块当前只实现 **Collect POD** 一个能力；GLS 其余端点（shipment/endofday/tracking 等）留待后续扩展。

## 1. 端点

- Test Base URL：`https://shipit-wbm-test01.gls-group.eu:443/backend/rs/tracking`
- Resource：`parcelpod`
- 完整 URL：`<base_url>/parcelpod`
- 方法：**POST**（请求带 body）
- 生产 Base URL 需在 label validation phase 后由 GLS 提供。

## 2. 请求

### 2.1 Header（所有请求）

| Header | 值 |
|--------|-----|
| `Authorization` | `Basic <Base64(username:password)>` |
| `Accept` | `application/glsVersion1+json, application/json` |
| `Content-Type` | `application/glsVersion1+json` |

### 2.2 Request Body

```json
{
  "TrackID": "GLS TrackID"
}
```

## 3. 响应

### 3.1 成功响应

```json
{
  "PODItem": {
    "TrackID": "GLS trackID",
    "ImageData": "JVBERi0xLjQKJfbk/N8KMSAwIG9iago8PAovVHlwZSAvQ2F0YWxvZwovVmVyc2lvbiAvMS40Ci9QYWdlcyAyIDAgUgo+PgplbmRvYg=="
  }
}
```

- `ImageData`：Base64 编码的 **PDF** 内容。调用方必须 `base64_decode()` 后得到原始 POD 文件字节。

### 3.2 错误处理

- 错误消息**不在 body**，而在 **HTTP 响应 header** 中读取。
- 常见状态码：

| 状态码 | 含义 | 处理策略 |
|--------|------|---------|
| 201 | Created，请求成功 | 正常返回 |
| 200 | OK，请求成功 | 正常返回 |
| 400 | Bad Request，请求结构无效 | 请求本身错误，**重试无益**；检查 TrackID 合法性/请求结构 |
| 401 | Unauthorized，认证缺失/无效 | 检查后台 Username/Password 配置 |
| 403 | Forbidden，无授权访问 | 账号无权限，联系 GLS |
| 404 | Not Found（**语义过载**） | **必须结合 `Error` header 判别**：`Error: NO_POD_IMAGE_FOUND` = 该运单无签收凭证（业务结论，**重试无益**，应提示用户，**不是**调用方请求错误）；无 `Error` 时才是端点/资源名不存在 → 检查 Base URL / 资源名配置 |
| 500 | Internal Server Error | 服务端错误，**可稍后重试** |
| 503 | Service Unavailable，过载 | 服务端错误，**可稍后重试** |

**实现要点**：
- `GlsGateway` 通过 cURL `CURLOPT_HEADERFUNCTION` 捕获响应 header，在非 2xx 时从 header 提取 GLS 错误消息。
- 非 2xx 统一抛 `XFE_Logistic_Domain_Exception_GlsApiException`，携带 HTTP 状态码。
- 上层可通过 `isClientError()`（4xx，含 400，重试无益）/ `isServerError()`（5xx，可重试）区分处理。

### 3.3 错误响应 header 契约（2026-09-21 实测确认）

失败响应 **body 为空**（`Content-Length: 0`），错误信息**全部在 header**。实测（运单 `ZWLNVW8X`，无凭证）响应为 `404 Not Found`，header 如下：

| Header（真实名，大小写不敏感） | 值示例 | 性质 | 用途 |
|------------------------------|-------|------|------|
| `Message` | `PoD (Proof of Delivery) document cannot be created for parcel with TrackID "ZWLNVW8X", it has no Signature/Geo PoD image.` | 人类可读英文，含 TrackID 插值 | 展示 / 日志 |
| **`Error`** | **`NO_POD_IMAGE_FOUND`** | **机器可读枚举码** | **判定依据（首选）** |
| `Args` | `["ZWLNVW8X"]` | JSON 数组，GLS 侧参数 | 日志 / 排障 |
| `ServerExecutionTime` | `41` | GLS 内部耗时（推测 ms） | 性能观测 |

**契约要点**：

1. `Error` 是**稳定的机器可读枚举码**，业务判定必须基于它，**不得**依赖 `Message` 的自然语言匹配（文案可能变化或本地化）。
2. `Message` 与 `Error` **用途不同**，必须**同时采集**；采用「取第一个非空 header」的单值策略会导致 `Error` 被丢弃。
3. `404` 存在双重语义，判别规则见上表 404 行。
4. 已确证的 `Error` 取值：`NO_POD_IMAGE_FOUND`。
   **完整枚举尚未取得**（权威来源为 GLS 的另一份文档 `..._API-Error-messages.pdf`，本仓库缺失）。在取得枚举前，不得假定「有 `Error` 即失败」或「只有这一个码」。
5. header 名为大小写不敏感；`lib/Zend/Http/Response.php:178` 的 `ucwords(strtolower())` 会造成 `Content-length` / `Serverexecutiontime` 这类显示形态，**不是**线上真实名。

> **排查依据**：完整取证过程见 [`logistic-gls-pod-diagnostics.md`](./logistic-gls-pod-diagnostics.md)。

## 4. 测试凭据（仅 test 环境）

| 项 | 值 |
|----|-----|
| Login/User | `glsfrtestuser` |
| Password | `iNsUWuUnLfSHOQvZCdq0` |
| Base64 | `Z2xzZnJ0ZXN0dXNlcjppTnNVV3VVbkxmU0hPUXZaQ2RxMA==` |
| ContactID | `250aaaxGp1` |

> 这些凭据仅作为**后台录入参考**，由管理员通过 `System > Configuration > XFE > Logistic (GLS)` 手工录入。
> **不得**写入模块 `etc/config.xml` 或任何 PHP 源码（AGENTS.md 禁止硬编码凭据红线）。

## 5. 实现映射

| 文档元素 | 实现位置 |
|---------|---------|
| Base URL / username / password / resource | `Helper/Data.php` + system config `xfe_logistic/gls_api/*` |
| Header 组装、Basic Auth | `Model/Print/Gls/GlsGateway.php` |
| POST + cURL 调用（捕获响应 header） | `Model/Print/Gls/GlsGateway.php` |
| 非 2xx 抛带状态码异常 | `Domain/Exception/GlsApiException.php` |
| 解析 `PODItem` + `base64_decode(ImageData)` | `Service/PodService.php` |
| 文件类型动态识别（magic bytes） | `Model/Print/Gls/GlsGateway.php::detectMimeType()` |
| MIME → 文件后缀映射 | `Domain/Constant/GlsApiConfig::$mimeToExtension` |
| 返回结构（TrackID / mime / extension / raw） | `Domain/PodResult.php` |
