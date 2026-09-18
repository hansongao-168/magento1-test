# XFE_Logistic 模块架构

> 状态：Accepted（2026-08-20）
> 依赖：`docs/architecture/logistic-gls-api.md`
> 遵循根目录 [`AGENTS.md`](../../AGENTS.md) 总章程。

## 1. 模块定位

`XFE_Logistic` 承载与 **GLS Web API** 的集成。当前首个能力为 **Proof of Delivery（POD）**：给定 GLS 运单号（TrackID），从 GLS 拉取签收凭证（POD）图片/PDF。

> 说明：GLS 相关功能此前散落在 `XFE_Carrier`（承运商主数据），本模块专注于**第三方物流 API 的实际调用**，与承运商主数据模块解耦。

## 2. 单向依赖分层

```
┌───────────────────────────────────────────────┐
│ L4  调用方（Controller / Observer / CLI）       │
│     例：后台控制器、定时任务                      │
└────────────────────┬──────────────────────────┘
                     ▼ 依赖 Service 接口
┌───────────────────────────────────────────────┐
│ L3  Service  PodService                       │  ← 业务用例编排
└────────────────────┬──────────────────────────┘
                     ▼
┌───────────────────────────────────────────────┐
│ L2  Gateway  GlsGateway                       │  ← cURL 第三方 API 适配
└────────────────────┬──────────────────────────┘
                     ▼
┌───────────────────────────────────────────────┐
│ L1  Domain  PodResult / Constant/GlsApiConfig │  ← 纯值对象，无外部依赖
└───────────────────────────────────────────────┘
```

**规则**：
- L4 只能依赖 `Api/PodApiInterface`（接口）与 `Service/PodService`。
- L3 Service 依赖 L2 Gateway 接口，不直接 `new` cURL。
- L2 Gateway 只做 HTTP 传输与响应解析，不含业务判断。
- L1 Domain 不引用任何 Magento 类。
- 禁止：L1/L2 反向调用 L3/L4；L3 `new` 其他模块 Service。

## 3. 目录结构

```
app/code/community/XFE/Logistic/
├── Api/
│   └── PodApiInterface.php        # 对外 POD 契约（仅接口）
├── Domain/
│   ├── Constant/
│   │   └── GlsApiConfig.php       # GLS API 常量（BaseURL、header、mime）
│   ├── Exception/
│   │   └── GlsApiException.php    # 带 HTTP 状态码的 GLS 异常（区分 4xx/5xx）
│   └── PodResult.php              # POD 值对象
├── Model/
│   └── Print/
│       └── Gls/
│           └── GlsGateway.php     # GLS 打印(POD) Web API cURL 适配器
├── Service/
│   └── PodService.php             # 业务用例：TrackID → PodResult
├── Helper/
│   └── Data.php                   # 配置读取、密码解密、Basic Auth 组装
└── etc/
    ├── config.xml                 # 模块声明（不写任何凭据默认值）
    └── system.xml                 # 后台 System > Config 配置项定义
```

## 4. 模块契约（对外）

| 契约 | 文件 | 说明 |
|------|------|------|
| 服务类（仅 L4 可调用） | `Mage::getModel('xfe_logistic/pod_service')` | 返回 `XFE_Logistic_Service_PodService`，`getProofOfDelivery($trackId)` |
| 接口（扩展点） | `XFE_Logistic_Api_PodApiInterface` | Gateway/Service 可实现的稳定契约 |

## 5. 配置项（后台 System > Config）

配置项**全部**通过后台管理界面（`System > Configuration > XFE > Logistic (GLS)`）维护，
由 `etc/system.xml` 定义。模块 `etc/config.xml` 中**不包含任何凭据默认值**（遵守 AGENTS.md 禁止硬编码凭据红线）。

| 后台字段 | 配置路径 | 类型 | 说明 |
|----------|---------|------|------|
| Base URL | `xfe_logistic/gls_api/base_url` | text | GLS Web API 基础地址 |
| Username | `xfe_logistic/gls_api/username` | text | Basic Auth 用户名 |
| Password | `xfe_logistic/gls_api/password` | obscure（加密存储） | Basic Auth 密码，`adminhtml/system_config_backend_encrypted` |
| Parcel POD Resource | `xfe_logistic/gls_api/parcelpod_resource` | text | Collect POD 资源名（默认 `parcelpod`） |

> **凭据安全**：密码经 encrypted backend 加密存储，`Helper/Data.php::_getDecryptedPassword()` 读取时解密。
> 测试凭据（`glsfrtestuser` / `iNsUWuUnLfSHOQvZCdq0`）仅作为后台录入参考，不写入模块文件。

## 6. 兼容性

- 本模块不依赖 `XFE_Carrier` 的数据库表。
- 可选的 `CarrierCredentialResolverInterface` 集成留给未来（本阶段不做跨模块账号挑选）。

## 7. 关联文档

- GLS Web API 契约：`docs/architecture/logistic-gls-api.md`
- 需求来源：`GLS-Web-API ShipIT_Development documentation_V01-00.pdf`（Collect POD，第 37 页）
