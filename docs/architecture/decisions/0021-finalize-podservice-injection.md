# 0021. 终结 PodService 注入化:删除 fallback、Helper 与 system config

- 状态：Accepted
- 验证日期：2026-09-24
- 验证摘要：PodService 删除 fallback + $_helper；Helper 瘦身至空壳；system.xml 删除 gls_api 组；config.xml 1.2.0 → 1.3.0；cleanup 脚本就绪；GlsApiConfigTest 11 断言 + PodServiceMainPathTest +4 断言全过；累计 467 断言 0 失败
- 日期：2026-09-17
- 决策者：AI 助手(经用户确认)

## 关联文档

- [0019-decouple-podservice-main-path.md](./0019-decouple-podservice-main-path.md) — PodService 接入注入 + 保留 fallback
- [0020-migrate-gls-api-credentials.md](./0020-migrate-gls-api-credentials.md) — GLS 凭据 data-upgrade 迁移(已执行)
- [../finalize-podservice-injection.md](../finalize-podservice-injection.md) — 本 ADR 对应的开发指南

## 背景

ADR 0020 已完成 GLS 凭据从 `xfe_logistic/gls_api/*` (system config) 到 `xfe_carrier_account` 表的迁移,并在真实环境 dry-run + run 各执行一次。

迁移后,PodService 的注入路径(`getCredentialsByCarrier`) 会返回完整的 `endpoint_url` + `username` + `password` 数组,fallback 路径(`Helper::getParcelPodUrl()` + `getBasicAuthHeaderValue()`) 不再被命中。

但当前 `PodService::getProofOfDelivery()` 仍保留以下过渡期产物:
1. **`else` fallback 分支**(行 51-56):注入返 null 时走 Helper → system config
2. **`$this->_helper` 属性**:仅 fallback 路径使用 + 主路径读 `parcelpod_resource`
3. **`Helper::getParcelPodUrl()` / `getBasicAuthHeaderValue()`**:fallback 专用,无外部调用方
4. **`Helper::getConfig()` / `getBaseUrl()` / `_getDecryptedPassword()` / `CONFIG_PATH_PREFIX`**:只服务于上述 fallback 方法
5. **`etc/system.xml` 中 `xfe_logistic/gls_api/{base_url,username,password,parcelpod_resource}` 4 字段定义**:system config 入口
6. **`core_config_data` 中上述 4 条记录**:管理员历史配置残留

`Helper::getParcelPodResource()` 还被主路径使用,但 `parcelpod` 是 GLS 协议固定值,应改为 `XFE_Logistic_Domain_Constant_GlsApiConfig` 常量,而不是从 system config 读取(避免引入配置漂移)。

## 决策

**PodService 注入路径作为唯一权威,删除 fallback、Helper 旧方法、system config 字段,实现最终态。**

### 1. PodService 简化

- 删除 `$_helper` 属性与构造参数
- `getProofOfDelivery()` 删除 `else` fallback 分支
- 注入返 null 时,改为抛 `Mage::throwException('GLS Carrier account not configured')` 硬失败
- 引入 `XFE_Logistic_Domain_Constant_GlsApiConfig::RESOURCE_PARCELPOD` 常量替换 `getParcelPodResource()` 调用

### 2. Helper 瘦身

- 删除以下方法(零外部调用方):
  - `getConfig()`
  - `getBaseUrl()`
  - `getParcelPodResource()`
  - `getParcelPodUrl()`
  - `_getDecryptedPassword()`
  - `getBasicAuthHeaderValue()`
- 删除 `CONFIG_PATH_PREFIX` 常量
- 保留 `XFE_Logistic_Helper_Data` 继承 `Mage_Core_Helper_Abstract` 的空壳(向后兼容,避免破坏 `<helpers><xfe_logistic><class>XFE_Logistic_Helper</class></helpers></xfe_logistic>` 工厂链)
- 保留文件本身(防止 `<helpers>` 节点配置报错)

### 3. system config 字段下线

- 删除 `etc/system.xml` 中 `xfe_logistic/gls_api` 组下 4 个 `<fields>` 项
- 删除整个 `<gls_api translate="label">` 组
- 提升 `config.xml` 版本号 `1.2.0` → `1.3.0`
- 新增 `data-upgrade/cleanup-gls-api-system-config.php` 脚本:删除 `core_config_data` 中 `path LIKE 'xfe_logistic/gls_api/%'` 的全部行
  - 文件位置:`app/code/community/XFE/Logistic/sql/xfe_logistic_setup/data-upgrade/cleanup-gls-api-system-config.php`
  - `data-upgrade/` 子目录的脚本不会被 Magento 自动扫描,需要管理员手动触发

### 4. GlsApiConfig 新增常量

- 新增 `const RESOURCE_PARCELPOD = 'parcelpod'`(GLS POD 资源名,协议固定,不应可配置)
- 单元测试覆盖:常量值断言

### 5. 测试更新

- `PodServiceMainPathTest` Test 5:从"fallback 验证"改为"reflection 验证"(fallback 分支已删除)
- 新增反射断言:`getProofOfDelivery` 方法体不再含 `else` 子句
- 新增反射断言:PodService 类不含 `$_helper` 属性
- 新增 `Unit/GlsApiConfigTest.php`:`RESOURCE_PARCELPOD === 'parcelpod'`
- 零耦合验证继续保留:PodService.php 中 0 处 `XFE_Carrier_*` 类型引用

## 备选方案

### A. ✅ 完全删除 fallback + Helper 旧方法 + system config(本决策)

- 优点:真正达成"凭据单一来源",代码极简
- 缺点:删除 system config 是不可逆操作,管理员误操作无法回滚
- 采纳:ADR 0020 验证期已通过,且有 `migrate-gls-api-credentials.log` 备份证据

### B. 保留 Helper 但 `@deprecated` 标注

- 优点:回滚容易
- 缺点:违背"删除而非弃用"原则(AGENTS.md §5.4 红线);admin 仍可能在 system config 配错
- 否决

### C. 用 system config 新位置替代 `xfe_logistic/gls_api/*`

- 优点:不删 system config
- 缺点:维持双配置源,与 ADR 0020 单一来源目标冲突
- 否决

## 后果

### 正面

- 凭据单一来源彻底达成,无任何双配置漂移可能
- PodService 主流路径代码简化 ~30 行,意图更清晰
- Helper 文件从 95 行减至 ~10 行(仅空壳)
- system config 后台减少 4 字段 + 1 组,降低 admin 误配风险
- 单元测试断言覆盖更完整(exception 路径显式覆盖)

### 负面

- 删除 system config 是不可逆操作
  - 缓解:`migrate-gls-api-credentials.log` 已记录旧值;迁移脚本本身是 INSERT,可 SELECT+DELETE 回滚
- PodService 注入返 null 时硬失败
  - 缓解:Carrier 后台必须配 GLS 账号(业务要求);缺失应被早期发现
- Helper 类保留空壳(向后兼容)
  - 缓解:不增加复杂度,仅避免工厂链断链

## 实施检查清单

- [ ] ADR 0021(本文档)
- [ ] 开发指南 `docs/architecture/finalize-podservice-injection.md`
- [ ] `PodService.php` 删除 fallback 分支 + `$_helper` + 注入硬失败
- [ ] `Helper/Data.php` 删除 6 个方法 + 1 个常量,留空壳
- [ ] `GlsApiConfig.php` 新增 `RESOURCE_PARCELPOD` 常量
- [ ] `etc/system.xml` 删除 `gls_api` 组
- [ ] `etc/config.xml` 版本号 `1.2.0` → `1.3.0`
- [ ] 新增 `data-upgrade/cleanup-gls-api-system-config.php` 脚本
- [ ] `PodServiceMainPathTest` Test 5 改为反射验证 + 新增 reflection 断言
- [ ] 新增 `Unit/GlsApiConfigTest.php`
- [ ] 累计断言 ≥ 420 全过
- [ ] 零 XFE_Carrier 类型耦合继续验证通过

## 注意事项

- **Helper 空壳保留**:`<helpers><xfe_logistic><class>XFE_Logistic_Helper</class></helpers></xfe_logistic>` 在 `config.xml` 中声明,删除类文件会导致 Magento factory 报 "Class not found"。保留 `XFE_Logistic_Helper_Data` 继承 `Mage_Core_Helper_Abstract` 的最小壳即可。
- **data-upgrade 命名**:`data-upgrade/*.php` **不会被 Magento 自动扫描**(仅 `install-*/upgrade-*` 会自动跑)。本脚本位于 `data-upgrade/` 子目录,所以需手动触发 — 这与 ADR 0020 一致。
  - 触发方式:在测试环境执行 `php -r "require 'app/Mage.php'; Mage::app(); include 'app/code/community/XFE/Logistic/sql/xfe_logistic_setup/data-upgrade/cleanup-gls-api-system-config.php';"`
  - 或:直接在 MySQL 客户端执行 `DELETE FROM core_config_data WHERE path LIKE 'xfe_logistic/gls_api/%'` 也合规
- **PodService 注入硬失败语义**:`Mage::throwException('GLS Carrier account not configured')` 在 L4 Controller 调用时会被 `Mage_Core_Controller_Varien_Action` 捕获并显示给 admin,与原 fallback 静默失败不同。这是好事:admin 能立即感知配置缺失。
- **测试覆盖**:删除 fallback 分支后,Test 5 不再有"fallback 行为"可测,转为反射 + 静态分析更合适。
