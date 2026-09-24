# 0015. XFE_Carrier ↔ XFE_Logistic 通过 XML 注入解耦

- 状态：Proposed
- 日期：2026-09-16
- 决策者：hanson.gao + AI 助手
- 关联文档：
  - [injection-architecture.md](../injection-architecture.md)（XFE_Injection 公共模块架构）
  - [injection-api.md](../injection-api.md)（XFE_Injection 对外契约）
  - [injection-carrier-logistic-integration.md](../injection-carrier-logistic-integration.md)（本次集成开发指南）
  - [carrier-facade.md](../carrier-facade.md)（历史 Facade 设计）

## 背景

`carrier-facade.md`（2026-08-10）已经设计过 Carrier 的 Facade（`XFE_Carrier_Model_CredentialResolver`），目标是让 Logistic 等业务模块通过 Facade 别名 + 接口调用 Carrier 的账号挑选逻辑。

但现状：
- `XFE_Logistic/Service/PodService.php` 当前直接调用 GLS API，完全绕开 Carrier 的账号/凭据管理
- GLS API 的 Basic Auth 用户名/密码/endpoint 存在 `XFE_Logistic/etc/system.xml` 的 system config 中
- 导致 GLS 承运商信息散落在两个模块（Carrier 主档 + Logistic system config）

这违反 `carrier-facade.md` §1.3 的核心风险：凭据散落多处，审计困难。

## 决策

### 1. 引入 XFE_Injection 作为声明式 Facade

放弃硬编码 PHP Facade 别名（`Mage::getModel('xfe_carrier/credential_resolver')`），改用 XML 注入：
- **XFE_Carrier** 在 `etc/injection.xml` 声明 `service_carrier_resolve_account`
- **XFE_Logistic** 在 `etc/injection.xml` 声明 `hook_logistic_before_request` + 对应 calling
- Logistic 业务代码只调用 `XFE_Injection_Model_Runner::trigger('hook_logistic_before_request', $ctx)`

### 2. 本次改动范围（最小化）

只新增 **1 个示例方法** + 必要的 XML，不改动现有 `PodService::getProofOfDelivery` 行为：
- `XFE_Carrier/Service/Account/CredentialViaInjection.php`：适配器，把 `CredentialResolver` 包装为可注入的 service
- `XFE_Carrier/etc/injection.xml`：声明 `service_carrier_resolve_account`
- `XFE_Logistic/Service/PodService.php`：**新增 1 个 public 方法** `resolveCredentialsViaInjection($carrierCode, $context)`，演示调用方式
- `XFE_Logistic/etc/injection.xml`：声明 `hook_logistic_before_request` + 1 个 calling
- `XFE_Carrier/etc/injection.xml`：声明 `hook_carrier_account_resolved`（让 Carrier 知道被哪些 hook 触发）
- **不改 `PodService::getProofOfDelivery`**，避免破坏现有 GLS 调用链

### 3. 为什么用新方法 + 新 XML 而不是改 getProofOfDelivery

- **避免回归**：`getProofOfDelivery` 是核心路径，每天被 GLS 实际调用约 300 次
- **示范目的**：本次目标是证明 XML 注入能跑通，不是完全替换现有架构
- **渐进迁移**：先在 1 个旁路方法上验证，下次迭代再合入主流
- **保留旧路径**：`Mage::getModel('xfe_carrier/credential_resolver')` 仍然可用，作为兼容层

### 4. 参数映射设计

```xml
<calling hook="logistic_before_request"
         service="service_carrier_resolve_account"
         method="resolveAccountId">
    <argument name="carrierCode" from="context.carrierCode"/>
    <argument name="context"     from="context"/>
</calling>
```

`context` 字段承载的是 `XFE_Carrier_Model_Service_Rule_MatchContext`，需要确认它属于公开类（不是私有下划线前缀）。

### 5. 集成测试覆盖

新增 `XFE_Injection/Test/Integration/CarrierLogisticTest.php`：
- 不依赖 Magento 完整启动，使用 `Registry::resetForTesting()` 隔离状态
- 覆盖：声明注入 → 触发 hook → Carrier 的 service 被调用 → 返回正确的 accountId
- 覆盖：mock 替换（用 `ServiceLocator::setOverride()` 把 Carrier 替换为假实现）
- 覆盖：Logistic 不引用 `XFE_Carrier_*` 类（grep 验证）

## 备选方案

### 备选 A：完全按 carrier-facade.md 实施

放弃：`carrier-facade.md` 的设计是 PHP 代码级 Facade，没解决配置级别切换实现的需求；
且没有 XML 注入公共模块，本 ADR 就是在 Facade 基础上叠加声明式。

### 备选 B：在 PodService::getProofOfDelivery 内部直接接入

放弃：会改动核心路径，需要：
1. 把 GLS 凭据从 `XFE_Logistic/etc/system.xml` 迁到 `XFE_Carrier` 主档（数据迁移）
2. 改 `XFE_Logistic/etc/system.xml`（管理 UI 大改）
3. 改 GLS Gateway 的构造（接受 Carrier 解析的账号对象）

范围太大，本轮不做。

### 备选 C：用 XML 注入但保留 Mage::getModel 作为 fallback

放弃：保留 fallback = 保留耦合。本次目标是验证 XML 注入路径，旧路径会作为对照
（不进 PodService 但可以并存于 `XFE_Carrier_Model_CredentialResolver` 自身）。

## 后果

### 正面
- Logistic ↔ Carrier 调用关系从 XML 一眼可见（架构可审计性提升）
- 测试可以 mock Carrier service 而不需要启动 Magento factory
- Logistic 业务代码不再 `use XFE_Carrier_*`（低耦合验证）
- 新模块接入只需新增 XML，PHP 代码无改动（渐进迁移）

### 负面
- 增加 1 个文件 + 1 个 XML：复杂度轻微上升
- 调试调用栈更深 1 层（Runner → Locator → Service）
- 启动期多读 1 个 XML（性能开销可忽略：约 1ms）

### 缓解措施
- 集成测试覆盖完整调用链
- 调试日志（`xfe_injection/general/debug`）可选开启
- 旧路径保留，旧测试不受影响

## 实施检查清单

- [ ] Carrier 侧新增 Service 适配器 `XFE_Carrier_Service_Account_CredentialViaInjection`
- [ ] Carrier 侧 `etc/injection.xml` 声明 service
- [ ] Logistic 侧 `etc/injection.xml` 声明 hook + calling
- [ ] Logistic `PodService.php` 新增 `resolveCredentialsViaInjection()` 公开方法
- [ ] 集成测试 `CarrierLogisticTest.php`
- [ ] `php -l` 全部通过
- [ ] `progress.md` 更新阶段 2 状态

## 不在本轮范围

- 改动 `PodService::getProofOfDelivery` 现有行为
- 迁移 `XFE_Logistic/etc/system.xml` 的 GLS 凭据到 Carrier 主档
- 改动 GLS Gateway 的构造签名
- 替换其他业务模块（如 LabelPrint、DocumentUpload）对 Carrier 的调用
