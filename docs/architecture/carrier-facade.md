# Carrier Account 门面(Facade)架构方案

> 主题:其他业务模块如何**模块化、低耦合、高内聚、单向依赖**地调用 `XFE_Carrier` 的承运商账号。
>
> 状态:**提案**(2026-08-10)
>
> 适用范围:任何需要"按订单上下文挑承运商账号"的 `XFE_*` 业务模块,例如 `XFE_NewLogistics`、`XFE_DocumentUpload` 等。
>
> **配套文档**:
> - [`carrier-observer-events.md`](./carrier-observer-events.md) — 事件订阅方案(被动路径)
> - [`carrier-line-examples.md`](./carrier-line-examples.md) — 真实线路公司配置示例 + 端到端调用

---

## 1. 背景与现状

### 1.1 当前模块职责(已审计)

| 模块 | 职责 | 是否会被其他业务模块依赖 |
|------|------|------------------------|
| `XFE_Carrier` | 承运商/账号/规则主数据 + Rule Resolver 挑账号 | **是**(叶子模块) |
| `XFE_LabelPrint` | 监听 `xfe_labelprint_response_received` 事件,面单存档 | 否(被事件触发) |
| `XFE_DocumentUpload` | 文件/文档上传,**自带** `accounts_json` system config 实现 | 否(平行模块) |
| `XFE_OAuth2` | Bearer Token + Carrier 只读 API | 否(只读 `xfe_carrier/carrier` 主档) |

### 1.2 当前耦合度(实测)

```
XFE_Carrier 外部调用方文件数:               1  (XFE_OAuth2/Model/Api/Carriers.php)
外部调用 xfe_carrier/carrier_account 的文件数: 0
外部走 RAW SQL 读 carrier_account 的文件数:    0
XFE_Carrier 反向依赖其它 XFE_* 模块:           0  (只有注释,无真实引用)
```

**结论**: `XFE_Carrier` 本身是健康的叶子模块。**但 `XFE_DocumentUpload` 与 `XFE_Carrier` 是两套并行的"挑账号"实现**,这是已存在的技术债。

### 1.3 风险

新增"调用承运商账号"的第三个模块时,**最常见的错误是复制 `XFE_DocumentUpload` 的"独立小作坊"模式** —— 自己用 system config 存账号、自己写 `curl_init`、自己挑账号,完全绕过 `XFE_Carrier`。这会导致:

- 管理员要为同一承运商在不同模块重复配置账号
- 规则引擎失效(每家物流一套独立的规则,无法统一按国家/重量匹配)
- 凭据散落多处,审计困难

---

## 2. 设计原则

### 2.1 依赖金字塔(单向)

```
        ┌─────────────────────────────────────────┐
        │  业务模块 (XFE_NewLogistics, ...)       │
        │  只依赖: Service Facade 别名 + 接口     │
        └────────────────────┬────────────────────┘
                             │ 调用接口,不引实现
                             ▼
        ┌─────────────────────────────────────────┐
        │  XFE_Carrier_Model_CredentialResolver   │  ← Facade
        │  实现: CarrierCredentialResolverInterface│
        └────────────────────┬────────────────────┘
                             │ 内部委托
                             ▼
        ┌─────────────────────────────────────────┐
        │  XFE_Carrier_Model_Service_* (内部)     │  ← 现有 Rule/Account
        └─────────────────────────────────────────┘
```

### 2.2 六条铁律

| # | 铁律 | 合规做法 | 反例 |
|---|------|--------|------|
| 1 | 业务模块**只引 Facade 别名** | `Mage::getModel('xfe_carrier/credential_resolver')` | `new XFE_Carrier_Model_Service_Rule_Resolver()` |
| 2 | **不直接读表** | 永远走 Model / Resource | `$resource->getTableName('xfe_carrier_carrier_account')` |
| 3 | **不序列化账号对象** | 凭据仅在进程内存使用,不写入 API、日志、事件 payload | `$account->toArray()` 塞入 observer event |
| 4 | **`MatchContext` 由业务模块自己造** | 业务知道自己的上下文,Carrier 不知道 | 在 `XFE_Carrier` 内 `Order::getWeight()` |
| 5 | **Resolver 不发 HTTP** | 只挑账号,真实调用归业务模块 | 把 `_httpPostMultipart()` 塞进 Resolver |
| 6 | **配置走别名节点** | 业务模块读 `xfe/carrier/credential_resolver` 配置节点,拿字符串 | 直接 `new` 具体 Resolver 类 |

---

## 3. 实施细节

### 3.1 配置文件:`XFE_Carrier/etc/config.xml`

新增独立于 `<models>` 的全局节点,允许通过配置切换 Resolver 实现:

```xml
<global>
    <xfe>
        <carrier>
            <!-- 业务模块读这个节点拿到 resolver 别名(字符串),不硬编码类名 -->
            <credential_resolver>xfe_carrier/service_rule_resolver</credential_resolver>
        </carrier>
    </xfe>
    <models>
        <xfe_carrier>
            <class>XFE_Carrier_Model</class>
            ... 已有 model 声明 ...
        </xfe_carrier>
    </models>
</global>
```

> `<xfe><carrier>` 不属于 Magento 自动加载的命名空间,因此 `<credential_resolver>` 节点只是**配置字符串**,不会触发 autoload,允许被替换。

### 3.2 接口契约

**文件路径**: `app/code/community/XFE/Carrier/Model/CarrierCredentialResolverInterface.php`

```php
<?php
/**
 * 业务模块调用承运商账号的契约。
 *
 * 任何"实现"必须遵循:
 *  - 只挑账号,不允许发 HTTP
 *  - 不允许序列化、记录日志、抛业务异常以外的副作用
 *  - 返回的 MatchResult 只包含 account_id / logo_id,不暴露凭据
 */
interface XFE_Carrier_Model_CarrierCredentialResolverInterface
{
    /**
     * @param int $carrierId 主档主键(不是 code,避免业务模块解析 code 时产生反向依赖)
     * @param XFE_Carrier_Model_Service_Rule_MatchContext $context
     * @param bool $fallback 规则全部不命中时是否退到"最近可用账号"
     * @return XFE_Carrier_Model_Service_Rule_MatchResult
     * @throws XFE_Carrier_Exception_NoRuleMatch 当 $fallback=false 且无规则命中
     */
    public function resolve($carrierId, $context, $fallback = true);
}
```

### 3.3 Facade 模型

**文件路径**: `app/code/community/XFE/Carrier/Model/CredentialResolver.php`

```php
<?php
/**
 * Facade: 业务模块调用承运商账号的唯一入口。
 *
 * 业务模块永远不应该直接引用:
 *   - XFE_Carrier_Model_Service_Rule_Resolver
 *   - XFE_Carrier_Model_Carrier_Account
 *   - xfe_carrier_carrier_account 表名
 *
 * 只应通过:
 *   Mage::getModel('xfe_carrier/credential_resolver')
 * 拿到本类,然后调用 resolveAccount / resolveAccountId。
 */
class XFE_Carrier_Model_CredentialResolver
{
    /**
     * 返回该订单/上下文该用的账号主键,无任何可用账号时返回 null。
     */
    public function resolveAccountId(
        $carrierCode,
        XFE_Carrier_Model_Service_Rule_MatchContext $context
    ) {
        $carrierId = $this->_resolveCarrierIdByCode($carrierCode);
        if (!$carrierId) {
            return null;
        }

        $resolverAlias = (string) Mage::getConfig()->getNode('xfe/carrier/credential_resolver');
        $resolver = Mage::getModel($resolverAlias);
        if (!$resolver instanceof XFE_Carrier_Model_CarrierCredentialResolverInterface) {
            Mage::throwException("Resolver '$resolverAlias' must implement CarrierCredentialResolverInterface");
        }

        try {
            $result = $resolver->resolve($carrierId, $context, true);
        } catch (XFE_Carrier_Exception_NoRuleMatch $e) {
            return null;
        }
        return $result->getAccountId() ?: null;
    }

    /**
     * 返回账号对象(含 api_key / api_secret / endpoint_url)。
     * 业务模块拿到后自行发 HTTP,但不要序列化此对象对外暴露。
     */
    public function resolveAccount(
        $carrierCode,
        XFE_Carrier_Model_Service_Rule_MatchContext $context
    ) {
        $id = $this->resolveAccountId($carrierCode, $context);
        if (!$id) {
            return null;
        }
        return Mage::getModel('xfe_carrier/carrier_account')->load($id);
    }

    /**
     * 列出一个 carrier_code 下所有可用账号(不做规则匹配)。
     * 用于调试/批量导出场景。
     */
    public function listActiveAccounts($carrierCode)
    {
        $carrierId = $this->_resolveCarrierIdByCode($carrierCode);
        if (!$carrierId) {
            return [];
        }
        /** @var XFE_Carrier_Model_Resource_Carrier_Account_Collection $coll */
        $coll = Mage::getResourceModel('xfe_carrier/carrier_account_collection');
        return $coll->addCarrierFilter($carrierId)
                    ->addStatusFilter(XFE_Carrier_Model_Carrier_Account::STATUS_ACTIVE)
                    ->load()
                    ->getItems();
    }

    private function _resolveCarrierIdByCode($carrierCode)
    {
        $carrier = Mage::getModel('xfe_carrier/carrier')->loadByCode($carrierCode);
        return $carrier->getId() ?: null;
    }
}
```

### 3.4 现有 Resolver 实现接口

**改动文件**: `app/code/community/XFE/Carrier/Model/Service/Rule/Resolver.php`

```php
// 在现有 class 声明上加 implements
class XFE_Carrier_Model_Service_Rule_Resolver
    implements XFE_Carrier_Model_CarrierCredentialResolverInterface
{
    // ... 现有 resolve() 方法签名保持不变 ...
}
```

零行为改动,只增加契约声明。

### 3.5 业务模块的标准用法(样板代码)

```php
<?php
// 假设新模块: XFE_NewLogistics
class XFE_NewLogistics_Model_Shipment
{
    public function createLabel(Mage_Sales_Model_Order $order)
    {
        // 1. 业务模块自己组装上下文
        $ctx = XFE_Carrier_Model_Service_Rule_MatchContext::create([
            'country_code'   => $order->getShippingAddress()->getCountry(),
            'package_weight' => $order->getWeight(),
            'order_amount'   => (float) $order->getGrandTotal(),
        ]);

        // 2. 唯一一处对 XFE_Carrier 的引用 —— 走 Facade
        $resolver = Mage::getModel('xfe_carrier/credential_resolver');
        $account  = $resolver->resolveAccount('newlogistics', $ctx);

        if (!$account) {
            Mage::throwException('No active carrier account configured for this order.');
        }

        // 3. 业务模块自己的 HTTP 调用(各家承运商差异由业务模块自己处理)
        $response = $this->_callCarrierApi(
            $account->getEndpointUrl(),
            $account->getApiKey(),
            $account->getApiSecret(),
            $this->_buildPayload($order)
        );

        // 4. 面单存档走事件驱动,不再直接调 LabelPrint
        Mage::helper('xfe_labelprint')->dispatchLabelResponse(
            $order,
            'xfe_newlogistics',
            new Varien_Object([
                'label_content'      => $response['pdf'],
                'label_format'       => 'pdf',
                'tracking_number_id' => $response['tracking_no'],
            ])
        );
    }
}
```

---

## 4. 与现有代码的兼容性

### 4.1 XFE_OAuth2(唯一现有消费者)

```php
// XFE_OAuth2/Model/Api/Carriers.php:52, 74 直接调 xfe_carrier/carrier
Mage::getModel('xfe_carrier/carrier')->getCollection();  // OK,只读主档
Mage::getModel('xfe_carrier/carrier')->load($id);        // OK,只读主档
```

**评估**: 该模块只读 `carrier` 主档,零凭据暴露,**已合规**。优先级最低,无需现在改造。

### 4.2 XFE_DocumentUpload(技术债清理路径)

**当前**: `DocumentUpload/Model/Carrier/Abstract.php:72-95` 用 `accounts_json` system config。

**未来改造**(不在本提案范围内):

```php
// DocumentUpload/Model/Carrier/Abstract.php 中 getAccounts() 改造示意
public function getAccounts(Mage_Sales_Model_Order $order)
{
    $ctx = XFE_Carrier_Model_Service_Rule_MatchContext::create([
        'country_code' => $order->getShippingAddress()->getCountry(),
        'package_weight' => $order->getWeight(),
    ]);

    $resolver = Mage::getModel('xfe_carrier/credential_resolver');
    $account  = $resolver->resolveAccount($this->_carrierCode, $ctx);

    return $account ? [$account] : [];
}
```

**注意**: 不要现在就改。`accounts_json` 与 `xfe_carrier_carrier_account` 字段不一定兼容,需先做字段对齐 + schema migration,否则会破坏现有 DocumentUpload 客户。

### 4.3 XFE_LabelPrint(已合规)

LabelPrint 监听事件,从不调用 `XFE_Carrier`,**已合规**,无需改动。

---

## 5. 可选增强

### 5.1 Facade 层缓存(高频场景)

```php
class XFE_Carrier_Model_CredentialResolver
{
    public function resolveAccountId($carrierCode, MatchContext $context)
    {
        $cacheKey = $carrierCode . '|' . md5(json_encode($context->toArray()));
        $cache    = Mage::app()->getCache(); // 或自定义 LRU
        if (($hit = $cache->load($cacheKey)) !== false) {
            return (int) $hit;
        }
        $id = /* ...走 resolver... */;
        $cache->save((string) $id, $cacheKey, ['xfe_carrier_account'], 300);
        return $id;
    }
}
```

缓存 key 不含订单号或敏感字段,只含业务上下文。Tag `xfe_carrier_account` 保证后台修改账号时自动失效。

### 5.2 测试隔离

新模块在写测试时,可以传入任何 `CarrierCredentialResolverInterface` 的 mock 实现,无需真表:

```php
class FakeResolver implements XFE_Carrier_Model_CarrierCredentialResolverInterface
{
    public function resolve($carrierId, $context, $fallback = true) { /* 测试返回 */ }
}

// 在测试 bootstrap 里替换配置节点
Mage::getConfig()->setNode('xfe/carrier/credential_resolver', 'fake_module/fake_resolver');
```

---

## 6. 行动清单(按 ROI 排序)

| 优先级 | 行动 | 涉及文件 | 风险 |
|--------|------|----------|------|
| P0 | 新增 `CredentialResolver.php` + `CarrierCredentialResolverInterface.php` | 2 个新文件 | 零(纯新增) |
| P0 | 给 `XFE_Carrier_Model_Service_Rule_Resolver` 加 `implements` | 1 行改动 | 零(契约声明) |
| P0 | `config.xml` 新增 `<xfe><carrier><credential_resolver>` 节点 | 3 行 XML | 零 |
| P1 | **强制**: 新模块统一走 `xfe_carrier/credential_resolver` 别名 | 代码评审 | 低 |
| P2 | 现有 `XFE_OAuth2` 改造为走 Facade(只读主档) | 1 个文件 | 低 |
| P3 | `XFE_DocumentUpload` 收敛到 Facade(需先做字段对齐) | 1+ 个文件 + 1+ 个 upgrade script | **高**,不在本提案 |
| ❌ 不要 | 在 Resolver 里塞 HTTP、Observer、日志、缓存 | — | — |

---

## 7. 验收标准

完成 P0 后,需要满足:

1. ✅ 在 `app/code/community/XFE/Carrier/Model/` 下存在 `CredentialResolver.php` 与 `CarrierCredentialResolverInterface.php`
2. ✅ `XFE_Carrier_Model_Service_Rule_Resolver` 已 `implements XFE_Carrier_Model_CarrierCredentialResolverInterface`
3. ✅ `config.xml` 的 `<xfe><carrier><credential_resolver>` 节点值为 `xfe_carrier/service_rule_resolver`
4. ✅ 任意业务模块通过 `Mage::getModel('xfe_carrier/credential_resolver')` 能拿到 Facade 实例
5. ✅ Facade 的 `resolveAccount('fedex', $ctx)` 返回 `XFE_Carrier_Model_Carrier_Account` 实例(与现有 Resolver 直接调用结果一致)
6. ✅ `tests/` 目录下有 `CredentialResolverTest.php` 覆盖 3 个 case:规则命中、规则未命中(回退)、carriercode 不存在

---

## 8. 关联文档

- 探索报告(2026-08-10): "面单打印如何调用承运商账号" 内部会话
- 代码入口: `app/code/community/XFE/Carrier/Model/Service/Rule/Resolver.php:74`
- 事件契约: `app/code/community/XFE/LabelPrint/Helper/Data.php:145` (`dispatchLabelResponse`)
- 现有 Facade(参考): `XFE_Carrier_Model_Service_Registry::ruleResolver()` 单例模式