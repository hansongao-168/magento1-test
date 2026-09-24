# XFE_Injection 新模块接入指南

> 主题：让**任意 XFE_ 业务模块**通过 XFE_Injection XML 注入与其他模块通信，无需改其他模块的代码。
>
> 状态：Accepted（2026-09-24）
> 适用范围：所有要使用跨模块服务调用的 XFE_ 业务模块
> 关联文档：
> - [injection-architecture.md](./injection-architecture.md) — 公共模块架构
> - [injection-api.md](./injection-api.md) — 对外 API 契约（Runner / Domain）
> - [injection-examples.md](./injection-examples.md) — Carrier↔Logistic 完整示例
> - [injection-carrier-logistic-integration.md](./injection-carrier-logistic-integration.md) — 集成开发指南参考模板
> - [documentupload-injection-integration.md](./documentupload-injection-integration.md) — 接入实例
> - [decisions/0014-xml-injection-mechanism.md](./decisions/0014-xml-injection-mechanism.md) — 设计决策
> - [decisions/0016-decouple-match-context.md](./decisions/0016-decouple-match-context.md) — MatchContext 全 array 化

---

## 1. 何时使用 XML 注入

满足以下**任一条件**就必须走 XML 注入：

1. **模块 A 需要调用模块 B 的方法**，但 A 不能直接 `use XFE_B_*`（违反单向依赖）
2. **模块 A 需要监听模块 B 的业务事件**（例如"Carrier 账号创建后"），但 A 不能直接订阅 B 的 observer
3. **模块 A 暴露的能力要可被任意 N 个模块复用**，希望按声明聚合而非硬编码调用
4. **模块 B 的实现类未来可能替换**（升级 / 拆分），但调用方希望零代码改动

## 2. 何时**不**使用 XML 注入

| 场景 | 直接做法 |
|------|---------|
| 调用 Magento 核心类（`Mage_Catalog_*` 等） | 直接 `Mage::getModel(...)` 或 `Mage::helper(...)` |
| 同模块内部类调用 | 直接 `$this->method()` 或 `new` 同 namespace 类 |
| 高频紧密循环（每行 10000 次） | 注入有 XML 解析间接开销，请直接调用 |
| 单纯传数据（无业务逻辑） | 用 Domain ValueObject 直接传，无需 XML 注入 |
| 调试用临时脚本 | 临时 `$obj = new XFE_B_Foo()` 可以，但 commit 前要改成注入 |

---

## 3. 角色模型

每个 XFE_ 业务模块可以扮演以下**两种角色之一或全部**：

| 角色 | 别名 | 做什么 | 改哪个 XML |
|------|------|--------|-----------|
| **Provider（提供方）** | 服务端 | 暴露方法 / 钩子给其他模块调用 | **自己**模块的 `etc/injection.xml` |
| **Consumer（消费方）** | 客户端 | 调用其他模块暴露的方法 / 钩子 | **自己**模块的 `etc/injection.xml` |

> **关键**：所有声明都在**自己模块**的 `etc/injection.xml` 中。Provider 模块声明 service + hook，Consumer 模块声明 calling。两边 XML 在 `Mage::app()` 时由 `XFE_Injection_Model_Loader` 自动合并到全局 Registry。

---

## 4. 接入流程（5 步）

### Step 1: 决定角色

打开 [injection-api.md](./injection-api.md) §3 浏览现有 hook / service 列表，或在 `app/code/community/XFE/*/etc/` 下看现有 `injection.xml` 声明。

| 你的诉求 | 你的角色 | 下一步 |
|---------|---------|--------|
| 我有方法要给别人用 | **Provider** | → Step 2.A |
| 我想调别人的方法 | **Consumer** | → Step 2.B |
| 我想监听别人的事件 | **Consumer**（事件订阅） | → Step 2.B |
| 我既给又拿 | **Provider + Consumer** | 两条路径并行 |

### Step 2.A: Provider 端 — 暴露能力

#### 2.A.1 在 `etc/injection.xml` 中声明 service + hook

文件：`app/code/community/XFE/YourModule/etc/injection.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <modules>
        <XFE_YourModule>
            <version>1.0.0</version>
        </XFE_YourModule>
    </modules>
    <injection>
        <!-- service：声明一个可被调用的方法 -->
        <services>
            <service_yourmodule_method_name>
                <class>XFE_YourModule_Service_YourService</class>
                <method>yourMethod</method>
            </service_yourmodule_method_name>
        </services>
        <!-- hook：声明一个可被触发的事件入口 -->
        <hooks>
            <hook_yourmodule_event>
                <description>Other modules can call our method via this hook</description>
            </hook_yourmodule_event>
        </hooks>
    </injection>
</config>
```

> **命名约定**：`service_<module>_<verb>`，如 `service_carrier_resolve_account`；`hook_<module>_<event>`，如 `hook_carrier_account_resolved`。

#### 2.A.2 实现 service 类

```php
<?php
/**
 * 业务 service：被其他模块通过 XML 注入调用。
 *
 * @category   Community
 * @package    XFE_YourModule
 */
class XFE_YourModule_Service_YourService
{
    /**
     * 公共方法签名约束：
     *   - 第一个参数：业务主键（string|int）
     *   - 第二个参数：array $contextValues（ADR 0016 强制要求，零耦合）
     *   - 返回值：array|null（null 表示"未命中/无账号"，触发 Consumer 端 fallback 处理）
     */
    public function yourMethod($businessKey, array $contextValues = array())
    {
        // 业务逻辑
        return array(
            'business_key' => $businessKey,
            // ... 其他字段
        );
    }
}
```

#### 2.A.3 （可选）触发 hook 让 Consumer 收到事件

```php
use XFE_Injection_Model_Runner as InjectionRunner;
use XFE_Injection_Domain_InjectionContext;

$result = InjectionRunner::trigger(
    'hook_yourmodule_event',
    new InjectionContext(array(
        'businessKey'   => $businessKey,
        'contextValues' => $contextValues,
    ))
);

// $result 是 XFE_Injection_Domain_InjectionResult，
// 可用 ->first() 拿第一个非 null 返回值，或 ->get(callId) 拿特定 calling 的返回值
$firstResult = $result->first();
```

### Step 2.B: Consumer 端 — 调用其他模块

#### 2.B.1 在 `etc/injection.xml` 中声明 calling

```xml
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <modules>
        <XFE_ConsumerModule>
            <version>1.0.0</version>
        </XFE_ConsumerModule>
    </modules>
    <injection>
        <!-- calling：声明"我要在某个 hook 上调用某个 service 的某个方法" -->
        <callings>
            <calling id="calling_consumer_use_other"
                     hook="hook_otherevent"
                     service="service_other_method_name"
                     method="otherMethod">
                <argument name="businessKey"  from="context.businessKey"/>
                <argument name="contextValues" from="context.contextValues"/>
            </calling>
        </callings>
    </injection>
</config>
```

> **关键约束**：
> - `from` 路径必须是 `context.<key>` 形式（指向 `InjectionContext` 中的字段）
> - **第二个参数必须从 `context.contextValues` 映射**（保证 Consumer 端不传具体对象，零耦合）

#### 2.B.2 调用代码

```php
use XFE_Injection_Model_Runner as InjectionRunner;
use XFE_Injection_Domain_InjectionContext;

public function someMethod($businessKey)
{
    $context = new InjectionContext(array(
        'businessKey'   => $businessKey,
        // contextValues 必须是 array，里面放业务上下文（request_id, user_id, locale 等）
        // 不能传 XFE_OtherModule_* 对象（违反零耦合）
        'contextValues' => array(
            'request_source' => 'admin',
            'user_id'        => Mage::getSingleton('admin/session')->getUser()->getId(),
        ),
    ));

    $result = InjectionRunner::trigger('hook_otherevent', $context);
    $value = $result->first();

    if (!is_array($value) || empty($value['business_key'])) {
        Mage::throwException('Other module returned null or invalid data');
    }

    return $this->doSomething($value);
}
```

### Step 3: 测试（必做）

#### 3.1 单元测试 — Reflection 验证签名

参考文件：
- [InjectionTest.php](../../app/code/community/XFE/Injection/Test/Unit/InjectionTest.php)
- [PodServiceMainPathTest.php](../../app/code/community/XFE/Injection/Test/Integration/PodServiceMainPathTest.php)

```php
<?php
spl_autoload_register(function ($class) {
    $projectRoot = realpath(__DIR__ . '/../../../../../../../');
    if ($projectRoot === false) return;
    $rel = str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
    foreach (array('community', 'core', 'local') as $pool) {
        $file = $projectRoot . '/app/code/' . $pool . '/' . $rel;
        if (file_exists($file)) { require $file; return; }
    }
});

$assertions = 0; $failures = 0;
function ok($cond, $msg) {
    global $assertions, $failures;
    $assertions++;
    echo $cond ? "  PASS  " : "  FAIL  ";
    echo $msg . "\n";
    if (!$cond) $failures++;
}

echo "\n=== Reflection Test: XFE_YourModule_Service_YourService ===\n\n";

// 验证方法签名
$rfl = new ReflectionMethod('XFE_YourModule_Service_YourService', 'yourMethod');
$params = $rfl->getParameters();
ok($rfl->isPublic(), 'yourMethod is public');
ok(count($params) === 2, 'yourMethod has 2 params');
ok($params[0]->getName() === 'businessKey', 'param[0] = businessKey');

// ADR 0016: 第二个参数必须是 array（零耦合保证）
$type = $params[1]->getType();
$typeStr = $type ? (string)$type : 'null';
ok($typeStr === 'array', "param[1] type = array, got: {$typeStr}");

// 零 XFE_OtherModule_* 耦合验证
$source = file_get_contents($rfl->getFileName());
$crossRefs = array();
foreach (array('XFE_Carrier_', 'XFE_Logistic_', 'XFE_OAuth2_', 'XFE_Billing_') as $prefix) {
    if (strpos($source, $prefix) !== false) {
        $crossRefs[] = $prefix;
    }
}
ok(count($crossRefs) === 0,
    'no cross-module type references: ' . (empty($crossRefs) ? 'none' : implode(', ', $crossRefs)));

echo "\nTotal: {$assertions} assertions, {$failures} failures\n";
exit($failures > 0 ? 1 : 0);
```

#### 3.2 集成测试 — Mock service 端到端

参考 [PodServiceMainPathTest.php](../../app/code/community/XFE/Injection/Test/Integration/PodServiceMainPathTest.php) 模式：

```php
// Mock service 用于端到端测试
class MockOtherService {
    public $callCount = 0;
    public $lastBusinessKey = null;
    public $returnValue = null;
    public function otherMethod($businessKey, array $contextValues = array()) {
        $this->callCount++;
        $this->lastBusinessKey = $businessKey;
        return $this->returnValue;
    }
}

// 用 ServiceLocator::setOverride 替换真实 service
$mock = new MockOtherService();
$mock->returnValue = array('business_key' => 42, 'data' => 'mocked');
$locator = new XFE_Injection_Model_ServiceLocator();
$locator->setOverride('service_other_method_name', $mock);

// 触发 hook，验证 mock 被调用
$result = XFE_Injection_Model_Runner::trigger('hook_xxx', $context);
ok($mock->callCount === 1, 'mock called once');
ok($result->first() === $mock->returnValue, 'first() returns mock return');
```

#### 3.3 加入统一测试入口

文件：`tests/php/run-tests.php`，在 `$suites` 数组追加你的测试：

```php
array(
    'file'  => 'app/code/community/XFE/YourModule/Test/Unit/YourServiceReflectionTest.php',
    'label' => 'Unit: YourService reflection',
),
```

### Step 4: 文档同步（AGENTS.md §5.3 强制）

1. **新建** `docs/architecture/<yourmodule>-architecture.md`，至少包含：
   - 模块定位 + 依赖（用 ASCII 箭头图）
   - "接入 XML 注入"段落：声明了哪些 hook / service / calling

2. **如引入不可逆决策**（修改 public API、调整单向依赖、共享数据 schema 破坏）：新建 ADR：
   ```
   docs/architecture/decisions/NNNN-<slug>.md
   ```

3. **README 索引**：在 `docs/architecture/README.md` 文档列表中加入新文档链接

### Step 5: 提交与验证

```bash
# 1. PHP 语法检查
php -l app/code/community/XFE/YourModule/Service/YourService.php

# 2. 单元测试
php app/code/community/XFE/YourModule/Test/Unit/YourServiceReflectionTest.php

# 3. 全量测试
php tests/php/run-tests.php
# 期望：Total: ≥ 现有数, 0 failures

# 4. 跨模块零耦合 grep 验证
grep -r "XFE_OtherModule_" app/code/community/XFE/YourModule/
# 期望：仅 PHPDoc 注释命中（行首 *），无代码命中
```

---

## 5. 完整示例：A 模块 → B 模块

**业务场景**：
- `XFE_Billing`（Provider）：有 `validateInvoice($invoiceId)` 方法，验证发票有效性
- `XFE_Report`（Consumer）：生成报表前需先验证发票

### 5.1 XFE_Billing 端（Provider）

#### etc/injection.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <modules><XFE_Billing><version>1.0.0</version></XFE_Billing></modules>
    <injection>
        <services>
            <service_billing_validate_invoice>
                <class>XFE_Billing_Service_InvoiceValidator</class>
                <method>validateInvoice</method>
            </service_billing_validate_invoice>
        </services>
        <hooks>
            <hook_billing_invoice_validated>
                <description>Other modules query validated invoice state via this hook</description>
            </hook_billing_invoice_validated>
        </hooks>
    </injection>
</config>
```

#### Service/InvoiceValidator.php

```php
<?php
class XFE_Billing_Service_InvoiceValidator
{
    public function validateInvoice($invoiceId, array $contextValues = array())
    {
        // 业务逻辑：查 DB / 调第三方 / 计算
        return array(
            'valid'     => true,
            'invoiceId' => $invoiceId,
            'amount'    => 99.00,
        );
    }
}
```

### 5.2 XFE_Report 端（Consumer）

#### etc/injection.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <modules><XFE_Report><version>1.0.0</version></XFE_Report></modules>
    <injection>
        <callings>
            <calling id="calling_report_validate_invoice"
                     hook="hook_billing_invoice_validated"
                     service="service_billing_validate_invoice"
                     method="validateInvoice">
                <argument name="invoiceId"     from="context.invoiceId"/>
                <argument name="contextValues" from="context.contextValues"/>
            </calling>
        </callings>
    </injection>
</config>
```

#### Service/ReportGenerator.php

```php
<?php
use XFE_Injection_Model_Runner as InjectionRunner;
use XFE_Injection_Domain_InjectionContext;

class XFE_Report_Service_ReportGenerator
{
    public function generateReport($invoiceId)
    {
        // 触发 Billing 暴露的 hook
        $context = new InjectionContext(array(
            'invoiceId'     => $invoiceId,
            'contextValues' => array('request_source' => 'admin'),
        ));

        $result = InjectionRunner::trigger('hook_billing_invoice_validated', $context);
        $validation = $result->first();

        if (!is_array($validation) || empty($validation['valid'])) {
            Mage::throwException('Invoice validation failed (injection returned null)');
        }

        return $this->doGenerateReport($invoiceId, $validation);
    }
}
```

### 5.3 验证清单

```bash
# 1. Billing 模块的 service 已被注册
grep -r "service_billing_validate_invoice" app/code/community/XFE/Billing/etc/injection.xml
# 期望：1 处命中

# 2. Report 模块的 calling 已声明
grep -r "calling_report_validate_invoice" app/code/community/XFE/Report/etc/injection.xml
# 期望：1 处命中

# 3. Report PHP 代码零 XFE_Billing_ 耦合
grep -r "XFE_Billing_" app/code/community/XFE/Report/
# 期望：仅 PHPDoc 命中

# 4. 跑全量测试
php tests/php/run-tests.php
# 期望：0 failures
```

---

## 6. 反模式（绝对禁止）

| 反模式 | 后果 | 替代方案 |
|--------|------|---------|
| `new XFE_OtherModule_Service_X()` | 编译期硬依赖，无法单测 | XML 注入 |
| `Mage::getModel('xfe_othermodule/...')` | 运行时强耦合 | XML 注入 |
| 在 calling 参数中传 `XFE_OtherModule_*` 对象 | 触发"零耦合"违规 | 用 array $contextValues |
| 注入 XML 中不声明就直接 `Runner::invoke()` | 运行时 `ServiceNotFoundException` | 先声明 service |
| 用 `Runner::invoke()` 调**自己模块**的 service | 绕一圈，违反模块化 | 直接 `new` 同 namespace 类 |
| calling 第二个参数 from 不是 `context.contextValues` | 跨模块耦合复发 | 必须 `context.contextValues` |

---

## 7. 调试技巧

### 7.1 验证 injection.xml 被合并

```php
// 在任何 Controller / Service / Observer 中临时加：
$registry = XFE_Injection_Model_Registry::getInstance();
Mage::log(
    'Services: ' . implode(', ', array_keys($registry->getAllServices())),
    Zend_Log::DEBUG
);
Mage::log(
    'Hooks: ' . implode(', ', array_keys($registry->getAllHooks())),
    Zend_Log::DEBUG
);
```

应包含你声明的 service id / hook id。

### 7.2 验证 calling 参数映射

```php
$callings = XFE_Injection_Model_Registry::getInstance()->getCallingsForHook('hook_xxx');
foreach ($callings as $call) {
    foreach ($call->getArguments() as $arg) {
        Mage::log("arg {$arg->getName()} <- {$arg->getFrom()}", Zend_Log::DEBUG);
    }
}
```

### 7.3 用 ServiceLocator::setOverride 注入 mock

```php
// 测试 / 调试时替换真实 service
$mock = new MyMockService();
XFE_Injection_Model_ServiceLocator::setOverride('service_target_id', $mock);
```

### 7.4 检查 injection.xml 合并错误

```bash
# 启动 Magento 后看 var/log/system.log
grep -i "injection\|XmlReader\|Merger" var/log/system.log
```

`XFE_Injection_Model_Loader` 启动时会 log "Merged <n> hooks / <n> services / <n> callings"。

---

## 8. 接入前自检清单

- [ ] 我要做的不是绕过单向依赖原则
- [ ] 我知道是 Provider / Consumer / 双角色
- [ ] Provider 端：service 类已实现并在 etc/injection.xml 中声明
- [ ] Consumer 端：calling 已在 etc/injection.xml 中声明 + 代码用 Runner::trigger
- [ ] 所有 calling 参数都从 context 字段 `from` 映射（不传对象）
- [ ] 第二个参数始终是 `array`（ADR 0016 强约束）
- [ ] 单元测试覆盖：reflection 验证签名 + mock service 端到端
- [ ] 跨模块零耦合：`grep -r "XFE_OtherModule_" app/code/community/XFE/MyModule/` 应仅 PHPDoc 命中
- [ ] 文档同步：`docs/architecture/<module>-architecture.md` 新增"接入 XML 注入"段落
- [ ] 如修改 public API 或引入不可逆决策：新建 ADR
- [ ] 提交前：`php tests/php/run-tests.php` 全过

---

## 9. 常见问题（FAQ）

### Q1: 我的模块不写 etc/injection.xml，能不能直接调用别人的 service？

**不能**。每个模块必须自己声明 calling，Registry 才能在 trigger 时找到调用关系。这是"声明式"的核心 — 模块边界由 XML 显式表达。

### Q2: 我能在 calling 里直接 new 一个匿名 service 类吗？

不能。calling 只能指向 Registry 中已注册的 service。如果你有"一次性"的 service，请先用 `<services>` 声明。

### Q3: hook 没有 calling 时 trigger 会怎样？

`Runner::trigger()` 会返回空的 `InjectionResult`，`->first()` 返回 `null`。这是合法状态 — 表示"无 Consumer 订阅此 hook"。

### Q4: 多个 calling 订阅同一 hook，返回值如何聚合？

`InjectionResult` 装载每个 calling 的返回值。`->first()` 返回第一个**非 null** 值（约定）。如需遍历，用 `->toArray()` 或 `foreach`。

### Q5: 我的 service 要传引用类型参数怎么办？

不能。calling 参数是值传递。如需复杂数据：
- 拆成多个原子字段（`<argument name="invoiceId" from="context.invoiceId"/>`）
- 或序列化到 `contextValues` array 中（Consumer 端 deserialize）

---

## 10. 修订记录

| 日期 | 变更 | 作者 |
|------|------|------|
| 2026-09-24 | 初版：通用接入指南，基于 ADR 0014/0016 + Carrier↔Logistic / DocumentUpload 接入实践 | hanson.gao + AI 助手 |