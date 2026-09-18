# 0007. OAuth2 client_secret 引入有效期与重生成

- 状态：**Accepted**
- 日期：2026-09-11
- 决策者：hanson.gao + AI 助手
- 关联文档：[oauth2-secret-expiration.md](../oauth2-secret-expiration.md)（本 ADR 引发的架构文档）

## 背景

`XFE_OAuth2` 模块当前（1.0.2）的 `xfe_oauth2_client.client_secret` 列只存储
**不可逆 bcrypt** + **可逆 AES 副本**（`client_secret_encrypted`），无任何"过期"
概念，表现为：

1. 一个 client 的 `client_secret` 一旦写入就**永远不过期**，只能通过删 client
   + 重建 client 才能"换" secret。这与业界 OAuth2 实践（GitHub/Google/微信
   开放平台都默认 secret 90 天 / 6 个月 / 1 年有效）不符。
2. 第三方应用主密钥泄漏后，没有"轮换"路径，只能等管理员手动删重建，会
   打断依赖现有 client 的所有 token 签发流程。
3. 列表 / 详情页没有"有效期到期时间"字段，管理员无法提前发现即将到期
   的 client，需要人工巡检。
4. 客户 / 管理员在 UI 上看不到当前 secret 还有多久过期，无法主动轮换。

本 ADR 决定引入"client_secret 有效期"概念及"重生成（rotate / regenerate）"
动作，并把这个能力**同时暴露给后台 admin 与前台 storefront**。

## 决策

### 1. 数据库 schema 扩展（不可逆新增列）

在 `xfe_oauth2_client` 上新增 2 个 DATETIME 列（nullable）：

| 列名 | 类型 | 是否 NULL | 含义 |
|------|------|----------|------|
| `client_secret_expires_at` | DATETIME | YES（已存在 client 暂保留 NULL） | 当前 client_secret 到期时间 |
| `client_secret_last_rotated_at` | DATETIME | YES | 上一次重生成 secret 的时间（审计用） |

**关键约束**：

- 两个列都允许 NULL，**不**改任何老列。`ALTER TABLE ... ADD COLUMN` 在 Magento 1.9
  通过 `information_schema` 幂等判断（沿用 `upgrade-1.0.1.0-1.0.2.0.php` 模板）。
- 默认值是 `NULL` 而**不是**自动写死时间，避免升级脚本产生隐式回填逻辑；NULL
  表示"该行尚未被新逻辑管理"（老 client）。`Storage\ClientCredentials::checkClientCredentials`
  仍然接受 NULL 行（与原行为一致），UI 上把这些行标为"未知"。
- 升级脚本在升级完后，对**所有 NULL 行**执行一次性回填：
  `client_secret_expires_at = DATE_ADD(created_at, INTERVAL 90 DAY)`，
  `client_secret_last_rotated_at = created_at`。这是非破坏性回填（不修改
  原 secret）。

### 2. 默认 TTL 与可配置化

- **默认 TTL = 90 天**。
- 通过 system config `xfeoauth2/general/client_secret_ttl_days` 暴露给 admin
  在 `System -> Configuration -> XFE -> OAuth2 Login -> General Settings` 下配置。
- 配置值范围：1 ~ 3650（10 年），保存时校验。
- Helper 方法 `getDefaultSecretTtlDays()` 默认返回 90；`now + ttl` 的计算统一
  在 `Helper\Data::rotateClientSecret()` 里完成。

### 3. 重生成（rotate / regenerate）

新增 `XFE_OAuth2_Helper_Data::rotateClientSecret($client)`，做以下事：

1. 调用 `generateToken(32)` 生成新 raw secret（与 save 流程一致）。
2. `password_hash()` 写入 `client_secret`（bcrypt）。
3. `core/encrypt` 写入 `client_secret_encrypted`（可逆 AES 副本，便于 UI "查看 secret"）。
4. 把 `client_secret_expires_at` 写为 `now + ttl`，把 `client_secret_last_rotated_at` 写为 `now`。
5. 同步刷新 `updated_at`。
6. **不**删除老 token / 老 refresh_token：bshaffer OAuth2 Server 校验的是
   `client_secret`（每次 token 请求都验），老 token 在过期后自然失效。
   这样不会"立刻"打断现有依赖，但管理员有责任通知调用方尽快切换。

### 4. UI 行为

#### 后台 admin（`XFE_OAuth2_Adminhtml_Xfeoauth2_ClientController`）

- 新增 `regenerateAction`：POST，参数 `id`，调用 `Helper::rotateClientSecret()`，
  成功后弹 notice "New Client Secret (shown once): xxx" 跟新建流程一致。
- 失败回退到 `*/*/edit`，并把异常写入 `xfeoauth2.log`。
- ACL 检查沿用 `_isAllowed()`（与 save 一致）。
- Grid 列 `client_secret_expires_at`：渲染器按剩余天数给颜色（>30d 绿、<=30d
  黄、过期红、NULL 显示"未知"）。

#### 前台 storefront（`XFE_OAuth2_ClientController`）

- 新增 `regenerateAction`：GET（带 CSRF 通过 form submit），参数 `id`，**强制
  owner check**（`$client->getUserId() == $session->getCustomerId()`），防止
  横向越权调别人 client 的重生成。
- 完成后跳回 `*/*/index` 并把新 secret 写入一次性 notice（与新建流程一致）。
- list.phtml 增加"Regenerate"按钮（POST form），按钮带 confirm 提示"已有 token
  会失效，是否继续?"。

### 5. 校验逻辑影响

`Storage\ClientCredentials::checkClientCredentials` **不**改逻辑：client_secret
没变（刚 rotate 后是新的，没 rotate 的还是老的），bshaffer 库做 `password_verify`
仍然过。

`isClientSecretExpired($client)` 是一个**新**方法，只服务于 UI 提示，**不**
塞进 `checkClientCredentials`：到期就拒绝签发 token 是"硬策略"，本期不做
（避免阻塞已存在的合法 client，需要管理员主动 rotate）。在文档里**明确**
告知：到期后 secret 仍可用于校验，仅 UI 标红，由管理员决定是否 rotate。

### 6. 文档与代码同步

- 本 ADR + `docs/architecture/oauth2-secret-expiration.md` 必须在改代码前落地
  （遵循 AGENTS.md §4.2 文档先行）。
- `task_plan.md` / `progress.md` 同步登记。
- PR 描述必须 link 到本 ADR + 架构文档。

## 备选方案

### 方案 A：在 `checkClientCredentials` 里直接拒绝过期 secret（**已采用**，2026-09-11 修订）

- **初版结论**：本期**不**采用，担心升级后立即锁死现有合法 client。
- **修订结论**：采用。原因是：
  1. 升级脚本 `upgrade-1.0.2.0-1.0.3.0.php` 对所有 NULL 行做一次性回填
     `created_at + 90d`，所以**已经存在的合法 client 在升级后会拿到一
     个 expires_at = 创建时间 + 90 天**，不会立刻过期。
  2. 对于"升级时创建时间已经超过 90 天"的老 client，会立刻过期。这正是
     用户要求的语义——"获取认证时需要验证是否过期"。客户/管理员可主动
     rotate 一行拿到新 secret（前台 / 后台对称提供）。
  3. NULL 行兜底：极少数手工 INSERT 跳过 controller 的 client（理论上不
     应存在），其 expires_at 为 NULL 时不做过期检查，与升级前的行为一致。
- **实现位置**：`Model/Storage/ClientCredentials::checkClientCredentials`，
  在 `verifySecret()` 之前调用 `Helper::isClientSecretExpired()`。
- **可观测性**：过期拒绝会写入 `xfeoauth2.log`，消息包含 client_id 与
  expires_at，便于运维 grep。

### 方案 B：完全不暴露 system config，TTL 硬编码 90 天

- **缺点**：不同客户业务节奏差异大（内部系统 90 天，对外 6 个月），
  没有 UI 配置入口时只能改代码。
- **结论**：保留 system config 入口但默认值 = 90 天。这样默认行为不变，
  又给运维提供逃生舱。

### 方案 C：rotate 时把老 client_secret 保留 N 天"宽限期"

- **缺点**：老 secret 仍能签发 token，宽限期内调用方切换不及时会产生
  "双倍 token 流量"，对审计不友好。
- **结论**：不引入宽限期，老 secret 在 rotate 那一刻立即作废（依赖
  bshaffer 库每次请求都重新验 secret）。

### 方案 D：把 expires_at 加到 access_token 而不是 client

- **缺点**：access_token 本来就有 expires 字段（Unix timestamp），与本期
  目标"client_secret 轮换"是两件事。
- **结论**：本期**不**改 access_token 表。

## 后果

### 正面

- client_secret 永久不过期的风险点被消灭，泄漏后可一键 rotate。
- 默认 90 天 + 可配置 TTL，覆盖绝大多数业务节奏。
- 后台 + 前台对称提供 rotate / regenerate，admin 和 customer 都能自助。
- 列表展示 expires_at，运维可提前发现"快过期" client 并主动 rotate。
- 升级脚本非破坏性：只 ADD COLUMN，老 client 的 secret / token 一行不动。

### 负面

- 升级脚本要做一次性回填（90 天从 created_at 算），如果 created_at 为 NULL
  （理论极端情况），回填退化为 `now() + 90d`。本项目不存在 NULL created_at，
  所以仅是兜底。
- 前台增加 regenerate 按钮后，恶意登录态用户可能用它来"穷举"破坏自己
  依赖的 token（实际无安全风险，只是误操作）。需要 confirm 弹窗与 notice
  双重提示。
- UI 颜色阈值（30 天黄 / 过期红）写死在渲染器里，未来调整需要改代码；
  不暴露 system config（业务量不大，不值得开 config 项）。

## 兼容性

- `client_secret` 列行为不变：`Storage\ClientCredentials::checkClientCredentials`
  仍然只看 `client_secret`（bcrypt），不读 `client_secret_expires_at`。
- 老 client（NULL expires_at）行为不变：UI 标"未知"，签发流程不变。
- 新建 client 行为变了：`saveAction` 会自动写入 expires_at = now + ttl。
  对 admin / customer 都一致。
- 数据库兼容：仅 ADD COLUMN，Magento 1.9 + MySQL 5.5+ 均支持。

## 修订记录

| 日期 | 变更 |
|------|------|
| 2026-09-11 | 初版：定义 90 天默认 TTL + 可配置 + rotate + UI 展示 + 校验逻辑 |
| 2026-09-11 | 修订 1：备选方案 A 由"不采用"改为"采用"（硬过期）；`Storage\ClientCredentials::checkClientCredentials` 加入过期校验；NULL 行保留向后兼容行为。 |

