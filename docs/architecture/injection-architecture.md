# XFE_Injection 公共模块架构

> 主题：**基于 XML 注入的跨模块调用机制**。让 A 模块通过读 XML 即可"注入调用"B 模块的方法，
> 类似 Magento layout XML 的合并与解析机制。
>
> 状态：Accepted（2026-09-16）
> 适用范围：所有需要跨模块服务调用的 XFE_* 业务模块
> 关联文档：
> - [decisions/0014-xml-injection-mechanism.md](./decisions/0014-xml-injection-mechanism.md)
> - [injection-api.md](./injection-api.md)（对外 API 契约）
> - [injection-examples.md](./injection-examples.md)（使用示例）

---

## 1. 模块定位

`XFE_Injection` 是**基础设施层**的公共模块，承担"声明 → 合并 → 调度"职责，
让业务模块之间通过 XML 解耦，是 AGENTS.md 中"低耦合"和"单向依赖"原则的具体落地。

它的作用类似：

| 已知概念 | 类比 |
|---------|------|
| Magento layout XML | 声明 block 树结构；合并所有模块 layout 后实例化 |
| Java Spring IoC 容器 | XML 声明 bean；容器负责 new + 装配 |
| Symfony 服务容器 | YAML/XML 声明服务；运行时按 ID 解析 |

**本模块的特点**：
- 不依赖任何业务模块（XFE_Carrier、Logistic 等）
- 不被任何业务模块依赖（除通过 XML 声明）
- 自身只在 L1 / L3 分层（无 L2 Gateway、无 L4 Controller）

---

## 2. 单向依赖分层

```
┌──────────────────────────────────────────────────────────┐
│  L4  业务模块（XFE_Carrier / XFE_Logistic / XFE_xxx）      │
│      通过 XML 声明调用关系,PHP 代码零耦合                  │
└────────────────────┬─────────────────────────────────────┘
                     │ XML 合并
                     ▼
┌──────────────────────────────────────────────────────────┐
│  L3  XFE_Injection_Model_*                                │
│      ├─ Registry       全局服务/hook/calling 注册表        │
│      ├─ Loader         启动期扫描合并 injection.xml       │
│      ├─ Runner         trigger(hook, ctx) 调度入口         │
│      ├─ ServiceLocator 按字符串标识解析服务类               │
│      └─ Config/Merger  XML 合并                            │
└────────────────────┬─────────────────────────────────────┘
                     │ 引用
                     ▼
┌──────────────────────────────────────────────────────────┐
│  L1  XFE_Injection_Domain_*                              │
│      ├─ HookDefinition         hook 描述                  │
│      ├─ ServiceDefinition      服务描述                    │
│      ├─ CallingDefinition      调用关系描述                │
│      ├─ InjectionContext       调用上下文(参数载体)        │
│      └─ InjectionResult        调用结果容器                │
└──────────────────────────────────────────────────────────┘
```

**禁止**：
- L1 ❌ 引任何外部类（包括 Mage_*）
- L3 ❌ `new` 任何业务模块类（所有依赖通过 XML 字符串）
- L3 ❌ 反向引用业务模块的类、配置、表名

---

## 3. 核心概念

### 3.1 Service（服务）

业务模块**对外暴露的方法**。通过 `<service id="..."><class>...</class><method>...</method></service>` 声明。

```xml
<services>
    <service_carrier_resolve_account>
        <class>XFE_Carrier_Model_CredentialResolver</class>
        <method>resolveAccountId</method>
    </service_carrier_resolve_account>
</services>
```

### 3.2 Hook（触发点）

业务模块**暴露给别模块触发的入口**。通过 `<hook id="..."><description>...</description></hook>` 声明。

```xml
<hooks>
    <hook_logistic_before_request>
        <description>B 模块请求获取账号时触发</description>
    </hook_logistic_before_request>
</hooks>
```

### 3.3 Calling（调用关系）

业务模块**在 hook 上要调用哪个 service 的哪个方法、传什么参数**。通过 `<calling hook service method>...</calling>` 声明。

```xml
<callings>
    <calling hook="logistic_before_request"
             service="service_carrier_resolve_account"
             method="resolve">
        <argument name="carrierCode" from="context.carrierCode"/>
        <argument name="context" from="context"/>
    </calling>
</callings>
```

参数映射（`from`）支持：
- `context.foo` —— 从上下文取字段 `foo`
- `context` —— 整个上下文对象
- `literal:xxx` —— 字符串字面量
- `null` —— 显式传 null

---

## 4. 目录结构

```
app/code/community/XFE/Injection/
├── Api/
│   └── InjectionRunnerInterface.php   # 对外契约:trigger / getService
├── Domain/
│   ├── CallingDefinition.php           # 调用关系定义(L1)
│   ├── HookDefinition.php              # hook 定义(L1)
│   ├── ServiceDefinition.php           # service 定义(L1)
│   ├── InjectionContext.php            # 上下文(L1,可变 Map)
│   ├── InjectionResult.php             # 调用结果(L1,Map)
│   └── Exception/
│       ├── DuplicateHookException.php
│       ├── DuplicateServiceException.php
│       ├── UnknownHookException.php
│       ├── UnknownServiceException.php
│       └── CircularCallingException.php
├── Model/
│   ├── Registry.php                    # 注册表(单例,所有 hooks/services/callings)
│   ├── Loader.php                      # 启动期入口:loadAll()
│   ├── Runner.php                      # 运行时调度:trigger()
│   ├── ServiceLocator.php              # 服务类解析(缓存 + fallback to Mage::getModel)
│   └── Config/
│       ├── Merger.php                  # 合并各模块 injection.xml
│       └── XmlReader.php               # SimpleXMLElement 读取助手
└── etc/
    ├── config.xml                      # 模块声明
    └── injection.xml                   # XFE_Injection 自描述:
                                       #   - hooks: injection_trigger_hook
                                       #   - services: injection_log_call
                                       #   - callings: 演示
```

---

## 5. 运行时流程

### 5.1 启动期

1. `Mage_Core_Model_Config::loadModulesConfiguration()` 加载所有 `config.xml`
2. 通过 Observer 钩子调用 `XFE_Injection_Model_Loader::loadAll()`
3. Loader 遍历所有启用模块（通过 `Mage::getConfig()->getNode('modules')`）
4. 每个模块读 `etc/injection.xml`，解析为 `HookDefinition` / `ServiceDefinition` / `CallingDefinition`
5. 合并到 `XFE_Injection_Model_Registry`（去重、循环引用检测）

### 5.2 调用期

```php
// 业务模块调用方(例如 XFE_Logistic)
\$result = XFE_Injection_Model_Runner::trigger(
    'logistic_before_request',
    new XFE_Injection_Domain_InjectionContext(array(
        'carrierCode' => 'gls',
        'context'     => \$myContext,
    ))
);

// Runner 内部
//   1. Registry::getCallingsForHook('logistic_before_request')
//   2. 遍历所有 calling,按参数映射构造 \$args
//   3. ServiceLocator::resolve('service_carrier_resolve_account')->newInstance()
//   4. \$instance->resolve(\$args)
//   5. 返回值塞入 InjectionResult::set(\$callingId, \$returnValue)
```

### 5.3 异常处理

- `UnknownHookException`：trigger 一个未声明的 hook
- `UnknownServiceException`：calling 引用了未声明的 service
- `CircularCallingException`：触发器 → calling → 又触发器 → 回到自己（深度限制 5）

---

## 6. 配置项

| 后台字段 | 配置路径 | 类型 | 默认值 | 说明 |
|---------|---------|------|--------|------|
| 启用调试日志 | `xfe_injection/general/debug` | select (yes/no) | no | 启动时打印所有 hooks/services/callings 摘要到 var/log/system.log |
| 最大调用链深度 | `xfe_injection/general/max_calling_depth` | text | 5 | 防止循环触发 |
| 严格模式 | `xfe_injection/general/strict_mode` | select (yes/no) | yes | yes 时 XML 解析失败立即抛异常 |

---

## 7. 测试覆盖

`XFE_Injection/Test/` 下提供：
- `Unit/RegistryTest.php`：注册、去重、循环引用检测
- `Unit/LoaderTest.php`：扫描 + 合并 + 错误恢复
- `Unit/RunnerTest.php`：参数映射、结果聚合、深度限制
- `Unit/Config/MergerTest.php`：多 XML 合并
- `Integration/DemoTest.php`：用 XFE_Demo 模块端到端验证

---

## 8. 关联文档

- 决策记录：[decisions/0014-xml-injection-mechanism.md](./decisions/0014-xml-injection-mechanism.md)
- API 契约：[injection-api.md](./injection-api.md)
- 使用示例：[injection-examples.md](./injection-examples.md)
- AGENTS.md 总章程：[../../AGENTS.md](../../AGENTS.md)

---

## 9. 修订记录

| 日期 | 变更 | 作者 |
|------|------|------|
| 2026-09-16 | 初版：定义 XFE_Injection 公共模块、XML schema、运行时调度流程 | hanson.gao + AI 助手 |
