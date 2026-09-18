# GLS API 凭据迁移开发指南

> 主题：把 Logistic 模块 system config 中的 GLS API 凭据（`xfe_logistic/gls_api/*`）
> 迁移到 `xfe_carrier_account` 表，实现凭据单一来源。

> 状态：Draft（2026-09-17）

> 关联文档：
> - [decisions/0020-migrate-gls-api-credentials.md](./decisions/0020-migrate-gls-api-credentials.md) — 本指南对应 ADR
> - [decisions/0019-decouple-podservice-main-path.md](./decisions/0019-decouple-podservice-main-path.md) — PodService 主流路径接入 XML 注入

---

## 1. 目标与范围

### 1.1 业务目标

让管理员只在 **Carrier 后台** 配置 GLS API 账号，不再需要在 Logistic 后台重复配置。

### 1.2 本轮范围

| 项目 | 是否在本轮 |
|------|----------|
| 迁移脚本（data-upgrade） | ✅ |
| CLI 入口（`shell/migrate-gls-api-credentials.php`） | ✅ |
| 单元测试：解密 + 幂等 + dry-run | ✅ |
| 删除 Logistic system config 字段 | ❌ 验证期后下一轮 |
| 删除 PodService fallback 分支 | ❌ 验证期后下一轮 |
| 删除 Helper::getParcelPodUrl/getBasicAuthHeaderValue | ❌ 验证期后下一轮 |

---

## 2. 迁移目标映射

| Logistic system config path | Carrier `xfe_carrier_account` 列 | 备注 |
|----------------------------|--------------------------------|------|
| `xfe_logistic/gls_api/base_url` | `endpoint_url` | 直接复制 |
| `xfe_logistic/gls_api/username` | `username` | 直接复制 |
| `xfe_logistic/gls_api/password` | `password` | **需解密**（Mag 加密 → 明文） |
| `xfe_logistic/gls_api/parcelpod_resource` | `custom_fields_json.parcelpod_resource` | 走 Codec 序列化 |

---

## 3. 实施清单

### 3.1 迁移脚本

文件：`app/code/community/XFE/Logistic/sql/xfe_logistic_setup/data-upgrade/migrate-gls-api-credentials.php`

行为：
1. 读取 `core_config_data` 中 `xfe_logistic/gls_api/*` 4 条记录
2. 检查 `xfe_carrier` 表 `code='gls'` 行；不存在则 seed
3. 取 `carrier_id`
4. 解密 `password`（用 `Mage::getModel('core/encryption')`）
5. 检查 `xfe_carrier_account` 是否已有 `username`+`endpoint_url` 匹配的行
   - 有 → 跳过（幂等）
   - 无 → INSERT 新行（含 `custom_fields_json` 编码 `parcelpod_resource`）
6. 写 `var/log/migrate-gls-api-credentials.log` 报告

**为什么用 `data-upgrade/` 命名**：参考 `xfe_carrier_setup/data-upgrade/schema-1.0.10.php`，Mage 不会自动扫描运行（仅 `install-*/upgrade-*` 触发），保证管理员必须手动触发。

### 3.2 CLI 入口

文件：`shell/migrate-gls-api-credentials.php`

```bash
php shell/migrate-gls-api-credentials.php              # 真实执行
php shell/migrate-gls-api-credentials.php --dry-run    # 仅打印计划
```

退出码：
- 0 = 成功
- 1 = 失败
- 2 = 缺前置条件（无 Mage 工厂、无 Carrier 模块等）

### 3.3 单元测试

文件：`app/code/community/XFE/Logistic/Test/Sql/MigrateGlsCredentialsTest.php`

测试场景（mock 掉 `Mage::getModel('core/resource')` 与 `core/encryption`）：
- 读 Logistic config（4 条：base_url / username / password 密文 / parcelpod_resource）
- 解密 password
- INSERT Carrier account 行（含 custom_fields_json）
- 已存在相同 username+endpoint_url → 跳过（幂等）
- GLS carrier 不存在 → 自动 seed
- dry-run 模式不写 DB

---

## 4. 验证清单

### 4.1 静态检查

- 脚本 `php -l` 通过
- `Mage::getModel('core/encryption')` 使用前判 `class_exists('Mage', false)`
- 所有 DB 写入用 `addData()` + `save()`（不直接 SQL，保留 Model 事件）

### 4.2 测试无回归

- `php tests/php/run-tests.php`：199 + 新增 = 210+ 全过

### 4.3 dry-run 实测

```bash
php shell/migrate-gls-api-credentials.php --dry-run
# 预期输出:
#   [DRY-RUN] Reading core_config_data: xfe_logistic/gls_api/*
#   [DRY-RUN] Found 4 config rows
#   [DRY-RUN] carrier_id=42 (xfe_carrier.code='gls')
#   [DRY-RUN] Will INSERT xfe_carrier_account:
#     username      = gls_user
#     endpoint_url  = https://shipit-wbm-test01.gls-group.eu:443/backend/rs/tracking
#     password      = ******** (decrypted)
#     custom_fields = {"parcelpod_resource":"parcelpod"}
#   [DRY-RUN] No DB writes performed.
```

### 4.4 真实 run 实测

```bash
# 1. 备份
mysqldump -u root -p m1 core_config_data --where="path LIKE 'xfe_logistic/gls_api/%'" > gls_config_backup.sql

# 2. dry-run
php shell/migrate-gls-api-credentials.php --dry-run

# 3. 真实执行
php shell/migrate-gls-api-credentials.php

# 4. 验证
mysql -u root -p m1 -e "SELECT account_id, carrier_id, username, endpoint_url FROM xfe_carrier_account WHERE endpoint_url LIKE '%gls%';"

# 5. 触发一次 POD 请求，看 PodService 注入路径是否命中
# (代码埋点: PodService::__construct 加日志，或观察 fallback 路径不再触发)
```

### 4.5 fallback 验证

迁移后，临时把 `xfe_carrier_account` 中 GLS 账号行的 `status` 设为 0，
PodService 注入路径返 null → fallback 到 system config 路径仍工作（验证安全网保留）。

---

## 5. 风险与回滚

### 5.1 风险

| 风险 | 缓解 |
|------|------|
| `core/encryption` 密钥不匹配（开发/生产不同） | 脚本输出解密结果 hash（不输出明文），让管理员对照原密码 |
| `custom_fields_json` Codec 兼容旧账号 | Codec 自 1.0.14 起稳定，新增 `parcelpod_resource` key 不冲突 |
| Carrier `code='gls'` 行已存在但 username 不同 | INSERT 新行不覆盖；Carrier 表允许多账号同 carrier |
| 脚本幂等性被破坏（重复 INSERT） | 检查 `username+endpoint_url` 双键匹配 |
| 真实 run 写入了不期望的账号 | DELETE WHERE endpoint_url=... 即可回滚（system config 不回滚） |

### 5.2 回滚方案

```sql
-- 1. 找到迁移脚本插入的行（看 var/log/migrate-gls-api-credentials.log）
SELECT * FROM xfe_carrier_account WHERE username = '<原 username>' AND endpoint_url = '<原 endpoint>';

-- 2. 删除（如不再需要）
DELETE FROM xfe_carrier_account WHERE account_id = <id>;

-- 3. (可选) 删除 seed 的 carrier 主档行
DELETE FROM xfe_carrier WHERE code = 'gls' AND NOT EXISTS (SELECT 1 FROM xfe_carrier_account WHERE carrier_id = xfe_carrier.entity_id);
```

system config 的 Logistic `xfe_logistic/gls_api/*` **不回滚**（保留作安全网）。

---

## 6. 后续工作

1. 观察期 1-2 周：确认所有 POD 请求走注入路径（fallback 不触发）
2. 删除 Logistic `system.xml` 中 `xfe_logistic/gls_api/*` 字段定义
3. 删除 `core_config_data` 中 4 条 `xfe_logistic/gls_api/*` 行
4. 删除 `Helper::getParcelPodUrl()` + `getBasicAuthHeaderValue()`（无外部调用方时）
5. PodService `getProofOfDelivery` 删除 fallback 分支（最终态）

---

## 7. 修订记录

| 日期 | 变更 | 作者 |
|------|------|------|
| 2026-09-17 | 初版：GLS API 凭据 data-upgrade 迁移脚本 | hanson.gao + AI 助手 |
