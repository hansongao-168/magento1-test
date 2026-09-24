# 0019. PodService 主流路径接入 XML 注入

- 状态：Proposed
- 日期：2026-09-17
- 决策者：AI 助手（经用户确认）

## 关联文档

- [0015-injection-carrier-logistic-integration.md](./0015-injection-carrier-logistic-integration.md) — 引入 resolveAccountId service（本 ADR 是其延伸）
- [0016-decouple-match-context.md](./0016-decouple-match-context.md) — MatchContext 完全解耦（array 签名）
- [0017-documentupload-injection-integration.md](./0017-documentupload-injection-integration.md) — 旁路方法模式（本 ADR 改主流路径）
- [../decouple-podservice-main-path.md](../decouple-podservice-main-path.md) — 本 ADR 对应的开发指南

## 背景

阶段 2 引入 `XFE_Logistic_Service_PodService::resolveCredentialsViaInjection()`（旁路方法），阶段 5 改成 array 签名。
但 **6 个月过去，主流路径 `getProofOfDelivery()` 仍读 system config**：`xfe_logistic/gls_api/*`。

### 当前两条路径

| 路径 | 来源 | 备注 |
|------|------|------|
| `getProofOfDelivery()`（主流） | `xfe_logistic/gls_api/base_url` + `username` + `password` | 解密 password（假设 encrypted） |
| `resolveCredentialsViaInjection()`（旁路） | `service_carrier_resolve_account::resolveAccountId` → `account_id`（int） | 只返 id，无法直接拿来组装 URL/Auth |

### 关键差距

`resolveAccountId` 返回的 `account_id`（int）对 PodService **没有直接用处**。
PodService 需要的是：`endpoint_url`、`username`、`password` 三元组，用于组装 GLS API 完整 URL + Basic Auth。

### 当前 xfe_carrier_account 表已有完整字段

安装脚本（upgrade-1.0.0-1.0.1.php）已定义：`api_key`、`api_secret`、`username`、`password`、`endpoint_url`。
Carrier admin 表单用 `text` 存 password（明文，无 encryption backend）。

也就是说：**GLS API 凭据能装进 `xfe_carrier_account` 表，缺的只是 service 层暴露这些字段**。

## 决策

**让 `getProofOfDelivery()` 主流路径走 XML 注入，优先用 Carrier 模块账号；失败时 fallback 到 system config 旧路径**。

### 1. 新增 service：`service_carrier_get_credentials`

位置：`XFE_Carrier_Service_Account_CredentialViaInjection::getCredentialsByCarrier()`

签名：
```php
public function getCredentialsByCarrier($carrierCode, array $contextValues = array())
```

返回 `array` 或 `null`，包含字段：
- `account_id`（int）
- `carrier_id`（int）
- `username`（string|null）
- `password`（string|null）
- `endpoint_url`（string|null）
- `api_key`（string|null）
- `api_secret`（string|null）
- `used_fallback`（bool）

内部流程：
1. `_resolveCarrierIdByCode($carrierCode)` → carrier_id
2. `Rule_Resolver::instance()->resolveOne($carrierId, 'account', $ctx, $fallback=true)` → account_id
3. `Mage::getModel('xfe_carrier/carrier_account')->load($accountId)` 抽 6 个字段
4. 任一步失败返回 null

### 2. Carrier injection.xml 加新 service 声明

```xml
<service_carrier_get_credentials>
    <class>XFE_Carrier_Service_Account_CredentialViaInjection</class>
    <method>getCredentialsByCarrier</method>
</service_carrier_get_credentials>
```

### 3. Logistic injection.xml 加新 calling

复用现有 `hook_logistic_before_request`，新增 1 个 calling 引用新 service：

```xml
<calling id="calling_logistic_resolve_credentials"
         hook="hook_logistic_before_request"
         service="service_carrier_get_credentials"
         method="getCredentialsByCarrier">
    <argument name="carrierCode"   from="context.carrierCode"/>
    <argument name="contextValues" from="context.contextValues"/>
</calling>
```

`hook_logistic_before_request` 现在挂 2 个 calling，互不影响。

### 4. PodService::getProofOfDelivery 主流路径改造

新增私有方法 `_resolveCredentialsViaInjection()`，getProofOfDelivery 走注入优先 + fallback 双路径。

### 5. backward compatibility

- `Helper::getParcelPodUrl()` + `getBasicAuthHeaderValue()` 保留（被 fallback 路径调用）
- `PodService::resolveCredentialsViaInjection()`（原旁路）保留
- system config `xfe_logistic/gls_api/*` 保留

## 备选方案

### A. 注入路径 + fallback（本决策）

- 优点：渐进迁移，旧路径保留；失败兜底；测试可 mock 注入路径
- 缺点：PodService 内部路径分支略复杂
- 采纳

### B. 注入路径唯一，删除 system config

- 优点：路径单一，代码清晰
- 缺点：破坏现有 Magento 后台配置（必须先做数据迁移）
- 否决：YAGNI 渐进迁移原则违反

### C. 注入路径返完整 array，但 PodService 内不做 fallback

- 优点：代码简洁
- 缺点：未配置 Carrier 账号时整个 POD 功能挂掉
- 否决：不接住旧路径 = 风险

### D. 在 Helper 层做注入（不直接改 PodService）

- 优点：改动集中在 Helper
- 缺点：Helper 同时承担两个职责，违反单职责
- 否决

## 后果

### 正面

- PodService 主流路径 **100%** 走 XML 注入（成功时）；失败时 fallback
- Logistic 模块完全脱离对 `xfe_logistic/gls_api/*` system config 的硬依赖（成功后）
- `xfe_carrier_account` 表成为 GLS API 凭据单一来源（渐进迁移）
- 后续 OAuth2 / DocumentUpload 等模块可直接复用 `service_carrier_get_credentials`

### 负面

- PodService 内部路径分支：成功注入 / 失败 fallback（共 2 条）
- 加 1 次 DB 查询（注入路径多读一次 `xfe_carrier_account`）
- Logistic 模块新增 1 个 calling 节点 + Carrier 加 1 个 service 节点

### 后续工作

1. 数据迁移脚本：`core_config_data`（`xfe_logistic/gls_api/*`）→ `xfe_carrier_account`
2. 验证迁移完成后，删除 system config 字段
3. PodService 删 fallback 分支（最终态）
4. 删除 Helper 中无外部调用方的旧方法

## 实施检查清单

- [ ] ADR 0019（本文档）
- [ ] 开发指南 `docs/architecture/decouple-podservice-main-path.md`
- [ ] Carrier `CredentialViaInjection` 加 `getCredentialsByCarrier()`
- [ ] Carrier `etc/injection.xml` 加 `service_carrier_get_credentials`
- [ ] Logistic `etc/injection.xml` 加 `calling_logistic_resolve_credentials`
- [ ] Carrier 版本号 1.0.18 → 1.0.19
- [ ] Logistic 版本号 1.1.1 → 1.2.0
- [ ] PodService 主流路径改造（注入优先 + fallback）
- [ ] CarrierLogisticTest 加 2-3 个新 case
- [ ] PodServiceTest 加反射验证（零 XFE_Carrier_ 类型耦合）
- [ ] 累计断言 156+ 仍 0 failures

## 注意事项

- **CarrierLogisticTest 期望值**：Carrier services 数 2 → 3；callings 数 1 → 2（Logistic）
- **PodService 反射验证**：第二参数仍为 array（ADR 0016 不变）；第一参数 `getProofOfDelivery($trackId)` 不变
- **Mage 在测试环境中可能不存在**：`class_exists('Mage', false)` 防御检查；新方法内部读 DB 需 Mage，在测试中用 mock 替换
- **password 明文 vs 加密**：Carrier 表存明文（无 encryption backend）；Helper 旧路径假设 system config 加密。新路径不需解密
