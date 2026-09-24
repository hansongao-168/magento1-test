# XFE_Injection 对外 API 契约

> 主题：业务模块通过 `XFE_Injection` 公共模块调用其他模块服务的**唯一**公开接口。
>
> 状态：Accepted（2026-09-16）
> 适用范围：所有 XFE_* 业务模块
> 关联文档：
> - [injection-architecture.md](./injection-architecture.md)
> - [decisions/0014-xml-injection-mechanism.md](./decisions/0014-xml-injection-mechanism.md)

---

## 1. 入口约定

业务模块与 XFE_Injection 的交互只允许通过以下 **3 个静态入口**：

```php
// 入口 1：触发 hook（业务模块暴露入口给别人调用）
XFE_Injection_Model_Runner::trigger(string \$hookName, XFE_Injection_Domain_InjectionContext \$context);

// 入口 2：直接取 service（已知 service 标识，不经过 hook）
XFE_Injection_Model_Runner::invoke(string \$serviceId, string \$methodName, array \$args = array());

// 入口 3：查询注册表（高级用法,debug / 后台 UI）
XFE_Injection_Model_Registry::getInstance()->getAllHooks();
XFE_Injection_Model_Registry::getInstance()->getAllServices();
XFE_Injection_Model_Registry::getInstance()->getCallingsForHook(string \$hookName);
```

**禁止**：
- ❌ `new XFE_Injection_Model_Registry()`（单例）
- ❌ 任何业务模块反向 `use XFE_Injection_Domain_xxx` 写逻辑（domain 只作为数据传输载体）
- ❌ 在 calling 中调用 `Mage::getModel('xfe_xxx/yyy')` 绕过 XML 注入（必须把该调用也声明为 service）

---

## 2. Domain 数据类契约（L1，纯值对象）

### 2.1 `XFE_Injection_Domain_InjectionContext`

```php
final class XFE_Injection_Domain_InjectionContext implements ArrayAccess, IteratorAggregate
{
    public function __construct(array \$data = array());
    public function get(string \$key, mixed \$default = null): mixed;
    public function set(string \$key, mixed \$value): self;
    public function has(string \$key): bool;
    public function toArray(): array;
    // ArrayAccess / IteratorAggregate: 让 \$context['foo']、foreach 等价于 get/set
}
```

### 2.2 `XFE_Injection_Domain_InjectionResult`

```php
final class XFE_Injection_Domain_InjectionResult
{
    /** 装载某个 callingId 对应的返回值 */
    public function set(string \$callingId, mixed \$value): self;
    public function get(string \$callingId, mixed \$default = null): mixed;
    public function has(string \$callingId): bool;
    public function toArray(): array;
    /** 第一个非 null 返回值（约定:hook 触发的预期结果） */
    public function first(): mixed;
}
```

### 2.3 `XFE_Injection_Domain_HookDefinition`

```php
final class XFE_Injection_Domain_HookDefinition
{
    public function __construct(string \$hookId, string \$description = '');
    public function getId(): string;
    public function getDescription(): string;
}
```

### 2.4 `XFE_Injection_Domain_ServiceDefinition`

```php
final class XFE_Injection_Domain_ServiceDefinition
{
    public function __construct(
        string \$serviceId,
        string \$className,
        string \$methodName,
        string \$module = ''
    );
    public function getId(): string;
    public function getClassName(): string;
    public function getMethodName(): string;
    public function getModule(): string;  // 提供该 service 的模块名
}
```

### 2.5 `XFE_Injection_Domain_CallingDefinition`

```php
final class XFE_Injection_Domain_CallingDefinition
{
    public function __construct(
        string \$callingId,
        string \$hookId,
        string \$serviceId,
        string \$methodName,
        array \$arguments = array()  // [['name'=>'foo','from'=>'context.foo'], ...]
    );
    public function getId(): string;
    public function getHookId(): string;
    public function getServiceId(): string;
    public function getMethodName(): string;
    public function getArguments(): array;  // 已展开的 [['name'=>..., 'from'=>...]]
}
```

### 2.6 异常

| 类 | 触发条件 |
|----|---------|
| `XFE_Injection_Domain_Exception_UnknownHookException` | trigger 一个未声明的 hook |
| `XFE_Injection_Domain_Exception_UnknownServiceException` | calling 引用了未声明的 service |
| `XFE_Injection_Domain_Exception_DuplicateHookException` | 同一 hookId 被多个模块声明 |
| `XFE_Injection_Domain_Exception_DuplicateServiceException` | 同一 serviceId 被多个模块声明 |
| `XFE_Injection_Domain_Exception_CircularCallingException` | A→B→A 循环触发 |
| `XFE_Injection_Domain_Exception_InvalidArgumentException` | 参数 from 表达式语法错误 |

---

## 3. Model API 契约（L3）

### 3.1 `XFE_Injection_Model_Runner`

```php
final class XFE_Injection_Model_Runner
{
    /**
     * 触发一个 hook,执行该 hook 下注册的所有 calling,聚合结果。
     *
     * @param string \$hookName hookId
     * @param XFE_Injection_Domain_InjectionContext \$context 调用上下文
     * @return XFE_Injection_Domain_InjectionResult 所有 callingId => 返回值
     * @throws XFE_Injection_Domain_Exception_UnknownHookException
     */
    public static function trigger(string \$hookName, XFE_Injection_Domain_InjectionContext \$context);

    /**
     * 直接调用一个 service 的方法(不经过 hook)。
     *
     * @param string \$serviceId  serviceId
     * @param string \$methodName 该 service 定义之外的备用方法(可选)
     * @param array  \$args      按方法签名顺序的参数数组
     * @return mixed
     */
    public static function invoke(string \$serviceId, string \$methodName = '', array \$args = array());

    /** 清空单例(测试用) */
    public static function resetForTesting(): void;
}
```

### 3.2 `XFE_Injection_Model_Registry`

```php
final class XFE_Injection_Model_Registry
{
    public static function getInstance(): self;

    /** 启动期由 Loader 调用 */
    public function registerHook(XFE_Injection_Domain_HookDefinition \$hook): void;
    public function registerService(XFE_Injection_Domain_ServiceDefinition \$svc): void;
    public function registerCalling(XFE_Injection_Domain_CallingDefinition \$call): void;

    /** 查询 */
    public function hasHook(string \$hookId): bool;
    public function hasService(string \$serviceId): bool;
    public function getHook(string \$hookId): XFE_Injection_Domain_HookDefinition;
    public function getService(string \$serviceId): XFE_Injection_Domain_ServiceDefinition;
    public function getCallingsForHook(string \$hookId): array;  // CallingDefinition[]
    public function getAllHooks(): array;                          // HookDefinition[]
    public function getAllServices(): array;                       // ServiceDefinition[]
    public function getAllCallings(): array;                       // CallingDefinition[]

    /** 统计 */
    public function countHooks(): int;
    public function countServices(): int;
    public function countCallings(): int;

    /** 检测循环引用 */
    public function detectCircularCallings(): array;  // 返回环列表,空数组=无环
}
```

### 3.3 `XFE_Injection_Model_Loader`

```php
final class XFE_Injection_Model_Loader
{
    /**
     * 扫描所有启用模块的 etc/injection.xml,合并到 Registry。
     * 通常在 Mage 启动期通过 Observer 调用一次。
     */
    public static function loadAll(): XFE_Injection_Model_Registry;

    /** 单文件加载(测试用) */
    public static function loadFile(string \$moduleName, string \$xmlPath): void;
}
```

### 3.4 `XFE_Injection_Model_ServiceLocator`

```php
final class XFE_Injection_Model_ServiceLocator
{
    /**
     * 给定 ServiceDefinition,返回一个可调用对象。
     * 优先尝试从 Registry 缓存的实例取,否则 new。
     */
    public function resolve(XFE_Injection_Domain_ServiceDefinition \$svc): object;

    /** 测试用:替换某个 service 的实例(用于 mock) */
    public function setOverride(string \$serviceId, object \$instance): void;
    public function clearOverride(string \$serviceId): void;
    public function clearAllOverrides(): void;
}
```

### 3.5 `XFE_Injection_Model_Config_Merger`

```php
final class XFE_Injection_Model_Config_Merger
{
    /**
     * 把一个 XML 字符串解析并合并到 Registry。
     * 重复声明抛 DuplicateHookException / DuplicateServiceException。
     */
    public function mergeFromXml(string \$moduleName, string \$xmlContent): void;

    /**
     * 合并多个 XML(顺序敏感:后注册覆盖先注册的 calling).
     * calling 的覆盖策略:同 hookId + 同 serviceId 视为同一 calling,
     * 用最后声明的版本。
     */
    public function mergeMultiple(array \$xmlSources): void;
}
```

---

## 4. 参数映射语法

`from` 表达式支持以下 4 种语法（在 calling 的 `<argument>` 节点下）：

| 语法 | 含义 | 示例 |
|------|------|------|
| `context.<key>` | 从 `InjectionContext::get('<key>')` 读取 | `context.carrierCode` |
| `context` | 整个上下文对象 | `context` |
| `literal:<value>` | 字符串字面量(完整字符串直到遇到第一个非字面字符) | `literal:gls` |
| `null` | 显式 null | `null` |

**示例**：

```xml
<calling hook="logistic_before_request"
         service="service_carrier_resolve_account"
         method="resolve">
    <argument name="carrierCode" from="context.carrierCode"/>
    <argument name="context"     from="context"/>
    <argument name="fallback"    from="literal:true"/>
</calling>
```

展开后等价于：

```php
\$instance->resolve(
    \$context->get('carrierCode'),  // 'gls'
    \$context,                       // XFE_Injection_Domain_InjectionContext
    true                              // fallback
);
```

---

## 5. 兼容性

- 不依赖 PHP 7.3 之外的版本特性
- 不引入 Composer 包
- 仅依赖 Magento Core（Mage_、Varien_Simplexml_Element）

---

## 6. 不允许的用法（红线）

| 错误用法 | 后果 |
|---------|------|
| 业务模块直接 `use XFE_Injection_Model_xxx` 当成"工具类" | 破坏单向依赖（Runner / Registry 是 XFE_Injection 内部细节） |
| 在 `<calling>` 里嵌套写 `<calling>` | XML 不支持,启动期报错 |
| 同一 hookId 在多个模块声明 | 抛 DuplicateHookException |
| 循环触发 A → B → A | 抛 CircularCallingException |
| `<argument>` 缺 `from` | 抛 InvalidArgumentException |
| calling 的 method 与 service 声明的 method 不一致 | 仅警告,实际调用 calling 的 method（更灵活） |

---

## 7. 关联文档

- 架构总览：[injection-architecture.md](./injection-architecture.md)
- 决策记录：[decisions/0014-xml-injection-mechanism.md](./decisions/0014-xml-injection-mechanism.md)
- 使用示例：[injection-examples.md](./injection-examples.md)
