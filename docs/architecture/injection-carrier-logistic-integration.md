# XFE_Carrier ↔ XFE_Logistic XML 注入集成开发指南

> 主题：通过 XFE_Injection 公共模块实现 Carrier 与 Logistic 的声明式解耦示范。
>
> 状态：Draft（2026-09-16）
> 适用范围：任何想接入 XFE_Injection 的业务模块
> 关联文档：
> - [injection-architecture.md](./injection-architecture.md)
> - [injection-api.md](./injection-api.md)
> - [injection-examples.md](./injection-examples.md)
> - [decisions/0015-injection-carrier-logistic-integration.md](./decisions/0015-injection-carrier-logistic-integration.md)

---

## 1. 目标与范围

### 1.1 业务目标

`XFE_Logistic` 在调用 GLS API 前，通过 XML 注入的方式获取 `XFE_Carrier` 维护的 GLS 账号凭据，避免：
- Logistic 直接 `Mage::getModel('xfe_carrier/credential_resolver')` 强耦合
- GLS 凭据在两个模块（Carrier 主档 + Logistic system config）中重复维护

### 1.2 本轮范围

| 项目 | 是否在本轮 |
|------|----------|
| 新增 `XFE_Carrier_Service_Account_CredentialViaInjection` 适配器 | 是 |
| 新增 `XFE_Carrier/etc/injection.xml` | 是 |
| 新增 `XFE_Logistic/etc/injection.xml` | 是 |
| Logistic `PodService.php` 新增 1 个公开方法 | 是 |
| 改动 `PodService::getProofOfDelivery` 现有逻辑 | 否 |
| 迁移 GLS system config 到 Carrier 主档 | 否 |
| 改动 GLS Gateway 构造签名 | 否 |
| 集成测试 | 是 |

---

## 2. 上游准备：XFE_Injection 公共模块

XFE_Injection 已实现并通过 71 个单元测试（见 `XFE_Injection/Test/Unit/InjectionTest.php`）。本轮集成只使用其对外静态 API：

```php
// 触发 hook（业务模块暴露入口给别人调用）
XFE_Injection_Model_Runner::trigger(\$hookName, \$context);

// 直接调用 service（不经过 hook）
XFE_Injection_Model_Runner::invoke(\$serviceId, \$methodName, \$args);
```

完整 API 见 [injection-api.md](./injection-api.md)。

---

## 3. Carrier 侧改造

### 3.1 新增适配器

**文件路径**：`app/code/community/XFE/Carrier/Service/Account/CredentialViaInjection.php`

**职责**：作为 XML 注入可识别的 service 类，内部委托给现有 `XFE_Carrier_Model_CredentialResolver`。

**为什么需要适配器**：
- `XFE_Carrier_Model_CredentialResolver` 当前构造签名是 `Mage::getSingleton()` 风格，与 XML 注入的 `ServiceLocator` 实例化约定不一致
- 适配器层显式接收参数，更适合 XML 注入的参数映射

**示例代码**（实际实施时替换占位符）：

```php
<?php
/**
 * XFE_Carrier_Service_Account_CredentialViaInjection
 *
 * XML 注入的 service 适配器。
 *
 * 内部委托给 XFE_Carrier_Model_CredentialResolver 复用现有逻辑。
 */
class XFE_Carrier_Service_Account_CredentialViaInjection
{
    public function resolveAccountId(\$carrierCode, XFE_Carrier_Model_Service_Rule_MatchContext \$context)
    {
        \$resolver = new XFE_Carrier_Model_CredentialResolver();
        return \$resolver->resolveAccountId(\$carrierCode, \$context);
    }
}
```

### 3.2 Carrier 声明 injection.xml

**文件路径**：`app/code/community/XFE/Carrier/etc/injection.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <modules>
        <XFE_Carrier>
            <version>1.0.16</version>
        </XFE_Carrier>
    </modules>
    <injection>
        <services>
            <service_carrier_resolve_account>
                <class>XFE_Carrier_Service_Account_CredentialViaInjection</class>
                <method>resolveAccountId</method>
            </service_carrier_resolve_account>
        </services>
        <hooks>
            <hook_carrier_account_resolved>
                <description>Carrier 的账号被其他模块解析时触发</description>
            </hook_carrier_account_resolved>
        </hooks>
    </injection>
</config>
```

### 3.3 版本号变更

`XFE_Carrier/etc/config.xml`：1.0.15 → 1.0.16

---

## 4. Logistic 侧改造

### 4.1 声明 injection.xml

**文件路径**：`app/code/community/XFE/Logistic/etc/injection.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <modules>
        <XFE_Logistic>
            <version>1.1.0</version>
        </XFE_Logistic>
    </modules>
    <injection>
        <hooks>
            <hook_logistic_before_request>
                <description>Logistic 准备发起第三方 API 请求前触发，用于解析账号</description>
            </hook_logistic_before_request>
        </hooks>
        <callings>
            <calling id="calling_logistic_resolve_account"
                     hook="hook_logistic_before_request"
                     service="service_carrier_resolve_account"
                     method="resolveAccountId">
                <argument name="carrierCode" from="context.carrierCode"/>
                <argument name="context"     from="context"/>
            </calling>
        </callings>
    </injection>
</config>
```

### 4.2 PodService 新增方法

**文件路径**：`app/code/community/XFE/Logistic/Service/PodService.php`

新增方法（不改动现有 `getProofOfDelivery`）：

```php
/**
 * 演示：通过 XML 注入方式解析 GLS 承运商账号。
 *
 * 这是 XFE_Injection 集成示例，与 getProofOfDelivery 共存。
 * 未来 OAuth2 凭据迁移时，本方法将被合入主流路径。
 */
public function resolveCredentialsViaInjection(\$carrierCode, XFE_Carrier_Model_Service_Rule_MatchContext \$context)
{
    \$injectionContext = new XFE_Injection_Domain_InjectionContext([
        'carrierCode' => \$carrierCode,
        'context'     => \$context,
    ]);
    \$result = XFE_Injection_Model_Runner::trigger('hook_logistic_before_request', \$injectionContext);
    return \$result->first();
}
```

注意：方法签名中 `XFE_Carrier_Model_Service_Rule_MatchContext` 仍属于弱耦合——因为这是 PHP 类型提示（PodService 必须知道方法参数类型）。完整解耦需要：
- 让 Carrier 提供公开的 value object（而不是 Service 子目录的内部类）
- 或者 Logistic 不引入类型提示（接受 array），由 Carrier 内部解析

**本轮选择前者**（保留类型提示），下一轮迭代再做完全解耦。

### 4.3 模块启用依赖

`app/etc/modules/XFE_Logistic.xml` 加 `<XFE_Injection/>` 依赖：

```xml
<depends>
    <Mage_Core/>
    <XFE_Injection/>
</depends>
```

### 4.4 版本号变更

`XFE_Logistic/etc/config.xml`：1.0.0 → 1.1.0

---

## 5. 集成测试方案

### 5.1 测试文件

`app/code/community/XFE/Injection/Test/Integration/CarrierLogisticTest.php`

使用 `Registry::resetForTesting()` 隔离状态，独立 PHP 进程运行。

### 5.2 测试用例清单

| # | 场景 | 预期 |
|---|------|------|
| 1 | 声明 Carrier service + Logistic calling → trigger hook | Carrier service 被调用，返回 accountId |
| 2 | 用 mock service 替换 Carrier | trigger 后 mock 被调用，Carrier 真实代码未跑 |
| 3 | ServiceLocator 实例缓存 | 第二次 trigger 复用同一实例 |
| 4 | 未声明 service 时 trigger | 抛 UnknownServiceException |
| 5 | 参数映射：context.carrierCode + context.context | 两个参数都正确传入 |
| 6 | ServiceLocator override | override 生效，真实实例不创建 |

---

## 6. 验证清单

### 6.1 静态检查

- `php -l` 所有新增/修改的 PHP 文件
- XML 解析无误（`simplexml_load_string` 不抛错）

### 6.2 单元测试

- `php Test/Unit/InjectionTest.php`（已存在，71 个 assertions 必须仍然全过）
- `php Test/Integration/CarrierLogisticTest.php`（新增，至少 6 个断言）

### 6.3 耦合度 grep 验证

```bash
# Logistic 不应出现 use XFE_Carrier_xxx / new XFE_Carrier_xxx
grep -rn 'use XFE_Carrier_\|new XFE_Carrier_' app/code/community/XFE/Logistic/Service/PodService.php
# 预期：仅命中 1 行（类型提示 XFE_Carrier_Model_Service_Rule_MatchContext），无 use/new
```

### 6.4 行为不变性

- 现有 `PodService::getProofOfDelivery` 调用链不变
- 现有 GLS HTTP 请求行为不变
- 旧路径 `Mage::getModel('xfe_carrier/credential_resolver')` 仍然可用（兼容层）

### 6.5 启动期检查

- `Mage::getStoreConfig('xfe_injection/general/debug')` 设为 1 后，var/log/system.log 应输出 `loaded: N hooks, M services, K callings`
- `Registry::detectRedundantCallings()` 无输出
- `Registry::validateIntegrity()` 通过

---

## 7. 风险与回滚

### 7.1 风险

| 风险 | 触发条件 | 影响 | 缓解 |
|------|---------|------|------|
| `XFE_Carrier_Model_Service_Rule_MatchContext` 是私有类 | 类名前缀下划线 | 类型提示无法满足 | 公开类或去除类型提示 |
| `CredentialResolver` 内部依赖 Mage::getSingleton | Magento 单例调用 | XML 注入路径下单例失效 | 适配器层重新拉起依赖 |
| 启动期合并 XML 抛 DuplicateHookException | 已有模块声明同名 hook | 启动失败 | 全局搜索 hook 名确认唯一 |

### 7.2 回滚方案

1. 删除 `XFE_Carrier/etc/injection.xml`
2. 删除 `XFE_Logistic/etc/injection.xml`
3. 撤销 `XFE_Logistic/Service/PodService.php` 新增方法
4. 撤销 `app/etc/modules/XFE_Logistic.xml` 的 `<XFE_Injection/>` 依赖
5. 删除 `XFE_Carrier/Service/Account/CredentialViaInjection.php`

回滚成本低：5 步，约 5 个文件，无数据库变更。

---

## 8. 后续工作（不在本轮）

1. **迁移 GLS 凭据到 Carrier 主档**：
   - 数据迁移脚本（admin_system_config_value → xfe_carrier_carrier_account）
   - 废弃 `xfe_logistic/gls_api/*` system config 字段
2. **改动 `getProofOfDelivery` 主流路径**：
   - 用 `resolveCredentialsViaInjection()` 替换硬编码 Helper 调用
3. **完全解耦 MatchContext 类型依赖**：
   - 在 `XFE_Carrier/Domain/` 下创建公开 value object
   - Logistic 改用 value object
4. **其他业务模块接入**：
   - XFE_LabelPrint（监听事件）
   - XFE_DocumentUpload（独立小作坊模式重构）
5. **更新 `carrier-facade.md` 状态**：
   - 把 §2.1 依赖金字塔改为指向 XML 注入
   - 把 §3 Facade 模型标注为已废弃，改用 XML 注入

---

## 9. 关联决策

- **ADR 0014**：引入 XFE_Injection 公共模块（已 Accepted）
- **ADR 0015**：本次 Carrier↔Logistic 集成（本文档基础）
- **carrier-facade.md**：历史 PHP 代码级 Facade（即将被 XML 注入取代）

---

## 10. 修订记录

| 日期 | 变更 | 作者 |
|------|------|------|
| 2026-09-16 | 初版：定义最小集成方案 + 实施步骤 + 测试方案 + 回滚方案 | hanson.gao + AI 助手 |
