# XFE_DocumentUpload 通过 XML 注入接入 XFE_Carrier 开发指南

> 主题：消除 DocumentUpload 的独立小作坊模式（accounts_json system config），通过 XFE_Injection XML 注入从 XFE_Carrier 统一主档读取账号。

> 状态：Draft（2026-09-17）

> 关联文档：
> - [decisions/0017-documentupload-injection-integration.md](./decisions/0017-documentupload-injection-integration.md)
> - [decisions/0015-injection-carrier-logistic-integration.md](./decisions/0015-injection-carrier-logistic-integration.md)
> - [decisions/0016-decouple-match-context.md](./decisions/0016-decouple-match-context.md)
> - [carrier-facade-deprecation.md](./carrier-facade-deprecation.md) §6.2 模块路线图


---

## 1. 目标与范围

### 1.1 业务目标

让 XFE_DocumentUpload 通过 XML 注入从 XFE_Carrier 读取账号，代替原本独立的 accounts_json system config。这是 ADR 0015 §6.2 路线图的第一步。

### 1.2 本轮范围

| 项目 | 是否在本轮 |
|------|----------|
| Carrier 适配器新增 listAccountsByCarrier service | ✅ |
| Carrier etc/injection.xml 加 service_carrier_list_accounts | ✅ |
| DocumentUpload etc/injection.xml 新建（hook + calling） | ✅ |
| DocumentUpload Colissimo adapter 新增 getAccountsViaInjection 旁路 | ✅ |
| DocumentUpload app/etc/modules 加 <XFE_Injection/> 依赖 | ✅ |
| 集成测试 DocumentUploadTest.php | ✅ |
| 删除 accounts_json system config 字段 | ❌ |
| 数据迁移（admin_system_config_value → xfe_carrier_carrier_account） | ❌ |
| 改动 Colissimo::getAccounts() 主流路径 | ❌ |

---

## 2. 设计要点

### 2.1 调用链

```
Colissimo::getAccountsViaInjection($contextValues)
  -> XFE_Injection_Model_Runner::trigger('hook_documentupload_resolve_accounts', $injCtx)
    -> 查找 calling_documentupload_list_accounts
      -> service_carrier_list_accounts::listAccountsByCarrier('colissimo', $contextValues)
        -> XFE_Carrier_Service_Account_CredentialViaInjection (适配器, 已有)
          -> 直接读 xfe_carrier_carrier_account 表 (carriers by code)
            -> 返回 array<int> account_ids
```

### 2.2 新 service vs 复用旧 service

| 维度 | resolveAccountId (ADR 0015) | listAccountsByCarrier (本轮新增) |
|------|--------------------------|------------------------------|
| 返回 | int 单个 account_id | array 多个 account_ids |
| 用途 | 按业务上下文挑账号 | 列该 carrier 所有可用账号 |
| 消费方 | XFE_Logistic | XFE_DocumentUpload |
| 触发逻辑 | Rule Resolver 规则匹配 | 不过规则, 直接按 carrier_code 过滤 |
| XML 参数 key | carrierCode + contextValues | carrierCode + contextValues |

---

## 3. Carrier 侧改造

### 3.1 适配器新增方法

文件：app/code/community/XFE/Carrier/Service/Account/CredentialViaInjection.php

在已有 resolveAccountId 之后新增：

```php
/**
 * 列出该 carrier_code 下的所有可用账号主键。
 *
 * 不走规则匹配, 直接按 carrier_code 过滤 is_active=1 的账号。
 * 用于 DocumentUpload 这类不需要规则挑选、仅需列出全部账号的场景。
 *
 * @param string $carrierCode 承运商 code
 * @param array $contextValues 业务上下文(可选, 当前未使用)
 * @return int[] account_id 列表, 无 carrier 或无账号时返回空数组
 */
public function listAccountsByCarrier($carrierCode, array $contextValues = array())
{
    $carrierCode = trim((string) $carrierCode);
    if ($carrierCode === '') {
        return array();
    }
    $carrierId = $this->_resolveCarrierIdByCode($carrierCode);
    if (!$carrierId) {
        return array();
    }
    if (!class_exists('Mage', false)) {
        return array(); // 单测无 Mage 时返回空
    }
    $collection = Mage::getModel('xfe_carrier/carrier_account')
        ->getCollection()
        ->addFieldToFilter('carrier_id', (int) $carrierId)
        ->addFieldToFilter('status', 1);
    $ids = array();
    foreach ($collection as $account) {
        $ids[] = (int) $account->getId();
    }
    return $ids;
}
```

### 3.2 Carrier injection.xml 加新 service

文件：app/code/community/XFE/Carrier/etc/injection.xml

在 <services> 节点加：

```xml
<service_carrier_list_accounts>
    <class>XFE_Carrier_Service_Account_CredentialViaInjection</class>
    <method>listAccountsByCarrier</method>
</service_carrier_list_accounts>
```

### 3.3 版本号

XFE_Carrier/etc/config.xml：1.0.17 → 1.0.18

---

## 4. DocumentUpload 侧改造

### 4.1 新建 etc/injection.xml

文件：app/code/community/XFE/DocumentUpload/etc/injection.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <modules>
        <XFE_DocumentUpload>
            <version>1.1.0</version>
        </XFE_DocumentUpload>
    </modules>
    <injection>
        <hooks>
            <hook_documentupload_resolve_accounts>
                <description>DocumentUpload 解析账号列表时触发</description>
            </hook_documentupload_resolve_accounts>
        </hooks>
        <callings>
            <calling id="calling_documentupload_list_accounts"
                     hook="hook_documentupload_resolve_accounts"
                     service="service_carrier_list_accounts"
                     method="listAccountsByCarrier">
                <argument name="carrierCode"   from="context.carrierCode"/>
                <argument name="contextValues" from="context.contextValues"/>
            </calling>
        </callings>
    </injection>
</config>
```

### 4.2 Colissimo adapter 新增旁路方法

文件：app/code/community/XFE/DocumentUpload/Model/Carrier/Colissimo.php

新增：

```php
/**
 * 旁路方法：通过 XML 注入从 XFE_Carrier 读取账号列表。
 *
 * 与 getAccounts() 共存。getAccounts() 仍读 system config (旧路径)，
 * 本方法走 XML 注入 (新路径)。两条路径行为可对照。
 *
 * 未来数据迁移时，把 getAccounts() 内部实现改为调用本方法即可。
 *
 * @param array $contextValues
 * @return int[] account_id 列表
 */
public function getAccountsViaInjection(array $contextValues = array())
{
    $injCtx = new XFE_Injection_Domain_InjectionContext(array(
        'carrierCode'   => $this->getCarrierCode(),
        'contextValues' => $contextValues,
    ));
    $result = XFE_Injection_Model_Runner::trigger(
        'hook_documentupload_resolve_accounts',
        $injCtx
    );
    return is_array($result->first()) ? $result->first() : array();
}
```

### 4.3 模块依赖

文件：app/etc/modules/XFE_DocumentUpload.xml

在 <depends> 中加 <XFE_Injection/>

### 4.4 版本号

XFE_DocumentUpload/etc/config.xml：版本号 +1

---

## 5. 集成测试

### 5.1 测试文件

app/code/community/XFE/Injection/Test/Integration/DocumentUploadTest.php

### 5.2 测试用例

| # | 场景 | 预期 |
|---|------|------|
| 1 | DocumentUpload injection.xml 解析 | 1 hook + 1 calling + 0 services |
| 2 | Carrier service_carrier_list_accounts 存在 | class 引用 CredentialViaInjection |
| 3 | 合并 3 个 XML（Injection 自描述 + Carrier + DocumentUpload） | integrity 通过, 3 hooks 2 services 2 callings |
| 4 | ServiceLocator mock 替换 listAccountsByCarrier | Colissimo adapter 拿到 mock 返回值 |
| 5 | 参数映射 context.carrierCode + context.contextValues | adapter 收到正确参数 |
| 6 | 反射验证 listAccountsByCarrier 签名第二参数是 array | 零 XFE_Carrier 类型耦合 |

---

## 6. 验证清单

### 6.1 静态检查

- 所有 PHP 文件 php -l 通过
- grep "XFE_Carrier_\$" Colissimo.php 仅命中 PHPDoc

### 6.2 单元测试无回归

- php Test/Unit/InjectionTest.php：71 全过
- php Test/Integration/CarrierLogisticTest.php：36 全过

### 6.3 新集成测试

- php Test/Integration/DocumentUploadTest.php：6+ 全过

### 6.4 耦合度 grep（关键）

```bash
grep -rn 'Mage::getStoreConfig.*xfe_documentupload.*accounts_json' app/code/community/XFE/DocumentUpload/Service/ app/code/community/XFE/DocumentUpload/Helper/
# 预期: 旧路径仍存在(本轮不删),但 getAccountsViaInjection 不应读取

grep -nE 'XFE_Carrier_[A-Za-z_]+\$' app/code/community/XFE/DocumentUpload/Model/Carrier/Colissimo.php
# 预期: 无匹配(零 PHP 类型耦合)
```

### 6.5 行为不变性

- Colissimo::getAccounts() 完全未改动
- accounts_json system config 仍可读
- DocumentUpload 上传功能未改动

---

## 7. 风险与回滚

### 7.1 风险

| 风险 | 缓解 |
|------|------|
| 新 service 与旧 service 同名/冲突 | serviceId 全局唯一(snake_case + 模块前缀) |
| DocumentUpload 模块未启用 XFE_Injection 依赖 | module.xml 加 <XFE_Injection/> |
| listAccountsByCarrier 在无 Mage 环境崩溃 | 单测环境提前 return array() |

### 7.2 回滚方案

1. 删除 DocumentUpload etc/injection.xml
2. 删除 Colissimo::getAccountsViaInjection 方法
3. 删除 Carrier etc/injection.xml 中的 service_carrier_list_accounts
4. 删除 Carrier 适配器中的 listAccountsByCarrier 方法
5. 撤销 module.xml 的 XFE_Injection 依赖
6. 版本号回滚

回滚成本：6 步约 10 分钟。

---

## 8. 后续工作

1. 数据迁移脚本：admin_system_config_value → xfe_carrier_carrier_account
2. 废弃 accounts_json system config 字段
3. 改 Colissimo::getAccounts() 主流路径：内部调用 getAccountsViaInjection
4. 其他 DocumentUpload carrier adapter 同样接入
5. 最终删除 DocumentUpload/Helper/Data.php 中的 _configPathPrefix system config 路径

---

## 9. 关联决策

- ADR 0017：本文档对应的设计决策
- ADR 0015：Carrier↔Logistic XML 注入集成（模式一致）
- ADR 0016：MatchContext 完全解耦（同样适用于本轮）
- carrier-facade-deprecation.md §6.2：DocumentUpload 模块路线图

---

## 10. 修订记录

| 日期 | 变更 | 作者 |
|------|------|------|
| 2026-09-17 | 初版：定义 DocumentUpload XML 注入最小化方案 | hanson.gao + AI 助手 |
