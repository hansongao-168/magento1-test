# Carrier MatchContext 完全解耦 开发指南

> 主题：把 PodService::resolveCredentialsViaInjection 中对 XFE_Carrier_Model_Service_Rule_MatchContext 的类型依赖，改为原生 array 类型，实现 Logistic 真正零 Carrier 代码耦合。

> 状态：Draft（2026-09-17）

> 关联文档：

> - [decisions/0016-decouple-match-context.md](./decisions/0016-decouple-match-context.md)

> - [decisions/0015-injection-carrier-logistic-integration.md](./decisions/0015-injection-carrier-logistic-integration.md)

> - [injection-carrier-logistic-integration.md](./injection-carrier-logistic-integration.md)


---

## 1. 目标与范围


### 1.1 业务目标


把 PodService::resolveCredentialsViaInjection 第二个参数从 XFE_Carrier_Model_Service_Rule_MatchContext 对象改为原生 array，让 Logistic 端 PHP 代码零 Carrier 类名依赖。

验证方式：`grep "XFE_Carrier" PodService.php` 仅命中注释/PHPDoc，无类型签名行。


### 1.2 本轮范围


| 项目 | 是否在本轮 |
|------|----------|
| 适配器签名：MatchContext 改为 array | 是 |
| 适配器内部 array 转 MatchContext | 是 |
| PodService 签名：MatchContext 改为 array | 是 |
| injection.xml 参数映射：context 改为 contextValues | 是 |
| 集成测试更新：MockContext 改为 array | 是 |
| 单元测试覆盖 array 转 MatchContext 转换 | 是 |
| 替换 MatchContext 内部使用方 | 否 |
| 引入新 Domain MatchContext 类 | 否 |
| 改动 PodService::getProofOfDelivery | 否 |

---

## 2. 设计要点


### 2.1 数据流


调用链：

1. PodService 接收 array (contextValues)，构造 InjectionContext
2. InjectionContext 注入到 Runner::trigger
3. Runner 从 calling 中提取参数：carrierCode (string) + contextValues (array)
4. 调用 Carrier 适配器 resolveAccountId(string, array, bool)
5. 适配器内部 array 转 MatchContext，委托给 Rule Resolver
6. Resolver 返回 accountId


### 2.2 关键决策


| 选项 | 选择 | 理由 |
|------|------|------|
| 参数类型 | array | PHP 原生、零依赖、最大公约数 |
| DTO 转换点 | Carrier 适配器层 | 单一映射点，重构集中 |
| 新建 Domain 类 | 否 | 重复实现 + 维护成本 |
| XML 注入参数 key | contextValues | 语义清晰（vs 旧 context） |

---

## 3. Carrier 侧改造


### 3.1 修改适配器签名


文件：app/code/community/XFE/Carrier/Service/Account/CredentialViaInjection.php


适配器签名变化：

- 第二个参数：XFE_Carrier_Model_Service_Rule_MatchContext 对象 → 原生 array
- 内部：array 通过 new XFE_Carrier_Model_Service_Rule_MatchContext(values) 转 DTO
- 然后委托给 Resolver::resolve(carrierId, matchContext, fallback)


PHPDoc 必须列出合法的 array key（country_code / city / zip_code / package_weight 等），让 IDE 仍有补全提示。


### 3.2 版本号


XFE_Carrier/etc/config.xml：1.0.16 → 1.0.17

---

## 4. Logistic 侧改造


### 4.1 修改 PodService 签名


文件：app/code/community/XFE/Logistic/Service/PodService.php


resolveCredentialsViaInjection 方法签名变化：

- 第二个参数：XFE_Carrier_Model_Service_Rule_MatchContext 对象 → 原生 array
- InjectionContext key：context → contextValues
- 内部从 contextValues 提取 array 传给 Runner::trigger


### 4.2 更新 injection.xml


文件：app/code/community/XFE/Logistic/etc/injection.xml


把 calling 节点的 argument：

- 旧：<argument name="context" from="context"/>
- 新：<argument name="contextValues" from="context.contextValues"/>


### 4.3 版本号


XFE_Logistic/etc/config.xml：1.1.0 → 1.1.1

---

## 5. 测试更新


### 5.1 集成测试 (CarrierLogisticTest.php)


MockCarrierService::resolveAccountId 签名第二个参数改为 array。

Test 6 (真实 XML) 增加用例：验证 calling.getArguments()[1].from 等于 context.contextValues。


### 5.2 新增单元测试 (Unit/MatchContextAdapterTest.php)


独立 PHP 进程运行，验证：

| # | 场景 | 预期 |
|---|------|------|
| 1 | 适配器接收 array 后构造 MatchContext | DTO get(country_code) 返回 array 值 |
| 2 | 适配器签名第二参数必须是 array | 非 array 触发 TypeError |
| 3 | 空 array 也能传 | DTO 接受空 values，返回 null |
| 4 | array 多余 key 透传 | DTO get(foo) 返回原值 |

---

## 6. 验证清单


### 6.1 静态检查

- php -l 所有修改的 PHP 文件
- grep XFE_Carrier_ PodService.php 仅命中 PHPDoc 行

### 6.2 单元测试

- php Test/Unit/InjectionTest.php：71 assertions 全过（无回归）
- php Test/Unit/MatchContextAdapterTest.php：新增 4+ 全过
- php Test/Integration/CarrierLogisticTest.php：更新后 32+ 全过

### 6.3 耦合度 grep 验证（关键）


```bash
grep -nE "XFE_Carrier_[A-Za-z_]+\$" app/code/community/XFE/Logistic/Service/PodService.php
# 预期：无匹配（type hint 不存在）

grep -n "XFE_Carrier" app/code/community/XFE/Logistic/Service/PodService.php
# 预期：仅 PHPDoc 注释行
```

### 6.4 行为不变性

- PodService::getProofOfDelivery 完全未改动
- XFE_Carrier_Model_Service_Rule_MatchContext 完全未改动（Service 内部仍用对象）
- XFE_Carrier_Model_Service_Rule_Resolver 完全未改动

### 6.5 启动期检查

- Registry::validateIntegrity() 通过
- 真实 XML 合并后 calling.getArguments()[1].from === context.contextValues

---

## 7. 风险与回滚


### 7.1 风险


| 风险 | 触发条件 | 影响 | 缓解 |
|------|---------|------|------|
| 适配器签名变化 | 已有调用方传对象 | TypeError | 本轮范围无旧调用方 |
| XML 参数 key 变化 | 漏改某处 | 运行时 contextValues 为 null | 集成测试覆盖 |
| array key 不匹配 | Logistic 传错 key 名 | Resolver 静默忽略 | PHPDoc 列出合法 key |

### 7.2 回滚方案


1. 把适配器签名改回 MatchContext
2. 把 PodService 签名改回 MatchContext
3. 把 injection.xml 参数映射改回 context
4. 撤销版本号

回滚成本低：3 处类型签名 + 1 处 XML 属性，5 分钟内可完成。

---

## 8. 后续工作


1. PHPDoc 静态分析：用 Psalm/PHPStan 校验 Logistic 不依赖 Carrier 类
2. 彻底消除 Carrier 类在 Logistic 的任何痕迹：包括模板、layout XML、design block 注释
3. 替换 PodService::getProofOfDelivery 主流路径：用 resolveCredentialsViaInjection 替换硬编码 Helper
4. 更新 carrier-facade.md：标注 Facade 模型为已废弃，改用 XML 注入 + array context

---

## 9. 关联决策


- ADR 0015：引入 Carrier↔Logistic XML 注入集成（本文档基础）
- ADR 0016：本文档对应的设计决策
- carrier-facade.md：历史 PHP Facade（即将标注为废弃）

---

## 10. 修订记录


| 日期 | 变更 | 作者 |
|------|------|------|
| 2026-09-17 | 初版：定义完全解耦 MatchContext 的最小化方案 | hanson.gao + AI 助手 |
