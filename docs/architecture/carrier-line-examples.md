# Carrier 线路公司配置示例

> 主题:用真实业务场景演示 **"**承运商(carrier)** + **线路公司(account)**"** 的配置与调用,让 [`carrier-facade.md`](./carrier-facade.md) 和 [`carrier-observer-events.md`](./carrier-observer-events.md) 的抽象方案落地。
>
> 状态:**示例文档**(2026-08-11)
>
> 前置: [`carrier-facade.md`](./carrier-facade.md)、[`carrier-observer-events.md`](./carrier-observer-events.md)
>
> 关键术语对齐:
> - **承运商 (carrier)** = `xfe_carrier.code`,代表一家快递公司(如`sfexpress`、`fedex`)
> - **线路公司 (line_account)** = `xfe_carrier_carrier_account.account_name`,代表同一承运商下的不同服务渠道(如 `SF-International`、`SF-Economy`)

---

## 1. 业务背景

一家跨境电商典型场景:

| 承运商 (carrier) | 线路公司 (line_account) | 适用场景 |
|---|---------|---------|
| **顺丰** (sfexpress) | SF-International | 国际标快, 5-7 天 |
| | SF-Economy | 国际特惠, 7-15 天 |
| | SF-Domestic-Express | 国内顺丰即日 |
| **联邦快递** (fedex) | FedEx-IE | International Economy, 4-6 天 |
| | FedEx-IP | International Priority, 1-3 天 |
| **中国邮政** (chinapost) | ChinaPost-EMS | 经济型 |
| | ChinaPost-Epacket | 轻小件 |

每条线路公司对应 `xfe_carrier_carrier_account` 的一行,有自己独立的 `api_key` / `endpoint_url` / 账号属性。

---

## 2. 基础数据示例(SQL seed 风格)

> **注意**: 这里用 SQL 形式表达便于阅读,实际后台通过 `System → Carrier` 录入,**不要**在生产环境直接跑这些 SQL。

### 2.1 承运商 (4 家)

```sql
-- xfe_carrier
INSERT INTO xfe_carrier (code, name, status, sort_order, note) VALUES
('sfexpress',  '顺丰速运',     1, 10, 'SF Express'),
('fedex',      '联邦快递',     1, 20, 'FedEx'),
('chinapost',  '中国邮政',     1, 30, 'China Post'),
('dhl',        'DHL',         1, 40, 'DHL Express');
```

### 2.2 线路公司 (同一承运商下多个渠道)

```sql
-- xfe_carrier_carrier_account
-- 凭据字段仅示例,真实值在后台加密存储
INSERT INTO xfe_carrier_carrier_account
    (carrier_id, account_name, account_no, api_key, api_secret, username, password, endpoint_url, status, sort_order, note)
VALUES
-- 顺丰 3 条线路
((SELECT entity_id FROM xfe_carrier WHERE code='sfexpress'),
 'SF-International', 'SFINT001', 'AKIA-SFINT-XXXX', 'secret-sfint-XXXX',
 NULL, NULL, 'https://api.sf-express.com/std/v1/', 1, 10, '国际标快'),
((SELECT entity_id FROM xfe_carrier WHERE code='sfexpress'),
 'SF-Economy', 'SFECO001', 'AKIA-SFECO-XXXX', 'secret-sfeco-XXXX',
 NULL, NULL, 'https://api.sf-express.com/economy/v1/', 1, 20, '国际特惠'),
((SELECT entity_id FROM xfe_carrier WHERE code='sfexpress'),
 'SF-Domestic-Express', 'SFDOM001', 'AKIA-SFDOM-XXXX', 'secret-sfdom-XXXX',
 NULL, NULL, 'https://api.sf-express.com/std/v2/', 1, 30, '国内顺丰即日'),

-- FedEx 2 条线路
((SELECT entity_id FROM xfe_carrier WHERE code='fedex'),
 'FedEx-IE', 'FDXIE01', 'fedex-ie-key-XXXX', 'secret-ie-XXXX',
 NULL, NULL, 'https://apis.fedex.com/ship/v1', 1, 10, 'International Economy'),
((SELECT entity_id FROM xfe_carrier WHERE code='fedex'),
 'FedEx-IP', 'FDXIP01', 'fedex-ip-key-XXXX', 'secret-ip-XXXX',
 NULL, NULL, 'https://apis.fedex.com/ship/v1', 1, 20, 'International Priority'),

-- 中国邮政 2 条
((SELECT entity_id FROM xfe_carrier WHERE code='chinapost'),
 'ChinaPost-EMS', 'CPEMS01', NULL, NULL,
 'admin@example.com', 'encrypted-password', 'https://api.11183.com.cn/ems/v1/', 1, 10, 'EMS 经济型'),
((SELECT entity_id FROM xfe_carrier WHERE code='chinapost'),
 'ChinaPost-Epacket', 'CPEP01', NULL, NULL,
 'admin@example.com', 'encrypted-password', 'https://api.11183.com.cn/epacket/v1/', 1, 20, 'ePacket 轻小件');
```

### 2.3 规则:按"国家 + 重量"挑线路

```sql
-- xfe_carrier_carrier_rule
-- 规则可绑定到具体的 account_id(线路公司),实现"按场景挑线路"
INSERT INTO xfe_carrier_carrier_rule
    (carrier_id, account_id, name, module_code, status, priority, sort_order)
VALUES
-- 顺丰国际 — 美国/加拿大,重量 0-2kg
((SELECT entity_id FROM xfe_carrier WHERE code='sfexpress'),
 (SELECT account_id FROM xfe_carrier_carrier_account WHERE account_name='SF-International'),
 'SF-INT US/CA ≤2kg', 'account', 1, 100, 10),

-- 顺丰国际 — 欧洲,重量 2-5kg
((SELECT entity_id FROM xfe_carrier WHERE code='sfexpress'),
 (SELECT account_id FROM xfe_carrier_carrier_account WHERE account_name='SF-International'),
 'SF-INT EU 2-5kg', 'account', 1, 90, 20),

-- 顺丰特惠 — 任何国家,重量 5kg+
((SELECT entity_id FROM xfe_carrier WHERE code='sfexpress'),
 (SELECT account_id FROM xfe_carrier_carrier_account WHERE account_name='SF-Economy'),
 'SF-ECO ≥5kg', 'account', 1, 80, 30),

-- FedEx IE — 欧洲,重量 0-30kg
((SELECT entity_id FROM xfe_carrier WHERE code='fedex'),
 (SELECT account_id FROM xfe_carrier_carrier_account WHERE account_name='FedEx-IE'),
 'FedEx-IE EU', 'account', 1, 100, 10);
```

### 2.4 规则条件(`xfe_carrier_rule_condition_group` + `xfe_carrier_rule_condition`)

```sql
-- 拿 "SF-INT US/CA ≤2kg" 规则举例
-- group_id = 1 (aggregator=ALL, 即所有条件必须同时满足)
INSERT INTO xfe_carrier_rule_condition_group (rule_id, parent_group_id, aggregator, sort_order)
VALUES
(1, NULL, 'all', 1);

-- group_id = 1 下的 3 个条件
INSERT INTO xfe_carrier_rule_condition
    (group_id, attribute, operator, value, sort_order)
VALUES
(1, 'country_code',   'in',     'US,CA',           1),  -- 国家是美国或加拿大
(1, 'package_weight', 'lteq',   '2',               2),  -- 重量 ≤ 2kg
(1, 'order_amount',   'gteq',   '0',               3);  -- 订单金额 ≥ 0
```

> **典型规则匹配链路** (来自 `Resolver.php:74-345`):
> 1. 取 `carrier='sfexpress'` 的所有 `module_code='account' AND status=1` 规则
> 2. 按 `priority DESC → updated_at DESC → sort_order ASC → rule_id ASC` 排序
> 3. 第一条**条件树全部满足**的规则胜出,胜出规则的 `account_id` 即被选用
> 4. 全部不命中 → fallback 取 `updated_at DESC` 的第一个 status=1 账号

---

## 3. 业务模块调用:完整示例

### 3.1 场景描述

订单 #100001 配送到美国,重量 1.5kg,金额 $50。

预期匹配路径:
```
country_code=US, package_weight=1.5kg
   ↓
匹配 SF-INT US/CA ≤2kg (priority=100)
   ↓
account_id = SF-International
   ↓
拿到 api_key="AKIA-SFINT-XXXX", endpoint_url="https://api.sf-express.com/std/v1/"
```

### 3.2 业务模块代码 [`carrier-facade.md §3.5`](./carrier-facade.md) 落地

```php
<?php
class XFE_NewLogistics_Model_Shipment
{
    public function createLabel(Mage_Sales_Model_Order $order)
    {
        // 1) 业务模块组装 MatchContext
        $ctx = XFE_Carrier_Model_Service_Rule_MatchContext::create([
            'country_code'   => $order->getShippingAddress()->getCountry(), // 'US'
            'package_weight' => $this->_calcWeight($order),                 // 1.5
            'order_amount'   => (float) $order->getGrandTotal(),             // 50.0
        ]);

        // 2) Facade 拿线路公司
        $resolver = Mage::getModel('xfe_carrier/credential_resolver');
        $account  = $resolver->resolveAccount('sfexpress', $ctx);

        //      ↑ 内部触发 Resolver 排序 → 命中 rule_id=1
        //      ↑ 命中后 dispatchEvent('xfe_carrier_account_used', ...) /* ← 见 §4.1 */

        if (!$account) {
            return ['error' => 'no_line_configured'];
        }

        // 3) 业务模块自己发 HTTP(SF International 走 v1 协议)
        $payload = [
            'order_no'      => $order->getIncrementId(),
            'dest_country'  => $order->getShippingAddress()->getCountry(),
            'weight'        => $this->_calcWeight($order),
            'items'         => $this->_buildItems($order),
        ];

        $response = $this->_httpPost(
            $account->getEndpointUrl() . '/orders',
            $payload,
            [
                'X-API-Key'    => $account->getApiKey(),
                'X-API-Secret' => $account->getApiSecret(),
            ]
        );

        // 4) 落 LibraryPrint
        $labelResponse = new Varien_Object([
            'label_content'      => $response['pdf'],
            'label_format'       => 'pdf',
            'tracking_number_id' => $response['tracking_no'],
        ]);
        Mage::helper('xfe_labelprint')->dispatchLabelResponse(
            $order, 'xfe_newlogistics', $labelResponse
        );
    }
}
```

### 3.3 预期日志

```
[INFO] xfe_carrier_account_used carrier_id=1 account_id=1 rule_id=1 via=rule used_at=2026-08-11 15:30:42
[INFO] xfe_newlogistics: POST https://api.sf-express.com/std/v1/orders HTTP/1.1 200 OK
[INFO] xfe_labelprint_response_received order_id=100001 carrier_module=xfe_newlogistics
```

---

## 4. Observer 订阅:3 个真实场景

### 4.1 场景 A:审计日志(只记录 ID,绝不记录凭据)

```php
<?php
/**
 * 新模块: XFE_Audit
 * 订阅账号/FTP/Logo 使用事件,写入自家 audit_log 表
 */
class XFE_Audit_Model_Observer
{
    public function onCarrierAccountUsed(Varien_Event_Observer $observer)
    {
        $e = $observer->getEvent();
        // 全部从 event payload 拿,绝不 reload account 对象取凭据
        Mage::getModel('xfe_audit/usage_log')
            ->setData([
                'resource_type' => 'carrier_account',
                'resource_id'   => $e->getData('account_id'),
                'carrier_id'    => $e->getData('carrier_id'),
                'rule_id'       => $e->getData('rule_id'),
                'via'           => $e->getData('via'),
                'used_at'       => $e->getData('used_at'),
            ])
            ->save();
    }

    public function onCarrierFtpAccountUsed(Varien_Event_Observer $observer)
    {
        $e = $observer->getEvent();
        Mage::getModel('xfe_audit/usage_log')
            ->setData([
                'resource_type' => 'carrier_ftp_account',
                'resource_id'   => $e->getData('ftp_account_id'),
                'carrier_id'    => $e->getData('carrier_id'),
                'rule_id'       => $e->getData('rule_id'),
                'via'           => $e->getData('via'),
                'used_at'       => $e->getData('used_at'),
            ])
            ->save();
    }

    public function onCarrierLogoUsed(Varien_Event_Observer $observer)
    {
        $e = $observer->getEvent();
        Mage::getModel('xfe_audit/usage_log')
            ->setData([
                'resource_type' => 'carrier_logo',
                'resource_id'   => $e->getData('logo_id'),
                'carrier_id'    => $e->getData('carrier_id'),
                'size_type'     => $e->getData('size_type'),
                'via'           => $e->getData('via'),
                'used_at'       => $e->getData('used_at'),
            ])
            ->save();
    }
}
```

注册 (XFE_Audit/etc/config.xml):

```xml
<global>
    <events>
        <xfe_carrier_account_used>
            <observers>
                <xfe_audit_carrier_account_used>
                    <class>xfe_audit/observer</class>
                    <method>onCarrierAccountUsed</method>
                </xfe_audit_carrier_account_used>
            </observers>
        </xfe_carrier_account_used>

        <xfe_carrier_ftp_account_used>
            <observers>
                <xfe_audit_carrier_ftp_account_used>
                    <class>xfe_audit/observer</class>
                    <method>onCarrierFtpAccountUsed</method>
                </xfe_audit_carrier_ftp_account_used>
            </observers>
        </xfe_carrier_ftp_account_used>

        <xfe_carrier_logo_used>
            <observers>
                <xfe_audit_carrier_logo_used>
                    <class>xfe_audit/observer</class>
                    <method>onCarrierLogoUsed</method>
                </xfe_audit_carrier_logo_used>
            </observers>
        </xfe_carrier_logo_used>
    </events>
</global>
```

### 4.2 场景 B:限额告警(账号级别每日用量计数)

```php
<?php
/**
 * 新模块: XFE_QuotaGuard
 * 每天 23:00 触发,如果某账号当日被使用 >1000 次,发邮件告警。
 * 完全事件驱动,XFE_Carrier 不感知。
 */
class XFE_QuotaGuard_Model_Observer
{
    public function onCarrierAccountUsed(Varien_Event_Observer $observer)
    {
        $accountId = (int) $observer->getEvent()->getData('account_id');
        $today     = date('Y-m-d');

        $counter = Mage::getModel('xfe_quota/daily_counter')
            ->loadByKey("carrier_account:$accountId:$today");

        $counter->setCount($counter->getCount() + 1)->save();

        if ($counter->getCount() === 1000) {
            Mage::helper('xfe_quota/notify')->sendAlert(
                'carrier_account',
                $accountId,
                'Daily usage reached 1000'
            );
        }
    }
}
```

### 4.3 场景 C:配置变更通知(配合 CRUD 事件)

```php
<?php
/**
 * 新模块: XFE_Notify
 * 账号/FTP 账号被管理员修改后,Slack 通知 DevOps
 */
class XFE_Notify_Model_Observer
{
    public function onCarrierAccountSaved(Varien_Event_Observer $observer)
    {
        $e = $observer->getEvent();
        Mage::helper('xfe_notify/slack')
            ->send(sprintf(
                ':pencil2: Carrier account #%d (carrier #%d) %s by %s',
                $e->getData('account_id'),
                $e->getData('carrier_id'),
                $e->getData('is_new') ? 'created' : 'updated',
                Mage::getSingleton('admin/session')->getUser()->getUsername()
            ));
    }

    public function onCarrierAccountDeleted(Varien_Event_Observer $observer)
    {
        $e = $observer->getEvent();
        Mage::helper('xfe_notify/slack')
            ->send(sprintf(
                ':wastebasket: Carrier account #%d (carrier #%d) deleted by %s',
                $e->getData('account_id'),
                $e->getData('carrier_id'),
                Mage::getSingleton('admin/session')->getUser()->getUsername()
            ));
    }
}
```

---

## 5. 端到端时序图

```
管理员                   Magento Admin          XFE_Carrier                   XFE_Audit
  │                          │                       │                            │
  │━━ 编辑 SF-International  ━━━━━>│                  │                            │
  │                          │         _afterSave()  │                            │
  │                          │             ┌────────>│                            │
  │                          │             │ dispatch EVENT_ACCOUNT_SAVED         │
  │                          │             │         (account_id=1, carrier_id=1) │
  │                          │             │                  │                  │
  │                          │             │                  ┣━━━━━━━━━━━━━━━━━>│
  │                          │             │                  │  Slack 通知        │
  │                          │             │                  │                  │
  ╎                          ╎              ╎                  ╎                  ╎
  │                          │                       │                            │
  │━━ 订单 #100001 提交 ───────>│                       │                            │
  │                          │  POST /shipment/create  │                            │
  │                          │   ↓                     │                            │
  │                          │  XFE_NewLogistics       │                            │
  │                          │  createLabel()          │                            │
  │                          │         ┌──────────────>│                            │
  │                          │         │ Facade:       │                            │
  │                          │         │ resolveAccount│                            │
  │                          │         │ 'sfexpress'   │                            │
  │                          │         │               │                            │
  │                          │         │ Resolver 命中 │                            │
  │                          │         │ rule_id=1     │                            │
  │                          │         │               │                            │
  │                          │         │ dispatch EVENT_ACCOUNT_USED              │
  │                          │         │ (account_id=1 │                            │
  │                          │         │  rule_id=1    │                            │
  │                          │         │  via=rule)    │                            │
  │                          │         │               ┣━━━━━━━━━━━━━━━━━━━━━━━━>│
  │                          │         │               │  audit_log 写入             │
  │                          │         │               │                            │
  │                          │         │ <─────────────┤                            │
  │                          │         │ return account│                            │
  │                          │         │ (含 api_key) │                            │
  │                          │         │               │                            │
  │                          │  HTTP POST 顺丰 API     │                            │
  │                          │  ← 200 OK + PDF         │                            │
  │                          │  LabelPrint 落库 (事件)  │                            │
```

---

## 6. 决策清单(真假对比)

| 决策点 | ❌ 错误做法 | ✅ 正确做法 |
|---|---|---|
| 凭据存哪 | 存到 `custom_config` / `core_config_data` / JSON 字段 | 存到 `xfe_carrier_carrier_account` 的 `api_key` / `api_secret` 列 |
| 凭据如何传 | `getAccount()` 把 `api_key` 塞进 event payload / 日志 / API 响应 | 只返回 `account_id`,需要时业务模块自己 `load()` |
| 业务模块挑线路 | 写自己的 if/else `if ($country=='US') use SF-INT` | 调 `Facade::resolveAccount('sfexpress', $ctx)` 让 Rule 引擎挑 |
| 业务模块改规则 | 在自己的模块里改 `xfe_carrier_carrier_rule` 表 | 通过 admin 后台改,**业务模块零修改** |
| 业务模块要"听"账号被用了 | 注入 `XFE_Carrier` 私有类、反射、改源码 | 订阅 `xfe_carrier_account_used` 事件 |
| 多模块共享同一线路 | 每个模块各自再存一份 | 所有模块都通过 `Facade` 拿同一行的 `account_id` |
| 计费/限额 | 业务模块自己维护计数器 | 订阅 `EVENT_ACCOUNT_USED`,在独立模块里计数 |

---

## 7. 验收检查清单(基于本例)

新建一个 `XFE_NewLogistics` 模块并接入时,**必须满足**:

1. ✅ 业务模块源文件 grep **不含** `xfe_carrier_carrier_account` / `xfe_carrier_carrier_rule` 字符串
2. ✅ 业务模块源文件 grep **不含** `api_key` / `api_secret` 字面量赋值
3. ✅ 业务模块源文件 grep **不含** `Mage::getModel('xfe_carrier/carrier_account')`
4. ✅ 业务模块源文件 grep **不含** `event->getData('api_key')` 或类似 payload 读取
5. ✅ 业务模块 `composer.json` / `app/etc/modules/*.xml` 不依赖 `XFE_Carrier` 模块(运行时依赖即可)
6. ✅ 业务模块的事件订阅在**自己的** `etc/config.xml`(不是 XFE_Carrier 的)
7. ✅ 单元测试覆盖 3 个 case:规则命中 / fallback / 不存在 carrier

---

## 8. 关联文档

- 理论: [`carrier-facade.md`](./carrier-facade.md) — Facade 设计
- 事件: [`carrier-observer-events.md`](./carrier-observer-events.md) — 6 个事件清单
- 数据表: `app/code/community/XFE/Carrier/sql/xfe_carrier_setup/upgrade-1.0.0-1.0.1.php` (账号表)
- Resolver: `app/code/community/XFE/Carrier/Model/Service/Rule/Resolver.php:74-345`
- 真实参考: `XFE_LabelPrint/Helper/Data.php:8` (事件常量定义风格)