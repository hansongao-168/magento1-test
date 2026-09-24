# Carrier Facade 废弃通知 + 迁移指南

> 主题：通知并指导将 `XFE_Carrier_Model_CredentialResolver` 这套 PHP Facade 迁移到 XFE_Injection XML 注入机制。

> 状态：Active（取代 [carrier-facade.md](./carrier-facade.md) 作为推荐架构）

> 适用读者：

> - 仍在使用 `Mage::getModel('xfe_carrier/credential_resolver')` 的业务模块负责人

> - 维护 `XFE_Carrier_Model_CredentialResolver` 类的开发者

> - 计划新增"按上下文挑承运商账号"能力的模块

> 关联文档：

> - [carrier-facade.md](./carrier-facade.md) — 被取代的原始设计

> - [decisions/0014-xml-injection-mechanism.md](./decisions/0014-xml-injection-mechanism.md) — XFE_Injection 引入决策

> - [decisions/0015-injection-carrier-logistic-integration.md](./decisions/0015-injection-carrier-logistic-integration.md) — 第一个集成示范

> - [decisions/0016-decouple-match-context.md](./decisions/0016-decouple-match-context.md) — 完全解耦 MatchContext

> - [injection-carrier-logistic-integration.md](./injection-carrier-logistic-integration.md) — Carrier↔Logistic 集成开发指南

> - [decouple-match-context.md](./decouple-match-context.md) — 完全解耦开发指南


---

## 1. 重要公告


**`carrier-facade.md`（2026-08-10 版本）已废弃**。

日期：2026-09-17

原因：

1. 该文档设计的 PHP Facade（`XFE_Carrier_Model_CredentialResolver`）属于 PHP 代码级耦合，未实现"配置级别切换实现"的目标

2. 2026-09-16 引入 XFE_Injection 公共模块（ADR 0014），提供 XML 注入机制，更优雅地实现"业务模块调用跨模块 service"的需求

3. 2026-09-16 完成 Carrier↔Logistic 集成示范（ADR 0015），跑通完整调用链

4. 2026-09-17 完成 MatchContext 完全解耦（ADR 0016），达到真正的零 PHP 类型耦合


**新模块接入请使用 XFE_Injection。已有使用 Facade 的模块请按 §4 迁移。**


---

## 2. 新旧方案对比


### 2.1 调用方式


**旧方案（PHP Facade）**：

```php
$resolver = Mage::getModel('xfe_carrier/credential_resolver');
$accountId = $resolver->resolveAccountId('gls', $context);
```

**新方案（XML 注入）**：

```php
$result = XFE_Injection_Model_Runner::trigger(
    'hook_logistic_before_request',
    new XFE_Injection_Domain_InjectionContext([
        'carrierCode'   => 'gls',
        'contextValues' => ['country_code' => 'FR'],
    ])
);
$accountId = $result->first();
```


### 2.2 耦合维度对比


| 维度 | 旧方案（Facade） | 新方案（XML 注入） |
|------|---------------|------------------|
| PHP 类引用 | 必须 `Mage::getModel('xfe_carrier/...')` 知道目标模块别名 | 不需要知道目标模块别名 |
| 类型依赖 | `CredentialResolver` + `MatchContext` 两个类 | 仅 `InjectionContext`（XFE_Injection 公共模块） |
| 配置驱动 | 否（PHP 代码级） | 是（XML 声明 service/hook/calling） |
| 实现替换 | 改 PHP 代码 | 改 XML 或调用方传入 mock |
| 测试 mock | 需要启动 Magento factory | `ServiceLocator::setOverride()` 注入 |
| 可审计性 | grep `Mage::getModel` | grep `etc/injection.xml` |
| 跨模块依赖方向 | L4 → L2（直接跨层） | L4 → L3（XFE_Injection）→ L3（业务） |

---

## 3. Xml 注入替代方案完整架构


### 3.1 三层组件


**公共模块 XFE_Injection（L3）**：

- 读取各模块 `etc/injection.xml`，合并到 Registry

- 提供 `XFE_Injection_Model_Runner::trigger()` 静态入口

- 通过 ServiceLocator 解析并调用目标 service


**提供方（如 XFE_Carrier）**：

- 在 `etc/injection.xml` 声明 `<services>`

- 接收 array 形式参数（避免跨模块类型耦合）

- 内部委托给已有 service（如 Rule Resolver）


**消费方（如 XFE_Logistic）**：

- 在 `etc/injection.xml` 声明 `<hooks>` + `<callings>`

- 业务代码只调用 `Runner::trigger()`，不出现目标模块类名


### 3.2 已完成集成示范


| 角色 | 模块 | 文件 | 状态 |
|------|------|------|------|
| 提供方 | XFE_Carrier | Service/Account/CredentialViaInjection.php | ✅ 1.0.17 |
| 提供方 | XFE_Carrier | etc/injection.xml (service_carrier_resolve_account) | ✅ |
| 消费方 | XFE_Logistic | etc/injection.xml (hook_logistic_before_request + calling) | ✅ 1.1.1 |
| 消费方 | XFE_Logistic | Service/PodService.php::resolveCredentialsViaInjection | ✅ |
| 公共 | XFE_Injection | 19 个 PHP 文件，107 个测试 assertions | ✅ |

---

## 4. 迁移指南（从 Facade 改为 XML 注入）


### 4.1 迁移步骤


**Step 1：在 XFE_Carrier/etc/injection.xml 追加你的消费方需要的 service**（如果还没声明）

**Step 2：在你的模块 etc/injection.xml 声明 hook + calling**

**Step 3：把 PHP 代码中的 Facade 调用改为 Runner::trigger**


### 4.2 完整示例


迁移前（XFE_NewModule 业务代码）：

```php
$resolver = Mage::getModel('xfe_carrier/credential_resolver');
$accountId = $resolver->resolveAccountId('gls', $matchContext);
```

迁移后：

Step 1：在 XFE_NewModule/etc/injection.xml 增加

```xml
<injection>
    <hooks>
        <hook_newmodule_get_account>
            <description>NewModule 业务场景请求解析账号</description>
        </hook_newmodule_get_account>
    </hooks>
    <callings>
        <calling id="calling_newmodule_resolve_account"
                 hook="hook_newmodule_get_account"
                 service="service_carrier_resolve_account"
                 method="resolveAccountId">
            <argument name="carrierCode"   from="context.carrierCode"/>
            <argument name="contextValues" from="context.contextValues"/>
        </calling>
    </callings>
</injection>
```

Step 2：XFE_NewModule 业务代码改为

```php
$result = XFE_Injection_Model_Runner::trigger(
    'hook_newmodule_get_account',
    new XFE_Injection_Domain_InjectionContext([
        'carrierCode'   => 'gls',
        'contextValues' => ['country_code' => $country, 'package_weight' => $weight],
    ])
);
$accountId = $result->first();
```


### 4.3 grep 验证


迁移完成后，验证模块真的解耦：

```bash
grep -rn 'Mage::getModel.*xfe_carrier\|new XFE_Carrier_' app/code/community/XFE/NewModule/
# 预期: 无匹配（0 处耦合）

grep -nE 'XFE_Carrier_[A-Za-z_]+\$' app/code/community/XFE/NewModule/
# 预期: 无匹配（0 处 PHP 类型依赖）
```


---

## 5. Facade 类的去留


### 5.1 现状


严格说，`XFE_Carrier_Model_CredentialResolver` 这个具体类**不存在**——carrier-facade.md 是 2026-08-10 的设计文档，描述的是"应该这样设计"，但实际代码中：

```bash
find app/code/community/XFE -name 'CredentialResolver.php'
# 预期: 无匹配
```

实际存在的相关类：

- `XFE_Carrier_Model_Service_Rule_Resolver` —— 规则匹配的 Service（已存在）

- `XFE_Carrier_Model_Service_Registry` —— Carrier 内部 service 注册表

XML 注入的适配器 `XFE_Carrier_Service_Account_CredentialViaInjection` 已委托给 Rule_Resolver，**没有重复实现 Facade**。


### 5.2 替代而非删除


XML 注入的 `CredentialViaInjection` **不删除** Rule_Resolver：

- Rule_Resolver 仍然服务 Carrier 内部逻辑（Rule Resolver、Evaluate 等）

- CredentialViaInjection 仅作为 XML 注入的"入口适配器"（接收 array，内部转 DTO）

- 其他业务模块通过 XML 注入调用 CredentialViaInjection，不直接 new Rule_Resolver


---

## 6. 其他业务模块接入路线图


### 6.1 XFE_LabelPrint（监听事件，被动路径）


现状：监听 `xfe_labelprint_response_received` 事件，是 fire-and-forget 模型。

问题：没有返回值需求，继续用事件即可。

结论：**保持原方案，不迁移到 XML 注入**。


### 6.2 XFE_DocumentUpload（独立小作坊模式）


现状：自带 `accounts_json` system config 实现，与 XFE_Carrier 完全平行。

问题：违反 ADR 0015 §1.3 "凭据散落多处，审计困难"。

路线图：

1. 数据迁移：`admin_system_config_value` → `xfe_carrier_carrier_account`

2. 废弃 `xfe_documentupload/general/accounts_json` system config 字段

3. 新增 `XFE_DocumentUpload/etc/injection.xml`：hook + calling 调用 Carrier

4. 业务代码从 Helper 直读改为 Runner::trigger


### 6.3 XFE_OAuth2（只读 API）


现状：`XFE_OAuth2/Model/Api/Carriers.php` 只读 `xfe_carrier/carrier` 主档（filter by code/name）。

问题：API 路径与 Carrier 解耦，无业务逻辑调用。

结论：**不需要迁移**。只读主档是合理边界。


### 6.4 新模块接入


任何新建模块需要"按上下文挑账号"能力：

1. 不写 Facade，不写 Helper

2. 直接在 etc/injection.xml 声明 hook + calling

3. 业务代码 Runner::trigger

4. 测试用 ServiceLocator::setOverride mock

5. grep 验证 0 处耦合


---

## 7. 验证清单


### 7.1 全仓库 Facade 调用扫描


```bash
# 应无匹配（所有迁移后的模块都用 Runner::trigger）
grep -rn 'Mage::getModel.*credential_resolver\|Mage::helper.*credential_resolver' app/code/community/XFE/
# 预期: 0 处
```


### 7.2 全仓库 Facade 类实例化扫描


```bash
grep -rn 'new XFE_Carrier_Model_CredentialResolver\|new CredentialResolver' app/code/community/XFE/
# 预期: 0 处
```


### 7.3 XML 注入覆盖率


```bash
# 所有需要跨模块调用的模块应有 etc/injection.xml
find app/code/community/XFE -name injection.xml | sort
# 当前（2026-09-17）:
#   XFE_Carrier/etc/injection.xml
#   XFE_Demo/etc/injection.xml
#   XFE_Injection/etc/injection.xml
#   XFE_Logistic/etc/injection.xml
```


---

## 8. 修订记录


| 日期 | 变更 | 作者 |
|------|------|------|
| 2026-09-17 | 初版：废弃通知 + 新旧方案对比 + 迁移指南 + 模块路线图 | hanson.gao + AI 助手 |
