# Carrier Observer 事件方案

> 主题:为 `XFE_Carrier` 新增 **Observer / 事件**,让其他模块订阅账号、FTP 账号、Logo 的"使用"与"配置变更"事件,做到**完全单向依赖**。
>
> 状态:**提案**(2026-08-10)
>
> 前置: [`carrier-facade.md`](./carrier-facade.md) 已完成
>
> 适用场景:其他业务模块需要"听说"账号/FTP/Logo 被使用了(审计/对账/统计/缓存清理/...)
>
> **配套文档**:
> - [`carrier-facade.md`](./carrier-facade.md) — Facade 主动调用方案
> - [`carrier-line-examples.md`](./carrier-line-examples.md) — 真实线路公司配置 + 3 个 Observer 订阅样板

---

## 1. 现状与目标

### 1.1 当前现状(已审计)

- `XFE_Carrier` **零 Observer 类**,`etc/config.xml` 没有 `<events>` / `<observers>` 节点
- 数据库没有 `usage_log` / `last_used_at` / `accessed_at` 字段,只有 `created_at` / `updated_at`
- 资源"被使用"的入口:
  - **账号**:`Model/Service/Rule/Resolver.php:293`、`Resolver.php:327` (fallback)
  - **Logo**:`Model/Service/Rule/Resolver.php:298`、`Resolver.php:335` (fallback)、`Model/Carrier.php:67` (`getLogoUrl`)
  - **FTP 账号**:**目前无业务侧 load 路径**(`controllers/Adminhtml/CarrierController.php:388/437/494` 都是 CRUD),需要在 Resolver 中补读 `rule.ftp_account_id`
- 模块已有 1 处事件 dispatch 样例:`Model/Carrier.php:100` `xfe_carrier_carrier_delete_before`
- 命名风格参考:`XFE_LabelPrint_Helper_Data::EVENT_RESPONSE_RECEIVED = 'xfe_labelprint_response_received'`

### 1.2 目标

- 其他模块通过订阅事件 **知道"账号/FTP/Logo 被用了"**,无需直接调用 `XFE_Carrier`
- 事件 payload **只传 ID + 时间戳 + 来源上下文**(如 `carrier_id` / `rule_id`),**不携带任何凭据**(api_key / api_secret / password)
- 事件 hook 点位于 **Resolver(使用入口)+ Model(CRUD 入口)**,职责分离清晰

---

## 2. 事件清单(共 6 个)

| # | 事件名 | hook 点 | 触发时机 | payload 字段 |
|---|--------|---------|----------|------------|
| E1 | `xfe_carrier_account_used` | Resolver `_findTargetId` + `_findDefaultTarget` | 业务模块挑出账号 **成功** 时 | `account_id`, `carrier_id`, `rule_id?`, `via`, `used_at` |
| E2 | `xfe_carrier_ftp_account_used` | Resolver 新增读路径 | 业务模块挑出 FTP 账号成功时 | `ftp_account_id`, `carrier_id`, `rule_id?`, `used_at` |
| E3 | `xfe_carrier_logo_used` | Resolver `_findTargetId` + `_findDefaultTarget` + `Model/Carrier::getLogoUrl` | 业务模块挑出 Logo / 拼接 Logo URL 时 | `logo_id`, `carrier_id`, `rule_id?`, `size_type?`, `used_at` |
| E4 | `xfe_carrier_account_saved` | Model `Carrier_Account::_afterSave` | 管理员保存账号 | `account_id`, `carrier_id`, `is_new`, `saved_at` |
| E5 | `xfe_carrier_account_deleted` | Model `Carrier_Account::_afterDelete` | 管理员删除账号 | `account_id`, `carrier_id`, `deleted_at` |
| E6 | `xfe_carrier_ftp_account_saved` / `_deleted` | Model `Carrier_FtpAccount::_afterSave` / `_afterDelete` | FTP 账号配置变更 | `ftp_account_id`, `carrier_id`, `is_new?`, `at` |

> **命名约定**(沿用现有 `xfe_labelprint_response_received` 风格):
> `xfe_<module>_<entity>_<verb_past_tense>`,动词用过去式(`used`/`saved`/`deleted`)。

---

## 3. Payload Schema(精确字段)

### 3.1 E1 `xfe_carrier_account_used`

```php
Mage::dispatchEvent('xfe_carrier_account_used', array(
    'account_id' => (int) $row->getId(),   // 主键
    'carrier_id' => (int) $row->getCarrierId(),
    'rule_id'    => (int) $ruleId ?: null, // null 表示走 fallback(规则未命中)
    'via'        => (string) $via,         // 'rule' | 'fallback'
    'used_at'    => (string) Varien_Date::now(), // ISO 时间
));
```

> **明确不携带**:`api_key` / `api_secret` / `endpoint_url` / `username` / `password` / `account_name`。

### 3.2 E2 `xfe_carrier_ftp_account_used`

```php
Mage::dispatchEvent('xfe_carrier_ftp_account_used', array(
    'ftp_account_id' => (int) $row->getId(),
    'carrier_id'     => (int) $row->getCarrierId(),
    'rule_id'        => (int) $ruleId ?: null,
    'via'            => (string) $via,
    'used_at'        => (string) Varien_Date::now(),
));
```

> **明确不携带**:`host` / `username` / `password` / `remote_path` / `port`。

### 3.3 E3 `xfe_carrier_logo_used`

```php
Mage::dispatchEvent('xfe_carrier_logo_used', array(
    'logo_id'    => (int) $row->getId(),
    'carrier_id' => (int) $row->getCarrierId(),
    'rule_id'    => (int) $ruleId ?: null,
    'size_type'  => (string) $sizeType ?: null, // 仅当从 getLogoUrl 触发时携带
    'via'        => (string) $via,
    'used_at'    => (string) Varien_Date::now(),
));
```

> **明确不携带**:`path`(文件系统路径)、二进制内容。

### 3.4 E4–E6 CRUD 事件(payload 形态)

```php
Mage::dispatchEvent('xfe_carrier_account_saved', array(
    'account_id' => (int) $this->getId(),
    'carrier_id' => (int) $this->getCarrierId(),
    'is_new'     => (bool) $isObjectNew,
    'saved_at'   => (string) Varien_Date::now(),
));

Mage::dispatchEvent('xfe_carrier_account_deleted', array(
    'account_id' => (int) $this->getId(),
    'carrier_id' => (int) $this->getCarrierId(),
    'deleted_at' => (string) Varien_Date::now(),
));

Mage::dispatchEvent('xfe_carrier_ftp_account_saved', array(
    'ftp_account_id' => (int) $this->getId(),
    'carrier_id'     => (int) $this->getCarrierId(),
    'is_new'         => (bool) $isObjectNew,
    'saved_at'       => (string) Varien_Date::now(),
));

Mage::dispatchEvent('xfe_carrier_ftp_account_deleted', array(
    'ftp_account_id' => (int) $this->getId(),
    'carrier_id'     => (int) $this->getCarrierId(),
    'deleted_at'     => (string) Varien_Date::now(),
));
```

---

## 4. 常量定义(单一来源)

**新文件**: `app/code/community/XFE/Carrier/Helper/Data.php`(扩展现有 Helper,新增 const)

```php
<?php
class XFE_Carrier_Helper_Data extends Mage_Core_Helper_Abstract
{
    // ... 现有内容 ...

    /* --------------------------------------------------------------
     *  Event names (新增)
     * -------------------------------------------------------------- */

    const EVENT_ACCOUNT_USED        = 'xfe_carrier_account_used';
    const EVENT_ACCOUNT_SAVED       = 'xfe_carrier_account_saved';
    const EVENT_ACCOUNT_DELETED     = 'xfe_carrier_account_deleted';

    const EVENT_FTP_ACCOUNT_USED    = 'xfe_carrier_ftp_account_used';
    const EVENT_FTP_ACCOUNT_SAVED   = 'xfe_carrier_ftp_account_saved';
    const EVENT_FTP_ACCOUNT_DELETED = 'xfe_carrier_ftp_account_deleted';

    const EVENT_LOGO_USED           = 'xfe_carrier_logo_used';
}
```

> 业务模块订阅时**优先引用常量**,不要硬编码字符串,避免拼写错误。

---

## 5. Hook 点代码改造

### 5.1 Resolver 使用事件(账号 / Logo)

**改动文件**: `app/code/community/XFE/Carrier/Model/Service/Rule/Resolver.php`

```php
// 在 _findTargetId() 内,account load 成功后追加:
if ($targetType === self::TARGET_ACCOUNT) {
    $rule = Mage::getModel('xfe_carrier/carrier_rule')->load($ruleId);
    $accountId = $rule && $rule->getId() ? (int)$rule->getAccountId() : 0;
    if (!$accountId) {
        return null;
    }
    $row = Mage::getModel('xfe_carrier/carrier_account')->load($accountId);
    if (!$row->getId() || (int)$row->getStatus() !== 1) {
        return null;
    }
    // ▼ 新增:账号被使用事件
    $this->_dispatchAccountUsed((int)$row->getId(), (int)$row->getCarrierId(),
        $ruleId, 'rule');
} elseif ($targetType === self::TARGET_LOGO) {
    $row = Mage::getModel('xfe_carrier/carrier_logo')->getCollection()
        ->addFieldToFilter('rule_id', $ruleId)
        ->setOrder('sort_order', 'ASC')
        ->getFirstItem();
    // ▼ 新增:Logo 被使用事件(若找到)
    if ($row && $row->getId()) {
        $this->_dispatchLogoUsed((int)$row->getId(), (int)$row->getCarrierId(),
            $ruleId, null, 'rule');
    }
}

// _findDefaultTarget() 内同样的 2 处:

// account fallback 成功时:
if ($row && $row->getId()) {
    $this->_dispatchAccountUsed((int)$row->getId(), (int)$row->getCarrierId(),
        null, 'fallback');
    return (int)$row->getId();
}

// logo fallback 成功时:
if ($row && $row->getId()) {
    $this->_dispatchLogoUsed((int)$row->getId(), (int)$row->getCarrierId(),
        null, null, 'fallback');
    return (int)$row->getId();
}

// 在类内新增两个私有 dispatch 方法:
private function _dispatchAccountUsed($accountId, $carrierId, $ruleId, $via)
{
    Mage::dispatchEvent(XFE_Carrier_Helper_Data::EVENT_ACCOUNT_USED, array(
        'account_id' => $accountId,
        'carrier_id' => $carrierId,
        'rule_id'    => $ruleId ?: null,
        'via'        => $via,
        'used_at'    => Varien_Date::now(),
    ));
}

private function _dispatchLogoUsed($logoId, $carrierId, $ruleId, $sizeType, $via)
{
    Mage::dispatchEvent(XFE_Carrier_Helper_Data::EVENT_LOGO_USED, array(
        'logo_id'    => $logoId,
        'carrier_id' => $carrierId,
        'rule_id'    => $ruleId ?: null,
        'size_type'  => $sizeType,
        'via'        => $via,
        'used_at'    => Varien_Date::now(),
    ));
}
```

### 5.2 Resolver 新增 FTP 账号读取路径(本提案新增)

**改动文件**: `app/code/community/XFE/Carrier/Model/Service/Rule/Resolver.php`

引入新常量 + 在 `_findTargetId()` 增加分支:

```php
const TARGET_FTP_ACCOUNT = 'ftp_account';

protected function _findTargetId($targetType, $ruleId)
{
    if ($targetType === self::TARGET_FTP_ACCOUNT) {
        // 新增:FTP 账号走规则解析
        $rule = Mage::getModel('xfe_carrier/carrier_rule')->load($ruleId);
        $ftpId = $rule && $rule->getId() ? (int)$rule->getFtpAccountId() : 0;
        if (!$ftpId) {
            return null;
        }
        $row = Mage::getModel('xfe_carrier/carrier_ftp_account')->load($ftpId);
        if (!$row->getId() || (int)$row->getStatus() !== 1) {
            return null;
        }
        $this->_dispatchFtpAccountUsed((int)$row->getId(), (int)$row->getCarrierId(),
            $ruleId, 'rule');
        return (int)$row->getId();
    }
    // ... 原有的 account / logo 分支保留 ...
}

// fallback 路径(_findDefaultTarget)也补 FTP 分支:
protected function _findDefaultTarget($targetType, $carrierId)
{
    if ($targetType === self::TARGET_FTP_ACCOUNT) {
        $row = Mage::getModel('xfe_carrier/carrier_ftp_account')->getCollection()
            ->addFieldToFilter('carrier_id', $carrierId)
            ->addFieldToFilter('status', 1)
            ->setOrder('updated_at', 'DESC')
            ->getFirstItem();
        if ($row && $row->getId()) {
            $this->_dispatchFtpAccountUsed((int)$row->getId(), (int)$row->getCarrierId(),
                null, 'fallback');
            return (int)$row->getId();
        }
        return null;
    }
    // ... 原有的 account / logo 分支 ...
}

private function _dispatchFtpAccountUsed($ftpAccountId, $carrierId, $ruleId, $via)
{
    Mage::dispatchEvent(XFE_Carrier_Helper_Data::EVENT_FTP_ACCOUNT_USED, array(
        'ftp_account_id' => $ftpAccountId,
        'carrier_id'     => $carrierId,
        'rule_id'        => $ruleId ?: null,
        'via'            => $via,
        'used_at'        => Varien_Date::now(),
    ));
}
```

> 注意:此处**仅补"被使用"的读取路径**。FTP 账号的"谁可以访问"由业务模块通过 Facade (`CredentialResolver`) 新增方法获得,见下文 5.4。

### 5.3 Logo URL 入口事件

**改动文件**: `app/code/community/XFE/Carrier/Model/Carrier.php`

```php
public function getLogoUrl($logoType, $sizeType)
{
    foreach ($this->getLogos() as $logo) {
        if ($logo->getSizeType() === $sizeType) {
            $url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . $logo->getPath();
            // ▼ 新增:Logo 被使用事件(URL 访问)
            Mage::dispatchEvent(XFE_Carrier_Helper_Data::EVENT_LOGO_USED, array(
                'logo_id'    => (int) $logo->getId(),
                'carrier_id' => (int) $this->getId(),
                'rule_id'    => null,
                'size_type'  => (string) $sizeType,
                'via'        => 'url',
                'used_at'    => Varien_Date::now(),
            ));
            return $url;
        }
    }
    return null;
}
```

### 5.4 Facade 暴露 FTP 账号(Facade 补全)

**改动文件**: `app/code/community/XFE/Carrier/Model/CredentialResolver.php`(沿用 [`carrier-facade.md`](./carrier-facade.md) 设计的 Facade)

```php
/**
 * 与 resolveAccount 对称的 FTP 账号接口。
 * 业务模块拿到 ftp_account_id 后自行用 ftp_account 模型 load(但不读取凭据),
 * 或直接用 ftp_account_id 委托给 FTP 客户端模块处理。
 */
public function resolveFtpAccountId(
    $carrierCode,
    XFE_Carrier_Model_Service_Rule_MatchContext $context
) {
    $carrierId = $this->_resolveCarrierIdByCode($carrierCode);
    if (!$carrierId) {
        return null;
    }
    $resolver = $this->_getResolver();
    try {
        $result = $resolver->resolveFtp($carrierId, $context, true);
    } catch (XFE_Carrier_Exception_NoRuleMatch $e) {
        return null;
    }
    return $result->getFtpAccountId() ?: null;
}
```

> 接口 `CarrierCredentialResolverInterface` 同步扩展 `resolveFtp()` 方法(见 §6)。

### 5.5 Model CRUD 事件

**改动文件**:

- `app/code/community/XFE/Carrier/Model/Carrier/Account.php`
- `app/code/community/XFE/Carrier/Model/Carrier/FtpAccount.php`

```php
// Carrier/Account.php
protected function _afterSave()
{
    parent::_afterSave();
    Mage::dispatchEvent(XFE_Carrier_Helper_Data::EVENT_ACCOUNT_SAVED, array(
        'account_id' => (int) $this->getId(),
        'carrier_id' => (int) $this->getCarrierId(),
        'is_new'     => (bool) $this->isObjectNew(),
        'saved_at'   => Varien_Date::now(),
    ));
    return $this;
}

protected function _afterDelete()
{
    parent::_afterDelete();
    Mage::dispatchEvent(XFE_Carrier_Helper_Data::EVENT_ACCOUNT_DELETED, array(
        'account_id' => (int) $this->getId(),
        'carrier_id' => (int) $this->getCarrierId(),
        'deleted_at' => Varien_Date::now(),
    ));
    return $this;
}

// Carrier/FtpAccount.php 同模式(E6)
```

---

## 6. 接口扩展(契约同步)

**改动文件**: `app/code/community/XFE/Carrier/Model/CarrierCredentialResolverInterface.php`

```php
interface XFE_Carrier_Model_CarrierCredentialResolverInterface
{
    /** @return XFE_Carrier_Model_Service_Rule_MatchResult */
    public function resolve($carrierId, XFE_Carrier_Model_Service_Rule_MatchContext $context, $fallback = true);

    /**
     * 新增:FTP 账号解析
     * @return XFE_Carrier_Model_Service_Rule_MatchResult
     */
    public function resolveFtp($carrierId, XFE_Carrier_Model_Service_Rule_MatchContext $context, $fallback = true);
}
```

`XFE_Carrier_Model_Service_Rule_MatchResult` 同步新增 `getFtpAccountId()` 方法(零侵入:`return $this->getData('ftp_account_id')`)。

---

## 7. config.xml 注册 Observer 模板

**改动文件**: `app/code/community/XFE/Carrier/etc/config.xml`

```xml
<global>
    <!-- ... 已有节点 ... -->

    <events>
        <!-- 使用事件(其他业务模块最常订阅) -->
        <xfe_carrier_account_used>
            <observers>
                <!-- 留空:由其他 XFE_* 模块自行注册 listener
                     本模块不监听自己的"使用"事件 -->
            </observers>
        </xfe_carrier_account_used>

        <xfe_carrier_ftp_account_used>
            <observers/>
        </xfe_carrier_ftp_account_used>

        <xfe_carrier_logo_used>
            <observers/>
        </xfe_carrier_logo_used>

        <!-- CRUD 事件 -->
        <xfe_carrier_account_saved>
            <observers/>
        </xfe_carrier_account_saved>

        <xfe_carrier_account_deleted>
            <observers/>
        </xfe_carrier_account_deleted>

        <xfe_carrier_ftp_account_saved>
            <observers/>
        </xfe_carrier_ftp_account_saved>

        <xfe_carrier_ftp_account_deleted>
            <observers/>
        </xfe_carrier_ftp_account_deleted>
    </events>
</global>
```

> **关键设计**:`XFE_Carrier` **不为自己注册 listener**。Observer 节点留空(`<observers/>`),仅作为"事件命名空间登记",方便其他模块通过 `<xfe_carrier_account_used>` 节点挂载自己的 observer。这样做的好处:
>
> 1. 后台开发者一眼就能看到 `XFE_Carrier` 暴露了哪些事件
> 2. 其他模块的 listener 配置在自己的 `etc/config.xml` 里,各自独立
> 3. 解除 Magento 偶发的"事件未被触发"调试难题(命名空间显式可见)

---

## 8. 其他模块订阅样板代码

### 8.1 在其他模块的 `etc/config.xml` 注册 listener

```xml
<!-- 假设 XFE_Audit 模块想监听账号被使用 -->
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
    </events>
</global>
```

### 8.2 Observer 实现

```php
<?php
class XFE_Audit_Model_Observer
{
    /**
     * 注意:只能拿到 ID,没有任何凭据。
     * 如需详情,自己 load(那是订阅方的责任)。
     */
    public function onCarrierAccountUsed(Varien_Event_Observer $observer)
    {
        $event = $observer->getEvent();
        $data  = array(
            'account_id' => $event->getData('account_id'),
            'carrier_id' => $event->getData('carrier_id'),
            'rule_id'    => $event->getData('rule_id'),
            'via'        => $event->getData('via'),
            'used_at'    => $event->getData('used_at'),
        );

        // 写自己的 audit 表
        Mage::getModel('xfe_audit/usage_log')
            ->setData($data)
            ->save();
    }
}
```

---

## 9. 与 carrier-facade.md 的协同关系

```
┌──────────────────────────────────────────────────────────┐
│ 业务模块 (XFE_NewLogistics)                              │
│                                                          │
│  ┌─ 主动调用路径 ────────────┐  ┌─ 被动订阅路径 ──────┐  │
│  │ Mage::getModel(           │  │ <events> 注册        │  │
│  │   'xfe_carrier/           │  │   <xfe_carrier_      │  │
│  │    credential_resolver'   │  │    account_used>     │  │
│  │ )->resolveAccount(...)    │  │ </events>            │  │
│  └─────────────┬─────────────┘  └──────────┬───────────┘  │
└────────────────┼────────────────────────────┼──────────────┘
                 │                            │
                 ▼                            ▼
┌──────────────────────────────────────────────────────────┐
│ XFE_Carrier                                              │
│  ┌─ CredentialResolver (Facade) ─┐                      │
│  │   - resolveAccountId()         │ ← 业务模块主动调用    │
│  │   - resolveFtpAccountId()      │   → 内部 → Resolver │
│  └─────────────┬───────────────────┘                      │
│                │                                          │
│  ┌─ Resolver ──────────────────────┐                     │
│  │   _findTargetId()              │ ← 发使用事件        │
│  │   _findDefaultTarget()         │ ← 发使用事件        │
│  └────────────────────────────────┘                     │
│                                                          │
│  ┌─ Model _afterSave/_afterDelete ──┐                   │
│  │   Carrier_Account, Carrier_FtpAccount │ ← 发 CRUD 事件│
│  └────────────────────────────────────┘                  │
└──────────────────────────────────────────────────────────┘
```

**两条路径并存,不冲突**:
- 业务模块需要"拿到账号才能发 HTTP" → 用 **Facade** (主动)
- 业务模块需要"知道哪个账号被用了" → 订阅 **Observer** (被动)

---

## 10. 行动清单(按 ROI 排序)

| # | 优先级 | 行动 | 文件 | 风险 |
|---|--------|------|------|------|
| 1 | **P0** | `Helper/Data.php` 加 6 个 const | 1 文件 + N 行 | 零 |
| 2 | **P0** | `Resolver.php` 加 FTP 分支 + 3 个 `_dispatch*` 私有方法 + 6 处调用 | 1 文件 + ~40 行 | 低(纯增量) |
| 3 | **P0** | `Carrier.php::getLogoUrl()` 加 1 处 dispatch | 1 文件 + 8 行 | 零 |
| 4 | **P0** | `Carrier/Account.php` 加 `_afterSave` / `_afterDelete` 事件 | 1 文件 + 12 行 | 零 |
| 5 | **P0** | `Carrier/FtpAccount.php` 加 2 处 `_afterSave` / `_afterDelete` 事件 | 1 文件 + 12 行 | 零 |
| 6 | **P0** | `CredentialResolver.php` 加 `resolveFtpAccountId()` | 1 文件 + 15 行 | 零 |
| 7 | **P0** | `CarrierCredentialResolverInterface.php` 加 `resolveFtp()` | 1 文件 + 5 行 | 低(同步实现) |
| 8 | **P0** | `MatchResult` 加 `getFtpAccountId()` | 1 文件 + 3 行 | 零 |
| 9 | **P0** | `etc/config.xml` 加 6 个事件节点(`<observers/>` 空) | 1 文件 + 30 行 | 零 |
| 10 | P1 | 在其他业务模块订阅这些事件,验证端到端 | 1 个新测试模块 | — |

---

## 11. 验收标准

完成后需要满足:

1. ✅ `XFE_Carrier_Helper_Data` 暴露 6 个事件常量
2. ✅ `Model/Service/Rule/Resolver.php` 在 6 个"成功 load"位置 dispatch 事件,**Resolver 行为不变**(现有 `tests/Rule/ResolverIntegrationTest.php` 全绿)
3. ✅ `Model/Carrier.php::getLogoUrl()` 在成功返回 URL 前 dispatch `logo_used`
4. ✅ `Model/Carrier/Account.php` 和 `Model/Carrier/FtpAccount.php` 在 CRUD 后 dispatch 事件
5. ✅ `etc/config.xml` 含 6 个事件节点,**无 listener**(本模块不订阅自己)
6. ✅ `CredentialResolver::resolveFtpAccountId()` 能返回正确的 ftp_account_id
7. ✅ 新增 `tests/Carrier/ObserverEventTest.php`,覆盖 6 个事件至少各 1 个 case
8. ✅ payload **不含**任何凭据字段(单元测试断言 array_keys 不包含 `api_key`/`secret`/`password`/`host`/`username`/`path`)

---

## 12. 不做的事

- ❌ 不新增 `usage_log` 表/字段(订阅方自己决定是否持久化)
- ❌ 不在 `XFE_Carrier` 内部订阅自己的事件(避免无限循环风险)
- ❌ 不在 payload 里携带任何凭据(`api_key`/`secret`/`password`/`endpoint_url`/`host`/`path`)
- ❌ 不在 `_beforeDelete` 发事件(沿用现有 `xfe_carrier_carrier_delete_before` 风格,但**仅在新增 `_afterDelete` 才发**;`_beforeDelete` 留给未来清理用)
- ❌ 不动 `XFE_DocumentUpload`(它是平行模块,与本提案无关)

---

## 13. 关联文档

- 前置: [`carrier-facade.md`](./carrier-facade.md) — Facade 与本提案的协同图见 §9
- 参考事件模式: `app/code/community/XFE/LabelPrint/Helper/Data.php:8,145`
- 参考 Observer 注册: `app/code/community/XFE/LabelPrint/etc/config.xml:53-70`
- Resolver 实现位置: `app/code/community/XFE/Carrier/Model/Service/Rule/Resolver.php:284-345`
- Logo URL 入口: `app/code/community/XFE/Carrier/Model/Carrier.php:67`