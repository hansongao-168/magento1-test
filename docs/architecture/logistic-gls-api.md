# XFE_Logistic — GLS Web API 接口契约

> 状态：Accepted（2026-08-20）
> 数据来源：《GLS-Web-API ShipIT_Development documentation_V01-00.pdf》Collect POD 章节（第 37 页）。
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
| 404 | Not Found，资源不存在 | 检查 Base URL/资源名配置 |
| 500 | Internal Server Error | 服务端错误，**可稍后重试** |
| 503 | Service Unavailable，过载 | 服务端错误，**可稍后重试** |

**实现要点**：
- `GlsGateway` 通过 cURL `CURLOPT_HEADERFUNCTION` 捕获响应 header，在非 2xx 时从 header 提取 GLS 错误消息。
- 非 2xx 统一抛 `XFE_Logistic_Domain_Exception_GlsApiException`，携带 HTTP 状态码。
- 上层可通过 `isClientError()`（4xx，含 400，重试无益）/ `isServerError()`（5xx，可重试）区分处理。

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
