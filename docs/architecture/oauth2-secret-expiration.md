# XFE_OAuth2 client_secret 有效期与重生成

- 状态：**Accepted**
- 日期：2026-09-11
- 决策者：XFE 开发组
- 关联 ADR：[0007-oauth2-secret-expiration.md](./decisions/0007-oauth2-secret-expiration.md)
- 关联文档：[oauth2-architecture.md](./oauth2-architecture.md)、[oauth2-customer-navigation.md](./oauth2-customer-navigation.md)

## 背景

`XFE_OAuth2` 模块的 client_secret 一直"永不过期"，带来三类风险：

1. **第三方密钥泄漏**后，管理员只能删 client + 重建，会**立刻**打断依赖
   现有 client 的所有 token 签发流程。
2. **运维无感**：列表 / 详情页没有"有效期到期时间"字段，管理员只能
   巡检 / 靠用户投诉发现快过期 client。
3. **客户无自助通道**：前台 storefront 用户在 `oauth2/client/index`
   列表上没有"重生成 secret"按钮，只能找客服。

本期引入：

- 默认 **90 天** client_secret 有效期（可通过 `xfeoauth2/general/client_secret_ttl_days` 调整）。
- 后台 + 前台对称提供 **重生成（regenerate / rotate）** 入口。
- 列表展示 **到期时间** + **剩余天数**。

## 模块分层（按 AGENTS.md §2 4 层单向依赖）

```
L4  controllers/Adminhtml/Xfeoauth2/ClientController::regenerateAction  (新增)
     ↓ 调
     controllers/ClientController::regenerateAction                       (新增，前台)
     ↓ 调
L3  XFE_OAuth2_Helper_Data::rotateClientSecret()                         (新增)
     ↓ 调
L2  XFE_OAuth2_Model_Client (Mage_Core_Model_Abstract)
     ↓ 走 Varien_ORM
L1  xfe_oauth2_client.client_secret_expires_at / client_secret_last_rotated_at  (新增列)
```

## 数据模型变更（1.0.2 -> 1.0.3）

### `xfe_oauth2_client` 新增 2 列

| 列名 | 类型 | NULL | 含义 |
|------|------|------|------|
| `client_secret_expires_at` | DATETIME | YES | 当前 client_secret 的到期时间（UTC） |
| `client_secret_last_rotated_at` | DATETIME | YES | 上一次 rotate 的时间（审计） |

### 升级脚本：`sql/xfeoauth2_setup/upgrade-1.0.2.0-1.0.3.0.php`

- 通过 `information_schema.COLUMNS` 检查两列是否存在，**幂等**。
- 升级时一次性回填：
  - `client_secret_expires_at = DATE_ADD(IFNULL(created_at, NOW()), INTERVAL 90 DAY)`
  - `client_secret_last_rotated_at = IFNULL(created_at, NOW())`

> 回填策略是**非破坏性**的：只填 NULL 的行，已存在的 `client_secret` /
> `client_secret_encrypted` 一字节都不动。

### config.xml 版本号

```xml
<modules>
    <XFE_OAuth2>
        <version>1.0.3.0</version>
    </XFE_OAuth2>
</modules>
```

## 领域逻辑

### `XFE_OAuth2_Helper_Data` 新增方法

| 方法 | 签名 | 作用 |
|------|------|------|
| `getDefaultSecretTtlDays()` | `int` | 从 `xfeoauth2/general/client_secret_ttl_days` 读配置；缺省或越界时回退到 90，范围 [1, 3650] |
| `rotateClientSecret(XFE_OAuth2_Model_Client $client)` | `string` (新 raw secret) | 生成新 secret、写 bcrypt、写 AES 副本、刷 expires_at + last_rotated_at + updated_at |
| `isClientSecretExpired(XFE_OAuth2_Model_Client $client)` | `bool` | 仅 UI 用：expires_at < now 返回 true，NULL 返回 false（"未知"视为未过期） |
| `getSecretTtlDays(XFE_OAuth2_Model_Client $client)` | `int` | 给出当前 client 剩余/总 TTL 展示用 |

### `XFE_OAuth2_Helper_Data::rotateClientSecret` 实现要点

```php
public function rotateClientSecret(XFE_OAuth2_Model_Client $client)
{
    $secret = $this->generateToken(32);
    $client->setClientSecret($this->hashSecret($secret));
    $client->setClientSecretEncrypted($this->encryptData($secret));
    $ttlDays = $this->getDefaultSecretTtlDays();
    $client->setClientSecretExpiresAt(date('Y-m-d H:i:s', time() + $ttlDays * 86400));
    $client->setClientSecretLastRotatedAt(date('Y-m-d H:i:s'));
    $client->setUpdatedAt(date('Y-m-d H:i:s'));
    $client->save();
    return $secret;
}
```

## 控制器层

### 后台 `XFE_OAuth2_Adminhtml_Xfeoauth2_ClientController`

新增 `regenerateAction`：

| 步骤 | 行为 |
|------|------|
| 1. ACL 检查 | `_isAllowed()` 沿用 `xfe/xfeoauth2_clients` |
| 2. 加载 client | 找不到则 error + 重定向到 `*/*/` |
| 3. 调用 helper | `$helper->rotateClientSecret($model)` |
| 4. 成功通知 | `Mage::getSingleton('adminhtml/session')->addNotice('Client Secret (shown once): ' . $secret)` |
| 5. 失败回退 | error + 重定向到 `*/*/edit` |

### 前台 `XFE_OAuth2_ClientController`

新增 `regenerateAction`（storefront）：

| 步骤 | 行为 |
|------|------|
| 1. preDispatch | 已登录 customer（继承自父类） |
| 2. 加载 client | 找不到或非本人 owner 则 addError + 重定向到 `*/*/index` |
| 3. 调用 helper | `$helper->rotateClientSecret($model)` |
| 4. 成功通知 | `Mage::getSingleton('customer/session')->setData('xfeoauth2_new_secret_' . $id, $secret)` 然后跳到 `*/*/index?show_secret=...`（沿用现有的"一次性展示"机制） |
| 5. 失败回退 | error + 重定向到 `*/*/index` |

> **强制 owner check**：`$model->getUserId() === $session->getCustomerId()`，防止横向越权。

## 视图层

### 后台 Grid

新增列 `client_secret_expires_at`：

- `header`: `Secret Expires`
- `index`: `client_secret_expires_at`
- `type`: `datetime`
- 宽度：`150px`
- 渲染：剩余天数按颜色上色（>30d 绿、<=30d 黄、过期红、NULL 灰）
  - 颜色逻辑写在 Grid 默认 `type=datetime` 的展示里，再附加一个 inline 文字说明。

### 前台 list.phtml

- 新增一列 `<th>Expires</th>`：显示 `client_secret_expires_at`（NULL 显示"-"）。
- 新增一列 `<th>Actions</th>` 内追加"Regenerate"按钮（POST form，confirm 弹窗）。
- 新增 JS 函数 `regenerateClientSecret(form)`：序列化 form 走 `Ajax.Request` 的 `post`，
  成功后弹 prompt 展示新 secret（与 `revealClientSecret` 行为对齐）。

## system config

在 `etc/system.xml` 的 `xfeoauth2/general` group 新增字段：

```xml
<client_secret_ttl_days translate="label">
    <label>Client Secret TTL (days)</label>
    <frontend_type>text</frontend_type>
    <sort_order>20</sort_order>
    <show_in_default>1</show_in_default>
    <show_in_website>1</show_in_website>
    <show_in_store>0</show_in_store>
    <comment>Default expiration period for newly generated client secrets. Min 1, max 3650.</comment>
</client_secret_ttl_days>
```

> 默认值 90 由 Helper 在配置缺失或越界时兜底，不在 system.xml 写 `<default>` 以避免
> "改了 config 但忘了 Helper"的隐性 bug。

## 校验逻辑影响

| 调用方 | 变化 |
|--------|------|
| `Storage\ClientCredentials::checkClientCredentials` | **加入过期校验**（ADR 0007 修订 1）：先判 `expires_at` 是否过期，NULL 视为向后兼容（不拒绝），非 NULL 且过期则返回 false 并写 `xfeoauth2.log` |
| `Storage\ClientCredentials::getClientDetails` | **不**改：继续返回老 5 个键，避免破坏 bshaffer 内部依赖 |
| 新增 `isClientSecretExpired` | 同时服务于 UI 红字提示 **和** 签发拒绝 |

**过期拒绝的可观测性**：

```log
[date] OAuth2 token request rejected: client_secret expired for <client_id>
       (expired at <expires_at>)
```

运维可直接 `grep "rejected: client_secret expired" var/log/xfeoauth2.log`
列出所有被拒绝的 client_id，然后到 admin 后台 / 前台 list 触发 regenerate。

**为什么 NULL 不拒**：升级脚本 `upgrade-1.0.2.0-1.0.3.0.php` 已对
所有 NULL 行做一次性回填 `created_at + 90d`，所以理论上一旦数据库经过
1.0.3 升级流程就不会再有 NULL 行。仅在手工 SQL 绕过 controller 插入
client 这种边缘情况下才会保留 NULL 行为（与升级前一致，不锁死）。

详见 ADR 0007 备选方案 A（修订后）。

## 受影响文件清单

| 路径 | 改动类型 | 概要 |
|------|---------|------|
| `sql/xfeoauth2_setup/upgrade-1.0.2.0-1.0.3.0.php` | 新增 | 2 列 + 一次性回填 |
| `etc/config.xml` | 改 | 模块版本 `1.0.2.0 -> 1.0.3.0` |
| `etc/system.xml` | 改 | 新增 `client_secret_ttl_days` 字段 |
| `Helper/Data.php` | 改 | 新增 4 个方法 |
| `controllers/Adminhtml/Xfeoauth2/ClientController.php` | 改 | 新增 `regenerateAction` |
| `controllers/ClientController.php` | 改 | 新增 `regenerateAction` |
| `Block/Adminhtml/Client/Grid.php` | 改 | 新增 `client_secret_expires_at` 列 |
| `app/design/frontend/base/default/template/xfeoauth2/customer/oauth2client/list.phtml` | 改 | 新增 Expires 列 + Regenerate 按钮 + JS |

## 兼容性 / 验证步骤

1. `php -l` 所有改动文件全部通过
2. 升级脚本在已有 1.0.2 数据库上**幂等**：
   - 第一次执行：ADD 2 列，回填 NULL 行
   - 第二次执行：`information_schema` 已查到列，跳过
3. 新建 client 后，admin grid 与前台 list 均显示 expires_at = 创建时间 + 90 天
4. 后台 + 前台 regenerate 后：
   - `client_secret` 已变（bcrypt hash 不同）
   - `client_secret_encrypted` 已变
   - `client_secret_expires_at` 重新指向 now + 90 天
   - `client_secret_last_rotated_at` = now
5. 现有 token 在 regenerate 后**立刻失效**（bshaffer 每次请求都验 secret），符合预期

## 修订记录

| 日期 | 变更 |
|------|------|
| 2026-09-11 | 初版：定义 90 天默认 TTL + 后台/前台对称 regenerate + 列表展示 + 升级脚本 + system config |
| 2026-09-11 | 修订 1：Storage 加硬过期校验；NULL 兜底；过期拒绝写 `xfeoauth2.log`；同步 ADR 0007 备选方案 A 决策 |

