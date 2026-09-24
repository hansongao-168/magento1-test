# 0014. 引入基于 XML 注入的跨模块调用机制（XFE_Injection 公共模块）

- 状态：Accepted
- 日期：2026-09-16
- 决策者：hanson.gao + AI 助手
- 关联文档：
  - [injection-architecture.md](../injection-architecture.md)（架构总览）
  - [injection-api.md](../injection-api.md)（对外 API 契约）

## 背景

当前 XFE 模块之间要"调用对方模块的方法"时，只能用以下 3 种方式之一：

1. `Mage::getModel('xfe_carrier/credential_resolver')` —— 业务模块知道目标模块别名
2. `Mage::dispatchEvent(...)` + Observer —— 只能异步通知，无法返回结果
3. `Mage::helper('xfe_xxx/data')` —— Helper 是单例，但是仍然强类型耦合

实测当前 XFE_* 模块之间的耦合：
- XFE_Logistic 内部 `Mage::getModel('xfe_carrier/credential_resolver')` 直引
- XFE_LabelPrint 通过事件订阅（异步、不可控返回值）
- XFE_DocumentUpload 自带 accounts_json 实现，绕过了 XFE_Carrier（重复造轮子）

**核心问题**：
- 跨模块调用是 **PHP 代码级别**耦合（`use` / `new`），不是 **配置级别**（XML）
- 想替换 / mock 目标模块的服务，必须改 PHP 代码
- 业务模块之间的依赖关系隐藏在源码里，不能从 XML 配置一眼看清
- 不能通过改配置就完成"把 A 调用 B 改为 A 调用 C"这类切换

## 决策

### 1. 引入公共模块 XFE_Injection

新建独立模块 `XFE_Injection`，承担"声明 → 合并 → 调度"职责：
- **Domain (L1)**：纯数据类（HookDefinition、ServiceDefinition、CallingDefinition、InjectionContext）
- **Model (L3)**：
  - Registry：全局注册表（hooks、services、callings）
  - Loader：启动期扫描合并所有 `etc/injection.xml`
  - Runner：trigger(hookName, context) 调度入口
  - ServiceLocator：按字符串标识查找服务类
  - Config/Merger：合并 XML

### 2. 每个业务模块新增 etc/injection.xml

3 类声明：

```xml
<config>
    <injection>
        <!-- 我能提供什么服务（被调用方） -->
        <services>
            <service_carrier_resolve_account>
                <class>XFE_Carrier_Model_CredentialResolver</class>
                <method>resolveAccountId</method>
            </service_carrier_resolve_account>
        </services>

        <!-- 我暴露哪些入口（触发点） -->
        <hooks>
            <hook_logistic_before_request>
                <description>B 模块请求获取账号时触发</description>
            </hook_logistic_before_request>
        </hooks>

        <!-- 我要在哪些入口调用哪些服务（消费方） -->
        <callings>
            <calling hook="logistic_before_request"
                     service="service_carrier_resolve_account"
                     method="resolve">
                <argument name="carrierCode" from="context.carrierCode"/>
                <argument name="context" from="context"/>
            </calling>
        </callings>
    </injection>
</config>
```

### 3. 消费方 PHP 代码不出现 use/new 目标模块类

```php
// XFE_Logistic 内部（旧：直接 new）
\$resolver = Mage::getModel('xfe_carrier/credential_resolver');
\$accountId = \$resolver->resolveAccountId('gls', \$context);

// XFE_Logistic 内部（新：通过 XML 注入）
\$result = XFE_Injection_Model_Runner::trigger(
    'logistic_before_request',
    array('carrierCode' => 'gls', 'context' => \$context)
);
\$accountId = \$result->get('accountId');
```

Logistic 内部代码**没有任何** `use XFE_Carrier_xxx`、`new XFE_Carrier_xxx`，甚至不需要知道 XFE_Carrier 存在。

### 4. 运行时合并与懒加载

- Magento 启动期（`config load`) 通过 `<modules><XFE_Injection><config>...<injection>...</injection>` 把所有 injection.xml 合并
- 或在 `Mage_Core_Model_Config::loadModulesConfiguration` 中调用 `XFE_Injection_Model_Loader::loadAll()`
- 每个 service / calling 实例按需懒加载（首次 trigger 时才 `new`）

### 5. 单向依赖保护

L1 Domain 不引任何外部类。L3 Model 只允许：
- 引用 L1 Domain
- 引用 Magento Core（Mage_、Varien_Simplexml_Element）
- 引用 `XFE_Injection` 自身（不允许反向引用业务模块）

业务模块只能通过 XML 声明依赖，PHP 代码层面零耦合。

## 备选方案

### 备选 A：直接基于现有 Mage::dispatchEvent + Observer

放弃：Observer 是 fire-and-forget，无法可靠返回值，参数序列化复杂。

### 备选 B：引入 Symfony DependencyInjection 组件

放弃：增加 Composer 依赖，与 Magento 1.x 生态冲突；本项目一直避免引入新依赖。

### 备选 C：完全沿用现有 Mage::getModel 风格 + 工厂方法

放弃：未解决"配置级别切换实现"的需求；仍然是 PHP 代码级别耦合。

### 备选 D：自己实现 IoC 容器（autowire / scope / lazy）

放弃：超出本次任务范围；增加维护成本和测试负担；本 ADR 限定为"声明式调度"而非完整 DI 容器。

## 后果

### 正面
- 业务模块之间零 PHP 代码耦合：删除/替换/重命名目标模块不需要改调用方代码
- 跨模块调用关系从 XML 配置可见：审计 / 重构一目了然
- 单测可注入 mock 服务：测试无需启动 Magento factory
- 复用 Magento 已有的 config 合并机制：无需新基础设施

### 负面
- 增加一个新概念（injection.xml），新人需要学习
- 调试时调用栈更深一层（Runner → Locator → Service）
- 极端情况下反射调用比 `new` 慢（但本项目非性能敏感）
- XML 拼写错误只有在运行时才能被发现（启动期校验只能发现语法错误）

### 缓解措施
- 详解文档 + 完整示例（`<module>-examples.md`）
- 启动期 `Mage::log()` 打印加载摘要，便于排查
- 教学示例 `XFE_Demo` 模块用最简化的 hook / service 让新人快速上手

## 实施检查清单

- [ ] 新建 `app/code/community/XFE/Injection/` 目录
- [ ] 新建 `app/etc/modules/XFE_Injection.xml` 启用声明
- [ ] 实现 L1 Domain (HookDefinition / ServiceDefinition / CallingDefinition / InjectionContext / InjectionResult)
- [ ] 实现 L3 Model (Registry / Loader / Runner / ServiceLocator / Config/Merger)
- [ ] 配置文件（etc/config.xml / etc/injection.xml）注册自描述
- [ ] 测试：触发 hook → 验证 service 调用 → 验证参数映射 → 验证结果传递
- [ ] `php -l` 全部通过
- [ ] 文档同步：docs/architecture/README.md 加一行

## 关联决策

- 与 0004 / 0005 / 0006 / 0007 / 0008 / 0009 / 0010 / 0011 / 0012 / 0013 无冲突。
- 与 AGENTS.md "模块化 / 低耦合 / 高内聚 / 单向依赖 / 文档先行" 5 项原则**完全对齐**。
