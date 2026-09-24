# 0016. 完全解耦 Carrier MatchContext 类型依赖

- 状态：Proposed
- 日期：2026-09-17
- 决策者：hanson.gao + AI 助手
- 关联文档：
  - [injection-carrier-logistic-integration.md](../injection-carrier-logistic-integration.md)
  - [decisions/0015-injection-carrier-logistic-integration.md](./0015-injection-carrier-logistic-integration.md)

## 背景

ADR 0015 把 `PodService::resolveCredentialsViaInjection()` 作为最小化集成示范，其方法签名用了 PHP 类型提示：

```php
public function resolveCredentialsViaInjection(
    $carrierCode,
    XFE_Carrier_Model_Service_Rule_MatchContext $context  // ← PHP 级耦合
)
```

虽然 Carrier 类名没出现在 `use` 语句里（PHP 类型提示不需要 use），但 PHP 类型检查机制强制 Logistic 代码"知道"这个类的存在：

- 在 IDE 自动补全中会列出该类
- 类型签名出现在 public 方法签名里（接口契约）
- 任何重构该类名的操作都会影响 Logistic 的语义
- 单测无法用 stdClass/array 替代（会被 PHP 拒绝）

这违反 ADR 0015 标榜的"零代码耦合"原则。

## 决策

### 1. XML 注入的 service 接口签名改为 `array`

把 `XFE_Carrier_Service_Account_CredentialViaInjection::resolveAccountId` 的第二个参数类型从 `XFE_Carrier_Model_Service_Rule_MatchContext` 改为原生 `array`：

```php
public function resolveAccountId(
    $carrierCode,
    array $contextValues,           // ← 原生 array,Logistic 端零类型依赖
    $fallback = true
)
```

### 2. Carrier 适配器内部把 array 转为 MatchContext

适配器内部做 array → MatchContext 的转换：

```php
$matchContext = new XFE_Carrier_Model_Service_Rule_MatchContext($contextValues);
$result = $resolver->resolve((int)$carrierId, $matchContext, (bool)$fallback);
```

`XFE_Carrier_Model_Service_Rule_MatchContext` 保持不变（已有大量内部调用方），仅在新 XML 注入适配器层做转换。

### 3. Logistic 端构造 array

```php
public function resolveCredentialsViaInjection($carrierCode, array $contextValues)
{
    $injectionContext = new XFE_Injection_Domain_InjectionContext([
        'carrierCode'    => (string) $carrierCode,
        'contextValues'  => $contextValues,    // array,任意 key 透传给 MatchContext
    ]);
    $result = XFE_Injection_Model_Runner::trigger(
        'hook_logistic_before_request', $injectionContext
    );
    return $result->first();
}
```

### 4. injection.xml 参数映射更新

把第二个参数从 `context` 改为 `contextValues`：

```xml
<calling id="calling_logistic_resolve_account"
         hook="hook_logistic_before_request"
         service="service_carrier_resolve_account"
         method="resolveAccountId">
    <argument name="carrierCode"    from="context.carrierCode"/>
    <argument name="contextValues"  from="context.contextValues"/>
</calling>
```

### 5. 不引入新 Domain value object

评估过 `XFE_Carrier_Domain_MatchContext` 作为公开 value object 的方案，**否决**：
- `XFE_Carrier_Model_Service_Rule_MatchContext` 已经在公共命名空间（无下划线前缀），外部可以引用
- 引入新 Domain 类会增加维护成本（两套实现需同步）
- 原生 array 在 PHP 中是无类型约束的最大公约数

### 6. 行为不变性

- 适配器签名变化：第二个参数从对象变 array（向后**不兼容**）
- 影响范围：仅 `PodService::resolveCredentialsViaInjection` 一处
- 旧调用方如果传对象进来会抛 TypeError — 但目前没有旧调用方（方法刚加，未发布）

## 备选方案

### 备选 A：新建 XFE_Carrier_Domain_MatchContext 公开类

否决：与 `XFE_Carrier_Model_Service_Rule_MatchContext` 重复实现，且 Service 层规则匹配代码（Resolver/Evaluator）仍依赖 Service 子目录版本，新 Domain 类无法直接被规则匹配复用。

### 备选 B：保留对象类型，但用接口代替具体类

```php
interface XFE_Carrier_Model_Service_Rule_MatchContextInterface {
    public function get($key);
    public function toArray();
}
```

否决：增加接口文件 + MatchContext 实现该接口，复杂度上升但解耦效果与"用 array"相同。array 是 PHP 内置无依赖的最大公约数。

### 备选 C：完全删除 PHP 类型提示

```php
public function resolveAccountId($carrierCode, $context)
```

否决：失去 PHP 7+ 类型提示带来的 IDE 提示和静态检查优势。PHP 8.5 的 union types 可以表达 array|object，但语义模糊。

## 后果

### 正面
- Logistic 端零 Carrier 类型依赖（`grep "XFE_Carrier" PodService.php` 仅命中类注释/PHPDoc，无类型签名）
- 接口稳定性提升：array 是 PHP 原生类型，演进风险低
- XML 注入参数语义更清晰：`contextValues` 表达"这是原始 key/value 集合"vs 旧 `context` 表达"这是 Carrier 的 DTO"
- Logistic 单测可以用 stdClass 转 array 或直接构造 array

### 负面
- 适配器签名变化 → 任何已经引用该适配器的代码会 TypeError（本次集成范围无此风险）
- 失去 IDE 在 Carrier 端的类型补全（适配器内 `$contextValues['country_code']` 不会被补全）
- 未来 Carrier 引入 DTO 类型安全增强时，需要再次重构（迁移成本可控）

### 缓解措施
- 适配器内部的 array→MatchContext 转换是单一映射点，未来重构集中
- 在 `CredentialViaInjection` 上方 PHPDoc 列出合法 key（country_code / city / zip_code 等）
- 集成测试覆盖 array 形式调用，回归保护

## 实施检查清单

- [ ] 修改 `XFE_Carrier_Service_Account_CredentialViaInjection::resolveAccountId` 签名
- [ ] 修改 `XFE_Carrier_Service_Account_CredentialViaInjection::_resolveCarrierIdByCode` 行为不变
- [ ] 修改 `XFE_Logistic_Service_PodService::resolveCredentialsViaInjection` 签名
- [ ] 更新 `XFE_Logistic/etc/injection.xml` 参数映射（`context` → `contextValues`）
- [ ] 集成测试更新：MockContext 改为 array
- [ ] 单元测试仍全过（71 assertions）
- [ ] 集成测试更新并全过（32 → 32+ assertions）
- [ ] 验证清单 6.3 重新跑：grep "XFE_Carrier" PodService.php 应仅命中注释
- [ ] 版本号：Carrier 1.0.16 → 1.0.17，Logistic 1.1.0 → 1.1.1
- [ ] 更新 `injection-carrier-logistic-integration.md` §4.2 的"弱耦合"标注

## 不在本轮范围

- ❌ 替换 `XFE_Carrier_Model_Service_Rule_MatchContext` 的内部使用方（Resolver/Evaluator）
- ❌ 引入 `XFE_Carrier_Domain_MatchContext` 公开类
- ❌ 改动 `PodService::getProofOfDelivery` 主流路径
