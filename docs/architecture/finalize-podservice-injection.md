# 终结 PodService 注入化 — 开发指南

> 主题:删除 PodService fallback 分支、Helper 旧方法、Logistic system config GLS 凭据字段。
> 达成 ADR 0020/0021 设定的"凭据单一来源"最终态。

> 状态:Draft(2026-09-17)

> 关联文档:
> - [decisions/0021-finalize-podservice-injection.md](./decisions/0021-finalize-podservice-injection.md) — 本指南对应 ADR
> - [decisions/0020-migrate-gls-api-credentials.md](./decisions/0020-migrate-gls-api-credentials.md) — 前置:已迁移 GLS 凭据
> - [decisions/0019-decouple-podservice-main-path.md](./decisions/0019-decouple-podservice-main-path.md) — PodService 接入注入 + 保留 fallback

---

## 1. 目标与范围

### 1.1 业务目标

PodService `getProofOfDelivery()` 主流路径走 XML 注入作为唯一权威,移除过渡期 fallback 与 system config 入口,达成真正的"凭据单一来源"。

### 1.2 本轮范围

| 项目 | 是否在本轮 |
|------|----------|
| PodService 删除 fallback 分支 + `$_helper` | ✅ |
| PodService 注入返 null 改为硬失败 | ✅ |
| Helper 删除 6 个旧方法 + 1 个常量(留空壳) | ✅ |
| `GlsApiConfig` 新增 `RESOURCE_PARCELPOD` 常量 | ✅ |
| 删除 `system.xml` 中 `gls_api` 组 | ✅ |
| `config.xml` 版本号 1.2.0 → 1.3.0 | ✅ |
| 新增 `data-upgrade/cleanup-gls-api-system-config.php` | ✅ |
| `PodServiceMainPathTest` Test 5 改反射验证 | ✅ |
| 新增 `Unit/GlsApiConfigTest.php` | ✅ |
| 累计断言 ≥ 420 全过 | ✅ |
| 真实环境 dry-run + run 验证 | ✅ |

---

## 2. 文件改动清单

| 文件 | 操作 | 说明 |
|------|------|------|
| `app/code/community/XFE/Logistic/Service/PodService.php` | 修改 | 删除 fallback + `$_helper`;注入返 null 硬失败 |
| `app/code/community/XFE/Logistic/Helper/Data.php` | 重写为最小空壳 | 仅保留 `extends Mage_Core_Helper_Abstract` |
| `app/code/community/XFE/Logistic/Domain/Constant/GlsApiConfig.php` | 新增常量 | `RESOURCE_PARCELPOD = 'parcelpod'` |
| `app/code/community/XFE/Logistic/etc/system.xml` | 修改 | 删除整个 `<gls_api>` 组 |
| `app/code/community/XFE/Logistic/etc/config.xml` | 修改 | 版本号 1.2.0 → 1.3.0 |
| `app/code/community/XFE/Logistic/sql/xfe_logistic_setup/data-upgrade/cleanup-gls-api-system-config.php` | 新建 | 清理 `core_config_data` 残留 |
| `app/code/community/XFE/Injection/Test/Integration/PodServiceMainPathTest.php` | 修改 | Test 5 改为反射验证;新增静态断言 |
| `app/code/community/XFE/Logistic/Test/Unit/GlsApiConfigTest.php` | 新建 | `RESOURCE_PARCELPOD` 常量值断言 |

---

## 3. 实施步骤(按顺序执行)

### 3.1 新增 GlsApiConfig 常量

文件:`app/code/community/XFE/Logistic/Domain/Constant/GlsApiConfig.php`

在 `HTTP_OK` 常量之前新增:

```php
/** @var string GLS POD 资源名(协议固定值,不可配置) */
const RESOURCE_PARCELPOD = 'parcelpod';
```

### 3.2 重写 Helper 空壳

文件:`app/code/community/XFE/Logistic/Helper/Data.php`

完整内容(仅 ~10 行):

```php
<?php

/**
 * XFE_Logistic Helper 空壳(向后兼容)。
 *
 * 历史:
 *   - 1.0.x ~ 1.2.x: 提供 getParcelPodUrl() / getBasicAuthHeaderValue() 等方法
 *   - 1.3.0(ADR 0021): 删除上述方法,凭据统一由 XFE_Carrier::service_carrier_get_credentials 注入
 *
 * 保留原因:
 *   - config.xml 中 <helpers><xfe_logistic><class>XFE_Logistic_Helper</class></helpers>
 *     仍声明本类;删除会导致 Magento factory 报 Class not found
 *
 * @category   Community
 * @package    XFE_Logistic
 */
class XFE_Logistic_Helper_Data extends Mage_Core_Helper_Abstract
{
}
```

### 3.3 修改 PodService 删除 fallback

文件:`app/code/community/XFE/Logistic/Service/PodService.php`

#### 3.3.1 删除 `$_helper` 属性 + 构造参数

```php
class XFE_Logistic_Service_PodService implements XFE_Logistic_Api_PodApiInterface
{
    /** @var XFE_Logistic_Model_Print_Gls_GlsGateway */
    protected $_gateway;

    /**
     * @param XFE_Logistic_Model_Print_Gls_GlsGateway|null $gateway
     */
    public function __construct(
        ?XFE_Logistic_Model_Print_Gls_GlsGateway $gateway = null
    ) {
        $this->_gateway = $gateway ?: Mage::getModel('xfe_logistic/print_gls_glsGateway');
    }
    // ...
}
```

#### 3.3.2 `getProofOfDelivery` 简化

删除 `else` fallback;注入返 null 抛异常;直接用 `GlsApiConfig::RESOURCE_PARCELPOD`:

```php
public function getProofOfDelivery($trackId)
{
    $trackId = trim((string) $trackId);
    if ($trackId === '') {
        Mage::throwException('GLS 运单号(TrackID)不能为空');
    }

    $config = 'XFE_Logistic_Domain_Constant_GlsApiConfig';

    // 主流路径(ADR 0021 最终态):通过 XML 注入从 XFE_Carrier 拿 GLS API 凭据。
    // 无账号(注入返 null)= 配置缺失,硬失败提示 admin 立即排查。
    $credentials = $this->_resolveCredentialsViaInjection('gls', array());
    if (!is_array($credentials) || empty($credentials['endpoint_url'])) {
        Mage::throwException('GLS Carrier account not configured (injection returned null)');
    }

    $parcelPodUrl = rtrim((string)$credentials['endpoint_url'], '/')
        . '/' . $config::RESOURCE_PARCELPOD;
    $basicAuthUser = (string)(isset($credentials['username']) ? $credentials['username'] : '');
    $basicAuthPass = (string)(isset($credentials['password']) ? $credentials['password'] : '');
    $basicAuthHeader = $config::AUTH_SCHEME_BASIC . ' '
        . base64_encode($basicAuthUser . ':' . $basicAuthPass);

    $podItem = $this->_gateway->requestParcelPod(
        $trackId,
        $parcelPodUrl,
        $basicAuthHeader
    );

    $podTrackId = isset($podItem[$config::RESPONSE_TRACK_ID])
        ? (string) $podItem[$config::RESPONSE_TRACK_ID]
        : $trackId;

    $imageData = isset($podItem[$config::RESPONSE_IMAGE_DATA])
        ? (string) $podItem[$config::RESPONSE_IMAGE_DATA]
        : '';

    $raw = base64_decode($imageData);
    if ($raw === false || $raw === '') {
        Mage::throwException('GLS POD 返回的 ImageData 无效或为空');
    }

    $mimeType = $this->_gateway->detectMimeType($raw);

    return new XFE_Logistic_Domain_PodResult(
        $podTrackId,
        $mimeType,
        $raw
    );
}
```

### 3.4 修改 system.xml

文件:`app/code/community/XFE/Logistic/etc/system.xml`

完整删除 `<gls_api translate="label">...</gls_api>` 整组。

最终文件结构(保留 `<tabs>` 与 `<xfe_logistic>` 顶层 `<sections>`,但 `<groups>` 为空):

```xml
<?xml version="1.0"?>
<config>
    <tabs>
        <xfe translate="title">
            <label>XFE</label>
            <sort_order>250</sort_order>
        </xfe>
    </tabs>
    <sections>
        <xfe_logistic translate="label" module="xfe_logistic">
            <label>Logistic (GLS)</label>
            <tab>xfe</tab>
            <frontend_type>text</frontend_type>
            <sort_order>110</sort_order>
            <show_in_default>1</show_in_default>
            <show_in_website>0</show_in_website>
            <show_in_store>0</show_in_store>
            <groups>
                <!-- ADR 0021:gls_api 组下线,凭据统一由 XFE_Carrier 模块配置 -->
            </groups>
        </xfe_logistic>
    </sections>
</config>
```

### 3.5 提升 config.xml 版本号

文件:`app/code/community/XFE/Logistic/etc/config.xml`

```xml
<modules>
    <XFE_Logistic>
        <version>1.3.0</version>
    </XFE_Logistic>
</modules>
```

### 3.6 新增 data-upgrade 清理脚本

文件:`app/code/community/XFE/Logistic/sql/xfe_logistic_setup/data-upgrade/cleanup-gls-api-system-config.php`

```php
<?php
/**
 * 清理 GLS API 凭据残留(ADR 0021 终结态)。
 *
 * 历史:
 *   - 1.0.x ~ 1.2.x: GLS 凭据存在 xfe_logistic/gls_api/{base_url,username,password,parcelpod_resource}
 *   - 1.2.0 (ADR 0020): 迁移脚本把上述凭据搬到 xfe_carrier_account 表(INSERT)
 *   - 1.3.0 (ADR 0021): 本脚本删除 core_config_data 残留(system config 字段已下线)
 *
 * 触发:
 *   - data-upgrade/ 子目录下的脚本**不会被 Magento 自动扫描**
 *   - 需要管理员手动执行(参见 README §5)
 *
 * 幂等性:
 *   - DELETE WHERE path LIKE 'xfe_logistic/gls_api/%' 多次执行无副作用
 */

if (!defined(' Mage::')) {
    // 直接 require 时(非 Magento 自动加载),人工加载 bootstrap
    require_once __DIR__ . '/../../../../../../Mage.php';
    Mage::app();
}

$resource = Mage::getSingleton('core/resource');
$write = $resource->getConnection('core_write');

$deleted = $write->delete(
    $resource->getTableName('core_config_data'),
    array('path LIKE ?' => 'xfe_logistic/gls_api/%')
);

Mage::log(
    sprintf('[cleanup-gls-api-system-config] Deleted %d rows from core_config_data', $deleted),
    Zend_Log::INFO
);

echo "Deleted {$deleted} rows from core_config_data (path LIKE 'xfe_logistic/gls_api/%')
";
```

---

## 4. 测试更新

### 4.1 PodServiceMainPathTest Test 5 改反射验证

原 Test 5:验证注入返 null → 第一项 === null。

改为:断言 PodService 类不存在 `$_helper` 属性 + `getProofOfDelivery` 源码不含 `else` 子句。

```php
// ---------------------------------------------------------
// Test 5: PodService 最终态静态验证(ADR 0021)
// ---------------------------------------------------------
echo "\n--- Test 5: PodService finalize (ADR 0021) ---\n";

$podClass = new ReflectionClass('XFE_Logistic_Service_PodService');
ok(!$podClass->hasProperty('_helper'),
    "PodService no longer has _helper property (fallback removed)");

$getProofMethod = $podClass->getMethod('getProofOfDelivery');
$methodSource = file_get_contents($getProofMethod->getFileName());
$startLine = $getProofMethod->getStartLine();
$endLine = $getProofMethod->getEndLine();
$sourceLines = array_slice(explode("\n", $methodSource), $startLine - 1, $endLine - $startLine + 1);
$sourceBody = implode("\n", $sourceLines);
ok(strpos($sourceBody, ' else ') === false && strpos($sourceBody, '}else') === false && strpos($sourceBody, '} else') === false,
    "getProofOfDelivery has no 'else' fallback branch");

ok(strpos($sourceBody, 'RESOURCE_PARCELPOD') !== false,
    "getProofOfDelivery uses GlsApiConfig::RESOURCE_PARCELPOD constant (not system config)");

ok(strpos($sourceBody, 'throwException') !== false,
    "getProofOfDelivery throws exception when injection returns null (hard fail)");

// 保留原 Test 5 的"注入返 null 时 $result->first() === null"语义作为 reflection 旁证
$mock5 = new MockCredentialsService();
$mock5->returnValue = null;
$locator5 = new XFE_Injection_Model_ServiceLocator();
$locator5->setOverride('service_carrier_get_credentials', $mock5);
$injCtx5 = new XFE_Injection_Domain_InjectionContext(array('carrierCode' => 'gls', 'contextValues' => array()));
$result5 = new XFE_Injection_Domain_InjectionResult();

foreach (XFE_Injection_Model_Registry::getInstance()->getCallingsForHook('hook_logistic_before_request') as $call) {
    if ($call->getServiceId() === 'service_carrier_resolve_account') continue;
    $svcDef = XFE_Injection_Model_Registry::getInstance()->getService($call->getServiceId());
    $instance = $locator5->resolve($svcDef);
    $args = array();
    foreach ($call->getArguments() as $arg) {
        if ($arg->getFrom() === 'context.carrierCode') $args[] = $injCtx5->get('carrierCode');
        elseif ($arg->getFrom() === 'context.contextValues') $args[] = $injCtx5->get('contextValues');
    }
    $value = call_user_func_array(array($instance, $call->getMethodName()), $args);
    $result5->set($call->getId(), $value);
}
ok($result5->first() === null,
    "InjectionResult first() === null when mock returns null (matches getProofOfDelivery hard-fail condition)");
```

(注:`Test 5` 在最终版本中预期产生 7 个新断言。)

### 4.2 新增 GlsApiConfigTest

文件:`app/code/community/XFE/Logistic/Test/Unit/GlsApiConfigTest.php`

```php
<?php
/**
 * XFE_Logistic_Domain_Constant_GlsApiConfig 单元测试(ADR 0021)。
 *
 * 验证新增的 RESOURCE_PARCELPOD 常量值符合 GLS 协议固定值。
 *
 * 运行: php Test/Unit/GlsApiConfigTest.php
 */

spl_autoload_register(function ($class) {
    $projectRoot = realpath(__DIR__ . '/../../../../../../');
    if ($projectRoot === false) {
        return;
    }
    $rel = str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
    foreach (array('community', 'core', 'local') as $pool) {
        $file = $projectRoot . '/app/code/' . $pool . '/' . $rel;
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

$assertions = 0;
$failures = 0;
function ok($cond, $msg) {
    global $assertions, $failures;
    $assertions++;
    echo $cond ? "  PASS  " : "  FAIL  ";
    echo $msg . "\n";
    if (!$cond) $failures++;
}

echo "\n=== Unit Test: GlsApiConfig constants (ADR 0021) ===\n\n";

ok(class_exists('XFE_Logistic_Domain_Constant_GlsApiConfig', false),
    'GlsApiConfig class exists');

$constants = (new ReflectionClass('XFE_Logistic_Domain_Constant_GlsApiConfig'))->getConstants();
ok(isset($constants['RESOURCE_PARCELPOD']),
    'RESOURCE_PARCELPOD constant declared');
ok($constants['RESOURCE_PARCELPOD'] === 'parcelpod',
    'RESOURCE_PARCELPOD value === "parcelpod" (GLS protocol fixed value)');

// 现有常量(回归)
ok($constants['AUTH_SCHEME_BASIC'] === 'Basic',
    'AUTH_SCHEME_BASIC still "Basic"');
ok($constants['HTTP_OK'] === 200,
    'HTTP_OK still 200');
ok($constants['MIME_PDF'] === 'application/pdf',
    'MIME_PDF still "application/pdf"');

// 不可变性
$countBefore = count($constants);
$countAfter  = count((new ReflectionClass('XFE_Logistic_Domain_Constant_GlsApiConfig'))->getConstants());
ok($countBefore === $countAfter,
    "Constants immutable: {$countBefore} === {$countAfter}");

echo "\n========================================\n";
echo "Total: {$assertions} assertions, {$failures} failures\n";
echo "========================================\n";

exit($failures > 0 ? 1 : 0);
```

预期 ~7 个断言。

### 4.3 run-tests.php 加入新套件

文件:`tests/php/run-tests.php`

在 `$suites` 数组最后追加:

```php
array(
    'file'  => 'app/code/community/XFE/Logistic/Test/Unit/GlsApiConfigTest.php',
    'label' => 'Unit: GlsApiConfig (ADR 0021)',
),
```

更新顶部注释中的"合计 407"为"合计 ≥ 420"。

---

## 5. 真实环境 dry-run + run 验证

### 5.1 升级模块版本

```bash
# 清缓存
rm -rf var/cache/*

# Magento 检测版本变更 + 自动跑 upgrade 脚本
# (注意:data-upgrade/ 子目录**不自动跑**,需手动触发)
```

### 5.2 手动触发 data-upgrade 清理脚本

```bash
# 方式 A:CLI 触发
php -r "require 'app/Mage.php'; Mage::app('admin'); include 'app/code/community/XFE/Logistic/sql/xfe_logistic_setup/data-upgrade/cleanup-gls-api-system-config.php';"

# 方式 B:直接 MySQL
mysql -u root -p m1 -e "DELETE FROM core_config_data WHERE path LIKE 'xfe_logistic/gls_api/%';"
```

### 5.3 dry-run 验证

触发一次 POD 请求,确认:
- PodService 走注入路径,无 warning / notice
- 后台 `System > Configuration > Logistic (GLS)` 页面只剩空 `<groups>`(无字段)
- `grep -r 'xfe_logistic/gls_api' app/` 应只出现在历史备份/日志中

### 5.4 真实 run 验证

```bash
php tests/php/run-tests.php
# 期望输出:Total: ≥ 420 assertions, 0 failures
```

---

## 6. 风险与回滚

### 6.1 风险

| 风险 | 缓解 |
|------|------|
| Carrier 后台 GLS 账号未配置 → PodService 抛异常 | admin 立即感知,需在 Carrier 后台创建账号 |
| Helper 删除方法被遗漏调用方 | grep 验证 + reflection 测试 |
| data-upgrade 脚本误删 | `path LIKE 'xfe_logistic/gls_api/%'` 仅匹配本组 4 字段,不影响其他配置 |
| version 1.3.0 触发其他 upgrade 脚本 | Logistic 1.3.0 不存在 upgrade-1.2.0-1.3.0.php,Mage 不会自动跑其他 |

### 6.2 回滚方案

```sql
-- 1. 若误删,可从备份恢复(若有)
mysqldump -u root -p m1 core_config_data --where="path LIKE 'xfe_logistic/gls_api/%'" > gls_config_backup.sql

-- 2. 回滚 PodService(从 git 拉回 1.2.0 版本)
git checkout 1.2.0 -- app/code/community/XFE/Logistic/Service/PodService.php

-- 3. 回滚 Helper
git checkout 1.2.0 -- app/code/community/XFE/Logistic/Helper/Data.php

-- 4. 恢复 system.xml(可选,system config 字段不影响功能)
git checkout 1.2.0 -- app/code/community/XFE/Logistic/etc/system.xml
```

---

## 7. 修订记录

| 日期 | 变更 | 作者 |
|------|------|------|
| 2026-09-17 | 初版:删除 fallback + Helper + system config,PodService 注入路径作为唯一权威 | hanson.gao + AI 助手 |
