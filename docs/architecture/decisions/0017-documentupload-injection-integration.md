# 0017. XFE_DocumentUpload 通过 XML 注入接入 XFE_Carrier

- 状态：Proposed
- 日期：2026-09-17
- 决策者：hanson.gao + AI 助手
- 关联文档：
  - [carrier-facade-deprecation.md](../carrier-facade-deprecation.md)
  - [decisions/0015-injection-carrier-logistic-integration.md](./0015-injection-carrier-logistic-integration.md)
  - [decisions/0016-decouple-match-context.md](./0016-decouple-match-context.md)
  - [injection-carrier-logistic-integration.md](../injection-carrier-logistic-integration.md)

## 背景

XFE_DocumentUpload 当前是"独立小作坊模式"（carrier-facade-deprecation.md §6.2）：

- 账号存在 `xfe_documentupload/{carrier_code}/accounts_json` system config
- 每个 carrier adapter（Colissimo）通过 `Mage::getStoreConfig('xfe_documentupload/colissimo/accounts_json')` 自读账号
- 完全绕开 XFE_Carrier 的账号主档
- 后果：管理员要为同一承运商在 2 个模块分别配置账号；规则引擎失效；凭据散落多处

ADR 0015 §6.2 已规划迁移路线，但本轮聚焦"接入 XML 注入"——而非彻底删除 `accounts_json`。

## 决策

### 1. 最小化集成示范（与 ADR 0015 模式一致）

- DocumentUpload 端：新增 `etc/injection.xml` + Carrier adapter 新增 1 个公开方法
- 不删除旧 `accounts_json` 路径
- 旧路径 `getAccounts()` 与新路径 `getAccountsViaInjection()` 并存
- 行为可对照，渐进迁移

### 2. 注入点设计

`hook_documentupload_resolve_accounts` 触发后，由 `service_carrier_resolve_account` 解析账号列表（不仅是 ID）。

但 ADR 0015 的 `resolveAccountId` 只返回单数 accountId。本轮扩展为：

```php
public function listAccountsByCarrier($carrierCode, array $contextValues = array())
{
    // 返回 array<int> account_id 列表
    // 走 XFE_Carrier_Model_Carrier_Account 资源模型直接读
}
```

### 3. 注入方向不变

- DocumentUpload 是消费方，调用 Carrier 提供的 service
- Carrier 已有 `service_carrier_resolve_account`（单数解析）
- 本轮新增 `service_carrier_list_accounts`（复数列举），与 ADR 0015 §3.1 适配器并列

### 4. 行为不变性

- DocumentUpload/Model/Carrier/Colissimo::getAccounts() 保持不变（仍读 system config）
- 新增方法 `getAccountsViaInjection($carrierCode, array $contextValues)` 作为旁路
- 数据迁移（admin_system_config_value → xfe_carrier_carrier_account）等下一轮

## 备选方案

### 备选 A：彻底删除 accounts_json 路径

放弃：本轮与 ADR 0015 一致，做最小化集成示范。完全删除需：
1. 数据迁移脚本（admin_system_config_value → xfe_carrier_carrier_account）
2. 废弃 system config 字段
3. 改 Colissimo adapter 主路径
风险高、范围大，留作下一轮。

### 备选 B：复用 service_carrier_resolve_account 解析单 ID 后再列账号

放弃：`resolveAccountId` 是单数解析，DocumentUpload 需要"该 carrier 的所有可用账号"列表，语义不同。
新增 `listAccountsByCarrier` 是必要的语义扩展。

## 后果

### 正面
- DocumentUpload 与 Carrier 建立 XML 注入连接，证明跨模块调用链可走通
- 旧 accounts_json 路径保留，无回归风险
- 未来数据迁移时，只需把 `getAccounts()` 内部实现改为调用 `getAccountsViaInjection()`，无需改调用方

### 负面
- 两个 service（resolveAccountId / listAccountsByCarrier）共存，Carrier 适配器表面变大
- DocumentUpload 的 `_configPathPrefix` system config 路径仍存在（废弃但未删）

### 缓解措施
- 在 `CredentialViaInjection` 上 PHPDoc 提醒"旧 Facade 入口已废弃"
- 数据迁移 ADR 单独规划（下次迭代）

## 实施检查清单

- [ ] Carrier 适配器新增 `listAccountsByCarrier($carrierCode, array $contextValues)`
- [ ] Carrier `etc/injection.xml` 声明 `service_carrier_list_accounts`
- [ ] DocumentUpload `etc/injection.xml` 声明 hook + calling
- [ ] DocumentUpload/Model/Carrier/Colissimo 新增 `getAccountsViaInjection()` 旁路方法
- [ ] DocumentUpload `app/etc/modules/XFE_DocumentUpload.xml` 加 <XFE_Injection/> 依赖
- [ ] 集成测试: DocumentUploadTest.php
- [ ] 版本号: Carrier 1.0.17 → 1.0.18, DocumentUpload 版本号 +1
- [ ] 所有 PHP 文件 php -l 通过
- [ ] 集成测试通过
- [ ] 验证清单：grep DocumentUpload 中 XFE_Carrier 类型签名 = 0 处

## 不在本轮范围

- ❌ 删除 accounts_json system config 字段
- ❌ 数据迁移脚本
- ❌ 改动 Colissimo::getAccounts() 主流路径
- ❌ 其他 DocumentUpload carrier adapter 的迁移
