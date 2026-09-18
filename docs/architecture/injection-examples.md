# XFE_Injection 使用示例

> 主题：展示 XFE_Injection 在真实业务场景下的使用方式。
>
> 状态：Accepted（2026-09-16）
> 关联文档：
> - [injection-architecture.md](./injection-architecture.md)
> - [injection-api.md](./injection-api.md)
> - [decisions/0014-xml-injection-mechanism.md](./decisions/0014-xml-injection-mechanism.md)

---

## 示例 1：XFE_Carrier → XFE_Logistic 解耦示范

### 1.1 场景

`XFE_Logistic` 在调用 GLS API 前需要先拿到账号凭据。
- 提供方：`XFE_Carrier` 有 `CredentialResolver::resolveAccountId()`
- 消费方：`XFE_Logistic` 需要账号主键

**反例（强耦合,被禁止）**：

```php
// XFE_Logistic 内部
\$resolver = Mage::getModel('xfe_carrier/credential_resolver');
\$accountId = \$resolver->resolveAccountId('gls', \$context);
```

### 1.2 XML 声明

**XFE_Carrier/etc/injection.xml**（提供方）：

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
                <class>XFE_Carrier_Model_CredentialResolver</class>
                <method>resolveAccountId</method>
            </service_carrier_resolve_account>
        </services>
        <hooks>
            <hook_carrier_account_resolved>
                <description>其他模块通过 trigger 此 hook 获取账号</description>
            </hook_carrier_account_resolved>
        </hooks>
    </injection>
</config>
```

**XFE_Logistic/etc/injection.xml**（消费方）：

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
                <description>Logistic 准备发 GLS 请求前,需要先解析账号</description>
            </hook_logistic_before_request>
        </hooks>
        <callings>
            <calling hook="logistic_before_request"
                     service="service_carrier_resolve_account"
                     method="resolveAccountId">
                <argument name="carrierCode" from="context.carrierCode"/>
                <argument name="context"     from="context"/>
            </calling>
        </callings>
    </injection>
</config>
```

### 1.3 调用代码

```php
// XFE_Logistic/Service/PodService.php
class XFE_Logistic_Service_PodService
{
    public function fetchPod(\$trackId)
    {
        // 准备上下文(包含 carrierCode + 业务 context)
        \$ctx = new XFE_Injection_Domain_InjectionContext(array(
            'carrierCode' => 'gls',
            'context'     => \$this->_buildMatchContext(),
        ));

        // 触发 hook → 自动调用 XFE_Carrier 的 resolveAccountId
        \$result = XFE_Injection_Model_Runner::trigger('logistic_before_request', \$ctx);

        // 取第一个 calling 的返回值(本例只有一个)
        \$accountId = \$result->first();
        if (!\$accountId) {
            throw new Exception('No carrier account resolved');
        }

        // ...继续发 GLS HTTP 请求
    }
}
```

### 1.4 关键点

- ✅ `XFE_Logistic` 的 PHP 代码里 **没有** `use XFE_Carrier_xxx`、`new XFE_Carrier_xxx`
- ✅ 删除 `XFE_Carrier` 模块不会导致 Logistic 编译失败（运行时 trigger 才报 UnknownHookException）
- ✅ 测试时用 `XFE_Injection_Model_ServiceLocator::setOverride()` 替换 service 实例为 mock
- ✅ 想把"调用 Carrier"换成"调用 Logistic 内置 demo resolver"，只改 XML，不改 PHP 代码

---

## 示例 2：教学模块 XFE_Demo

### 2.1 场景

最小的完整示例，演示 **A 模块调用 B 模块**的最简形态：
- `XFE_Demo` 模块内部声明 2 个 hook + 2 个 service + 2 个 calling
- 1 个 PHP 测试脚本验证调用链

### 2.2 XML

`XFE_Demo/etc/injection.xml`：

```xml
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <modules>
        <XFE_Demo>
            <version>1.0.0</version>
        </XFE_Demo>
    </modules>
    <injection>
        <services>
            <service_demo_greet>
                <class>XFE_Demo_Model_Service_Greeter</class>
                <method>greet</method>
            </service_demo_greet>
            <service_demo_log>
                <class>XFE_Demo_Model_Service_Logger</class>
                <method>log</method>
            </service_demo_log>
        </services>
        <hooks>
            <hook_demo_user_login>
                <description>模拟用户登录事件</description>
            </hook_demo_user_login>
            <hook_demo_page_view>
                <description>模拟页面浏览事件</description>
            </hook_demo_page_view>
        </hooks>
        <callings>
            <calling hook="demo_user_login"
                     service="service_demo_log"
                     method="log"
                     id="calling_demo_login_log">
                <argument name="level" from="literal:info"/>
                <argument name="msg"  from="context.message"/>
            </calling>
            <calling hook="demo_user_login"
                     service="service_demo_greet"
                     method="greet"
                     id="calling_demo_login_greet">
                <argument name="name" from="context.userName"/>
            </calling>
            <calling hook="demo_page_view"
                     service="service_demo_log"
                     method="log"
                     id="calling_demo_page_log">
                <argument name="level" from="literal:debug"/>
                <argument name="msg"  from="context.message"/>
            </calling>
        </callings>
    </injection>
</config>
```

### 2.3 Service 实现

`XFE_Demo/Model/Service/Greeter.php`：

```php
<?php
class XFE_Demo_Model_Service_Greeter
{
    public function greet(\$name)
    {
        return 'Hello, ' . \$name . '!';
    }
}
```

`XFE_Demo/Model/Service/Logger.php`：

```php
<?php
class XFE_Demo_Model_Service_Logger
{
    public function log(\$level, \$msg)
    {
        Mage::log(\"[{\$level}] {\$msg}\", null, 'xfe_demo.log');
        return true;
    }
}
```

### 2.4 触发代码（应用层）

```php
\$ctx = new XFE_Injection_Domain_InjectionContext(array(
    'userName' => 'Alice',
    'message'  => 'User logged in',
));
\$result = XFE_Injection_Model_Runner::trigger('demo_user_login', \$ctx);

// 访问每个 calling 的返回值
\$greetResult = \$result->get('calling_demo_login_greet'); // 'Hello, Alice!'
\$logResult  = \$result->get('calling_demo_login_log');    // true
```

---

## 示例 3：错误与边界

### 3.1 触发未声明的 hook

```php
XFE_Injection_Model_Runner::trigger('never_declared_hook', \$ctx);
// 抛 XFE_Injection_Domain_Exception_UnknownHookException
```

### 3.2 calling 引用未声明的 service

启动期即抛 DuplicateServiceException（虽然叫 Duplicate，实际是 merge 时发现未注册）。

### 3.3 循环引用

```xml
<!-- A 模块 -->
<calling hook="hook_a" service="svc_b" method="run"/>
<!-- B 模块 -->
<calling hook="hook_b" service="svc_a" method="run"/>
```

`XFE_Injection_Model_Loader::loadAll()` 时调用 `Registry::detectCircularCallings()` 抛出 CircularCallingException。

---

## 4. 最佳实践

1. **calling id 命名**：用 `calling_<module>_<hook>_<purpose>`，避免重复
2. **service id 命名**：用 `service_<module>_<purpose>`，跨模块唯一
3. **hook id 命名**：用 `hook_<module>_<event>`，触发点唯一
4. **少用 `literal:` 参数**：复杂字面量建议放到 context 里
5. **context 复用**：把频繁传的参数（如 carrierCode）放在 context 顶层，不要嵌套
6. **测试用 override**：`ServiceLocator::setOverride()` 是单测标准做法
