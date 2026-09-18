# PodService 主流路径接入 XML 注入开发指南

> 主题：让 `XFE_Logistic_Service_PodService::getProofOfDelivery()` 主流路径走 XML 注入，
> 从 `XFE_Carrier` 模块读 GLS API 凭据；失败时 fallback 到 system config 旧路径。

> 状态：Draft（2026-09-17）

> 关联文档：
> - [decisions/0019-decouple-podservice-main-path.md](./decisions/0019-decouple-podservice-main-path.md) — 本指南对应 ADR
> - [decisions/0016-decouple-match-context.md](./decisions/0016-decouple-match-context.md) — array 签名基线
> - [decisions/0017-documentupload-injection-integration.md](./decisions/0017-documentupload-injection-integration.md) — 旁路方法模板（旁路 vs 主流对照）

---

## 1. 目标与范围

### 1.1 业务目标

让 `PodService::getProofOfDelivery()` 主流路径通过 XML 注入从 `XFE_Carrier` 拿 GLS API 凭据，
实现：
- 凭据单一来源（`xfe_carrier_account` 表）
- 与 `XFE_DocumentUpload` 共用同一 service（消除平行模块）
- 数据迁移过渡期保留 system config fallback

### 1.2 本轮范围

| 项目 | 是否在本轮 |
|------|----------|
| Carrier `CredentialViaInjection` 加 `getCredentialsByCarrier` 方法 | ✅ |
| Carrier `etc/injection.xml` 加 `service_carrier_get_credentials` | ✅ |
| Logistic `etc/injection.xml` 加 `calling_logistic_resolve_credentials` | ✅ |
| PodService 主流路径改造：注入优先 + fallback | ✅ |
| Carrier 版本 1.0.18 → 1.0.19 | ✅ |
| Logistic 版本 1.1.1 → 1.2.0 | ✅ |
| 集成测试 CarrierLogisticTest 加新 case | ✅ |
| PodServiceTest 反射验证（零 XFE_Carrier 耦合） | ✅ |
| 删除 `Helper::getParcelPodUrl/getBasicAuthHeaderValue` | ❌ 下一轮 |
| 删除 system config `xfe_logistic/gls_api/*` | ❌ 数据迁移后 |
| 数据迁移脚本（system config → xfe_carrier_account） | ❌ 下一轮 |

---

## 2. 设计要点

### 2.1 调用链

```
PodService::getProofOfDelivery($trackId)
  -> _resolveCredentialsViaInjection('gls', $ctx)
    -> XFE_Injection_Model_Runner::trigger('hook_logistic_before_request', $injCtx)
      -> 查找 2 个 calling:
         (a) calling_logistic_resolve_account   -> service_carrier_resolve_account::resolveAccountId (旧, ADR 0015)
         (b) calling_logistic_resolve_credentials -> service_carrier_get_credentials::getCredentialsByCarrier (新)
           -> XFE_Carrier_Service_Account_CredentialViaInjection
             -> Rule Resolver -> account_id
             -> load xfe_carrier_account row
             -> 返回 array{username,password,endpoint_url,...}
  -> 组装 URL + Basic Auth
  -> _gateway->requestParcelPod($trackId, $url, $auth)  [签名不变]
  -> base64 decode + detectMimeType
  -> return new PodResult(...)
```

### 2.2 新 service vs 旧 service

| 维度 | `resolveAccountId`（ADR 0015） | `getCredentialsByCarrier`（本轮新增） |
|------|------------------------------|--------------------------------------|
| 返回 | int 单个 account_id | array 完整凭据 |
| 用途 | 规则挑选 | 直接拿 API 凭据 |
| 消费方 | PodService.getProofOfDelivery（间接） | PodService.getProofOfDelivery（直接） |
| 调用频次 | 仅 `resolveCredentialsViaInjection` 旁路方法 | 主流路径（每次 POD 调用） |

两者并存不冲突：前者供未来规则场景，后者供主流凭据场景。

---

## 3. Carrier 侧改造

### 3.1 适配器新增方法

文件：`app/code/community/XFE/Carrier/Service/Account/CredentialViaInjection.php`

在已有 `listAccountsByCarrier` 之后新增 `getCredentialsByCarrier`：

```php
/**
 * 通过 carrierCode + 业务上下文，拿到完整账号凭据（明文）。
 *
 * 与 resolveAccountId（只返 id）对比：本方法返完整凭据，
 * 供 PodService 等需要直接组装 API 调用的场景。
 *
 * 单向依赖：本方法依赖 XFE_Carrier_Model_Carrier_Account（L2），
 * 不出现 use XFE_Carrier_ 业务模块。
 *
 * @param string $carrierCode   承运商 code（gls / chronopost）
 * @param array  $contextValues 业务上下文 key=>value
 * @return array|null 见 ADR 0019 §1 字段清单；无 carrier 或无账号时返回 null
 */
public function getCredentialsByCarrier($carrierCode, array $contextValues = array())
{
    $carrierCode = trim((string) $carrierCode);
    if ($carrierCode === '') {
        return null;
    }

    $carrierId = $this->_resolveCarrierIdByCode($carrierCode);
    if (!$carrierId) {
        return null;
    }

    if (!class_exists('Mage', false)) {
        return null; // 单测环境无 Mage
    }

    // 委托给 Rule Resolver 拿 account_id（与 resolveAccountId 共享同一套规则匹配逻辑）
    $matchContext = new XFE_Carrier_Model_Service_Rule_MatchContext($contextValues);
    try {
        $accountId = XFE_Carrier_Model_Service_Rule_Resolver::instance()
            ->resolveOne((int)$carrierId, XFE_Carrier_Model_Service_Rule_Resolver::TARGET_ACCOUNT, $matchContext, true);
    } catch (XFE_Carrier_Exception_NoRuleMatch $e) {
        return null; // 严格模式 fallback 失败时返 null
    }

    if (!$accountId) {
        return null;
    }

    // load 账号实体抽字段（明文）
    $account = Mage::getModel('xfe_carrier/carrier_account')->load((int)$accountId);
    if (!$account->getId()) {
        return null;
    }

    return array(
        'account_id'    => (int) $account->getId(),
        'carrier_id'    => (int) $carrierId,
        'username'      => $account->getUsername(),
        'password'      => $account->getPassword(),
        'endpoint_url'  => $account->getEndpointUrl(),
        'api_key'       => $account->getApiKey(),
        'api_secret'    => $account->getApiSecret(),
        'used_fallback' => true, // Resolver 内部已 fallback；此处只透传
    );
}
```

### 3.2 Carrier injection.xml 加新 service

```xml
<service_carrier_get_credentials>
    <class>XFE_Carrier_Service_Account_CredentialViaInjection</class>
    <method>getCredentialsByCarrier</method>
</service_carrier_get_credentials>
```

### 3.3 版本号

`XFE_Carrier/etc/config.xml`：1.0.18 → 1.0.19
`XFE_Carrier/etc/injection.xml`：同步

---

## 4. Logistic 侧改造

### 4.1 injection.xml 加新 calling

文件：`app/code/community/XFE/Logistic/etc/injection.xml`

在已有 `calling_logistic_resolve_account` 之后加：

```xml
<calling id="calling_logistic_resolve_credentials"
         hook="hook_logistic_before_request"
         service="service_carrier_get_credentials"
         method="getCredentialsByCarrier">
    <argument name="carrierCode"   from="context.carrierCode"/>
    <argument name="contextValues" from="context.contextValues"/>
</calling>
```

### 4.2 PodService 主流路径改造

文件：`app/code/community/XFE/Logistic/Service/PodService.php`

新增私有方法 `_resolveCredentialsViaInjection`，改造 `getProofOfDelivery`：
- 先调注入拿凭据 array
- 拿到且 `endpoint_url` 非空 → 用凭据组装 URL + Basic Auth
- 否则 fallback 到 `Helper::getParcelPodUrl()` + `Helper::getBasicAuthHeaderValue()`
- gateway 调用签名不变

### 4.3 版本号

`XFE_Logistic/etc/config.xml`：1.1.1 → 1.2.0
`XFE_Logistic/etc/injection.xml`：同步

---

## 5. 集成测试

### 5.1 CarrierLogisticTest 加新 case

文件：`app/code/community/XFE/Injection/Test/Integration/CarrierLogisticTest.php`

新增 1 个测试段（Test 8）：
- 验证合并 3 XML 后 `service_carrier_get_credentials` 存在
- 验证 Logistic injection.xml 含 `calling_logistic_resolve_credentials`
- 验证 calling 参数映射 (`carrierCode` + `contextValues`)
- 用 `MockCredentialsService` 替换 `service_carrier_get_credentials`，验证 PodService 主流程拿到 mock 返回的 array

### 5.2 PodServiceTest 加反射验证（新增）

文件：`app/code/community/XFE/Injection/Test/Integration/PodServiceMainPathTest.php`

测试用例：
- `_resolveCredentialsViaInjection` 内部调 Runner::trigger
- 走成功路径时，PodService 用 array 凭据组装 URL + Basic Auth（不读 system config）
- 走 fallback 路径时，PodService 调 Helper 旧方法
- 反射验证：`_resolveCredentialsViaInjection` 签名 (`string, array`)
- 反射验证：PodService 类内无 `use XFE_Carrier_*` / 无 `new XFE_Carrier_*`

---

## 6. 验证清单

### 6.1 静态检查

- 所有 PHP 文件 `php -l` 通过
- XML 解析通过（5 个 XML：Carrier config/injection + Logistic config/injection + DocumentUpload injection）
- grep 验证：PodService.php 内 `use XFE_Carrier_` / `new XFE_Carrier_` 0 处类型签名

### 6.2 单测无回归

- `php tests/php/run-tests.php`：156 + 新增 = 170+ assertions 全过

### 6.3 行为不变性

- `Helper::getParcelPodUrl()` + `getBasicAuthHeaderValue()` 仍可用（fallback 路径）
- `PodService::resolveCredentialsViaInjection()` 旁路方法仍可用
- system config `xfe_logistic/gls_api/*` 字段保留

---

## 7. 风险与回滚

### 7.1 风险

| 风险 | 缓解 |
|------|------|
| 新 service 与 `service_carrier_resolve_account` 在 hook 上冲突 | 2 个 calling 共存，Result::first() 取第一个非 null |
| PodService fallback 路径行为改变 | 旧路径完全保留代码，行为 1:1 |
| 注入路径返回 null 时 PodService 主流程挂掉 | fallback 保证 POD 不中断 |
| Carrier 表 password 明文（无 encryption backend） | 与现有 Carrier admin 表单行为一致；下一轮数据迁移时统一加密 |

### 7.2 回滚方案

1. 删除 `service_carrier_get_credentials` 注入声明
2. 删除 `calling_logistic_resolve_credentials` 节点
3. 还原 PodService `getProofOfDelivery` 为原直读 Helper 实现
4. 还原 `_resolveCredentialsViaInjection` 私有方法不存在
5. Carrier 版本号回滚

回滚成本：5 步约 5 分钟。

---

## 8. 后续工作

1. 数据迁移脚本：`core_config_data`（`xfe_logistic/gls_api/*`）→ `xfe_carrier_account`
2. 删除 system config 字段（迁移完成后）
3. PodService 删 fallback 分支（最终态）
4. 删除 `Helper::getParcelPodUrl()` + `getBasicAuthHeaderValue()`（无外部调用方时）
5. PodService 注入路径加更细 contextValues（country / weight 等，让 Rule Resolver 真正用上）

---

## 9. 修订记录

| 日期 | 变更 | 作者 |
|------|------|------|
| 2026-09-17 | 初版：PodService 主流路径接入 XML 注入最小化方案 | hanson.gao + AI 助手 |
