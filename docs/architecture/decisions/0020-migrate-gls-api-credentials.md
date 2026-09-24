# 0020. GLS API 凭据迁移：core_config_data → xfe_carrier_account

- 状态：Accepted
- 验证日期：2026-09-17
- 验证摘要：dry-run + run 各执行一次；xfe_carrier_account 表已含迁移行；MigrateGlsCredentialsTest 40 断言全过；core_config_data 4 行残留待 ADR 0021 清理脚本下线
- 日期：2026-09-17
- 决策者：AI 助手（经用户确认）

## 关联文档

- [0019-decouple-podservice-main-path.md](./0019-decouple-podservice-main-path.md) — 本 ADR 的前置（PodService 已接入 XML 注入）
- [../migrate-gls-api-credentials.md](../migrate-gls-api-credentials.md) — 本 ADR 对应的开发指南

## 背景

ADR 0019 让 PodService 主流路径走 XML 注入 + fallback。但 Carrier 后台没人配过 GLS 账号，所以注入路径一直返回 null，所有线上 POD 调用**实际仍走 system config 旧路径**。

**当前凭据分布**：

| 字段 | Logistic system config (`core_config_data`) | Carrier account 表 (`xfe_carrier_account`) |
|------|------------------------------------------|---------------------------------------|
| base_url / endpoint | `xfe_logistic/gls_api/base_url` | `endpoint_url` |
| username | `xfe_logistic/gls_api/username` | `username` |
| **password** | 加密（`adminhtml/system_config_backend_encrypted`） | 明文 |
| parcelpod_resource | `xfe_logistic/gls_api/parcelpod_resource` | 无对应字段 |

### 关键差异

1. **password 加密 vs 明文**：Logistic 用 Magento `adminhtml/system_config_backend_encrypted` 后端模型加密存储；Carrier admin Form 仅 `text` 类型，明文存储。
2. **parcelpod_resource 没有对应列**：需要写入 Carrier 表的 `custom_fields_json` (1.0.14 引入)。
3. **carrier_id 来源**：Carrier 表 `code='gls'` 行可能不存在，脚本要自动 seed。

### 业务动机

凭据单一来源：让管理员只在 **Carrier 后台** 配置 GLS API 账号，避免双处配置带来的漂移风险。

## 决策

**编写一次性的 data-upgrade 迁移脚本**，把 Logistic system config 的 3 条 GLS 凭据迁移到 Carrier account 表；migration 完成后**不立即删除** Logistic system config 字段，留给 PodService fallback 路径作为安全网。

### 1. 迁移脚本结构

文件：`app/code/community/XFE/Logistic/sql/xfe_logistic_setup/data-upgrade/migrate-gls-api-credentials.php`

**为什么用 `data-upgrade/` 命名**：
- `install-*/upgrade-*` 会被 Mage 自动在每次请求时扫描运行 — 不可控
- `data-upgrade/` 命名（参考 Carrier `schema-1.0.10.php`）不会被 Mage 自动运行 — 需要管理员手动触发
- 数据迁移是**一次性、不可逆**操作，必须有明确执行入口和确认步骤

### 2. 迁移脚本逻辑

```
1. 读取 core_config_data 中 xfe_logistic/gls_api/* 4 条记录
2. 检查 xfe_carrier 表是否存在 code='gls' 行；不存在则 INSERT（name='GLS', status=1）
3. 取 carrier_id
4. 用 Mage::getModel('core/encryption')->decrypt() 解密 password
5. 检查 xfe_carrier_account 是否已有 username+endpoint_url 匹配的行
   - 有 → 跳过（幂等性）
   - 无 → INSERT 新行（username, password 明文, endpoint_url, custom_fields_json={parcelpod_resource}, status=1）
6. 输出迁移报告（log 到 var/log + 输出到 stdout）
```

### 3. CLI 触发

```bash
php shell/migrate-gls-api-credentials.php
# 或
php shell/migrate-gls-api-credentials.php --dry-run   # 仅打印计划，不写
```

### 4. 不做的事

- ❌ 不自动删除 Logistic system config 字段（fallback 安全网保留）
- ❌ 不动 Carrier 主表结构
- ❌ 不动 Carrier 既有账号行（仅 INSERT 新行）
- ❌ 不引入数据库事务包裹（脚本是单次原子操作，失败可重跑）

## 备选方案

### A. ✅ data-upgrade 脚本 + CLI 触发 + 保留 fallback（本决策）

- 优点：管理员可见、dry-run 预览、幂等、不可逆操作有显式入口
- 缺点：管理员需主动运行 CLI（非自动）
- 采纳

### B. 标准 upgrade-1.2.0-1.3.0.php 脚本（让 Mage 自动跑）

- 优点：装模块即迁移
- 缺点：不可逆操作与模块升级强绑定，违反"数据迁移 = 一次性手动操作"原则
- 否决

### C. 直接 SQL UPDATE（不用 PHP 脚本）

- 优点：最短
- 缺点：无法处理 password 解密；无幂等保护；无报告输出
- 否决

### D. 在 PodService 内首次调用时自动迁移

- 优点：完全自动
- 缺点：业务代码承担迁移职责；首次调用延迟高；无 dry-run；审计困难
- 否决：违反分层（Service 不应写 DB 迁移）

## 后果

### 正面

- 凭据单一来源达成：admin 只在 Carrier 后台配 GLS 账号
- PodService 注入路径开始命中（注入返 array，fallback 不触发）
- 数据迁移有明确执行入口 + dry-run 模式
- 迁移报告 log 到 var/log/migrate-gls-api-credentials.log 可审计
- 幂等：多次执行无副作用

### 负面

- 管理员需手动触发 CLI 脚本（不可逆操作的合理成本）
- password 加密语义变化：system config 存密文 → Carrier 表存明文（与 Carrier 现有行为一致）
- parcelpod_resource 从系统配置搬到 custom_fields_json（语义保留，但路径变化）

### 后续工作

1. 验证迁移完成后线上 POD 请求仍正常
2. 观察 1-2 周确认无 fallback 触发
3. 删除 `xfe_logistic/gls_api/*` system config 字段 + `system.xml` 配置 + Helper 旧方法
4. PodService 删除 fallback 分支（最终态）

## 实施检查清单

- [ ] ADR 0020（本文档）
- [ ] 开发指南 `docs/architecture/migrate-gls-api-credentials.md`
- [ ] `app/code/community/XFE/Logistic/sql/xfe_logistic_setup/data-upgrade/migrate-gls-api-credentials.php`
- [ ] `shell/migrate-gls-api-credentials.php`（CLI 入口）
- [ ] `Logistic/etc/config.xml` 版本号 +1（不是必须，但保持一致）
- [ ] 单元测试：`MigrateGlsCredentialsTest.php` 验证解密 + 幂等 + dry-run
- [ ] Carrier 既有账号兼容性测试
- [ ] 在测试环境 dry-run + 真实 run 各跑一次
- [ ] 累计断言 199+ 全过
- [ ] CI 接入（自动跑 dry-run 模式）

## 注意事项

- **Magento 加密密钥依赖**：`Mage::getModel('core/encryption')->decrypt()` 需要 `app/etc/local.xml` 中的 `<crypt><key>`。生产环境必须用同一密钥。
- **custom_fields_json 编码**：用 `XFE_Carrier_Domain_CustomFieldCodec`（已存在）保证序列化一致性。
- **Carrier 主表 seed**：`code='gls'` 行不存在时，脚本创建 `name='GLS', status=1, sort_order=10`，但不动 `shipping_company_id`（保持 NULL）。
- **不导入 ftp_account_id**：FTP 是另一回事，不在本次迁移范围。
- **回滚方案**：Carrier 表 INSERT 操作本身可逆（DELETE WHERE username=... AND endpoint_url=...），但 Logistic system config 不会回滚（防止误操作）。建议先备份 core_config_data 相关 4 行。
