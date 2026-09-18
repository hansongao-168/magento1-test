# XFE_OAuth2 模块架构

- 状态：Accepted
- 日期：2026-09-09
- 决策者：XFE 开发组
- 关联文档：[oauth2-customer-navigation.md](./oauth2-customer-navigation.md)（前端 layout handle 名纠正）

## 背景

`XFE_OAuth2` 是 Magento 1 平台上的 OAuth 2.0 Server / Resource Provider，对内提供 storefront 与 admin 的 API client 管理界面，对外为第三方应用颁发与校验 token。

模块**不**自实现 OAuth 2.0 协议，而是把 [`bshaffer/oauth2-server-php`](https://github.com/bshaffer/oauth2-server-php)（已 bundle 到 `lib/OAuth2/`）作为底层协议引擎，自己实现 4 个 storage 适配器把它们桥接到 Magento EAV 模型。

本架构文档解决**两次反复出现的"在 Linux 服务器上偶发 `include(OAuth2\Storage\AccessTokenInterface.php) failed to open stream` 报错"**的根因与修复契约。

## 模块定位

| 维度 | 说明 |
|------|------|
| 模块名 | `XFE_OAuth2`（`app/etc/modules/XFE_OAuth2.xml` 启用） |
| 协议版本 | OAuth 2.0（RFC 6749），由 bshaffer 库实现 |
| L1 Domain | `XFE_OAuth2_Domain_*`（暂无） |
| L2 Gateway | `XFE_OAuth2_Model_Storage_*`（OAuth2\Storage\*Interface 适配器）、`XFE_OAuth2_Model_Grant_ClientCredentials` |
| L2 Repository | `XFE_OAuth2_Model_Access_Token` / `Refresh_Token` / `Authorization_Code` / `Client`（Magento 模型，对应 `xfe_oauth2_*` 数据表） |
| L3 Service | `XFE_OAuth2_Model_Server`（组装 `OAuth2\Server`）、`XFE_OAuth2_Helper_Data` |
| L4 Controller | `XFE_OAuth2_*Controller`、`XFE_OAuth2_Adminhtml_*Controller` |

## 单向依赖图

```
L4  controllers/*  ─────┐
                        │  (Mage::getModel / Mage::helper)
                        ↓
L3  Model/Server.php  ──→  XFE_OAuth2_Helper_Data
    Model/Api/*  ─────┘
                        ↓
L2  Model/Storage/AccessToken       ──implements──→  OAuth2\Storage\AccessTokenInterface
    Model/Storage/ClientCredentials  ──implements──→  OAuth2\Storage\ClientCredentialsInterface
                                                    + OAuth2\Storage\ScopeInterface
    Model/Storage/RefreshToken       ──implements──→  OAuth2\Storage\RefreshTokenInterface
    Model/Storage/AuthorizationCode  ──implements──→  OAuth2\Storage\AuthorizationCodeInterface
    Model/Grant/ClientCredentials    ──extends─────→  OAuth2\GrantType\ClientCredentials
                        ↓
L1  (无) ──→  OAuth2\*Interface（由 bshaffer 库提供，位于 lib/OAuth2/）
```

> L2 ↔ bshaffer 接口的依赖**必须**用 PHP 原生 `implements` / `extends` 声明，不能去掉。
> bshaffer `OAuth2\Server` 在构造时（`lib/OAuth2/Server.php` 第 604 行起）会做
> `instanceof OAuth2\Storage\ClientCredentialsInterface` 等强校验，违反则直接抛
> `LogicException("You must supply a storage object implementing ...")`。

## 关键决策：bshaffer 库 + 自建 autoloader

### 库的位置与 autoloader 必要性

- 库代码全部位于 `lib/OAuth2/**`，**不**在 `app/code/community/XFE/OAuth2/` 树下
- Magento 1 的 `Varien_Autoload` 只识别 `Mage_*` / `<Namespace>_*` 这套 PEAR 风格类名
- **`Varien_Autoload` 不会自动加载 `OAuth2\Storage\AccessTokenInterface` 这类带反斜杠命名空间的接口**
- 因此**必须**有专门为 `OAuth2\` 命名空间服务的 `spl_autoload_register`

### autoloader 实现

文件：`lib/XFE/OAuth2/Autoloader.php`

```php
class XFE_OAuth2_Autoloader
{
    private static $_registered = false;

    public static function register()
    {
        if (self::$_registered) {
            return;
        }
        self::$_registered = true;

        // 【必须用绝对路径】Linux PHP-FPM/CLI/Cron 进程 cwd 不一定是 webroot，
        // 相对路径 'lib/...' 会导致 file_exists() 永远 false。
        if (defined('BP')) {
            $libDir = BP . DS . 'lib';
        } else {
            // 兜底：未在 Magento 引导中调用时（如单元测试）
            $libDir = dirname(dirname(dirname(__FILE__)));
        }

        spl_autoload_register(function ($class) use ($libDir) {
            if (strncmp('OAuth2\\', $class, 7) !== 0) {
                return;
            }
            $relativeClass = substr($class, 7);
            $file = $libDir . DS . 'OAuth2' . DS
                  . str_replace('\\', DS, $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}
```

### 入口调用约定

**所有走 OAuth2 命名空间类的入口文件（controller、model、helper）必须在最顶部、class 声明之前**：

```php
require_once BP . '/lib/XFE/OAuth2/Autoloader.php';
XFE_OAuth2_Autoloader::register();
```

`register()` 内部 `$_registered` 幂等保护，重复调用是 no-op。

#### 入口白名单

| 文件 | 已加？ | 备注 |
|------|--------|------|
| `Helper/Data.php` | ✅ | 通过 `_construct()` 兜底 |
| `Model/Server.php` | ✅ | 通过 `__construct()` 兜底 |
| `Model/Api/Abstract.php` | ✅ | |
| `Model/Grant/ClientCredentials.php` | ✅ | 父类 `OAuth2\GrantType\ClientCredentials` 必须先 resolve |
| `controllers/AuthorizeController.php` | ✅ | |
| `controllers/TokenController.php` | ✅ | |
| `controllers/ClientController.php` | ✅ | **2026-09-09 修复补** |
| `controllers/Adminhtml/Xfeoauth2/ClientController.php` | ✅ | **2026-09-09 修复补** |
| `controllers/ApiController.php` | ✅ | **2026-09-09 修复补** |
| `controllers/LoginController.php` | ✅ | **2026-09-09 修复补** |

> **新加 controller / model / block 时**，如果会引用 `OAuth2\` 命名空间类，**必须**在文件顶部 class 之前加 `require_once ... Autoloader.php; XFE_OAuth2_Autoloader::register();`。

## 关键决策：Storage 类必须在文件顶部显式 `require_once` 父接口

### 为什么这是必要的（2026-09-09 修复根因）

`XFE_OAuth2_Model_Storage_AccessToken` 类声明：

```php
class XFE_OAuth2_Model_Storage_AccessToken implements OAuth2\Storage\AccessTokenInterface
```

PHP 在 **编译类声明**时，**必须立即** resolve `implements OAuth2\Storage\AccessTokenInterface` 指向的接口。这意味着：

- 此刻 `OAuth2\Storage\AccessTokenInterface` 接口类必须已经**加载到 Zend 内存**（即 `interface_exists(..., false)` 为 `true`）
- 如果 Zend 里没这个接口，PHP 会调 `spl_autoload_register` 注册的所有 autoloader
- 如果 autoloader 也找不到，PHP 会回退到 `include(类名)` 并抛出：
  ```
  ErrorException: include(OAuth2\Storage\AccessTokenInterface.php):
                  failed to open stream: No such file or directory
  ```

**两个会让 autoloader 失效的场景**：

1. **autoloader 还没注册**（helper/server/Server 还没被实例化时）
2. **autoloader 注册了但 file_exists() 返回 false**（Linux PHP-FPM/CLI/Cron 进程 cwd 不在 webroot 时，相对路径 `lib/...` 找不到）

**修复**（已在 `Model/Storage/AccessToken.php` 等 4 个 storage 文件中实施）：在文件**顶部**（class 声明之前）显式 `require_once` 父接口文件，**让 PHP 编译类声明时直接从 Zend 内存取接口，绕过 autoloader 链**：

```php
// app/code/community/XFE/OAuth2/Model/Storage/AccessToken.php
require_once BP . '/lib/XFE/OAuth2/Autoloader.php';
XFE_OAuth2_Autoloader::register();
require_once BP . '/lib/OAuth2/Storage/AccessTokenInterface.php';

class XFE_OAuth2_Model_Storage_AccessToken implements OAuth2\Storage\AccessTokenInterface
{
    // ...
}
```

#### 4 个 storage 文件的接口 require_once 一览

| 文件 | implements 的接口 | 显式 require 的接口文件 |
|------|------------------|----------------------|
| `Model/Storage/AccessToken.php` | `OAuth2\Storage\AccessTokenInterface` | `lib/OAuth2/Storage/AccessTokenInterface.php` |
| `Model/Storage/ClientCredentials.php` | `OAuth2\Storage\ClientCredentialsInterface` + `OAuth2\Storage\ScopeInterface` | `ClientCredentialsInterface.php` + `ScopeInterface.php` |
| `Model/Storage/RefreshToken.php` | `OAuth2\Storage\RefreshTokenInterface` | `RefreshTokenInterface.php` |
| `Model/Storage/AuthorizationCode.php` | `OAuth2\Storage\AuthorizationCodeInterface` | `AuthorizationCodeInterface.php` |

> **铁律**：任何 `implements OAuth2\...` 或 `extends OAuth2\...` 的类文件，**必须**在 class 声明之前 `require_once` 全部引用的接口 / 父类。**不能**依赖 autoloader 一定能找到（即使 autoloader 路径已修对，class 声明本身的 resolve 顺序比 autoloader 注册更早）。

## 已弃方案

### 方案 1：去掉 `implements`，改用 `__construct` 软检查

```php
// ❌ 已弃
class XFE_OAuth2_Model_Storage_AccessToken
{
    public function __construct()
    {
        if (class_exists('OAuth2\\Storage\\AccessTokenInterface', false)) {
            if (!($this instanceof \OAuth2\Storage\AccessTokenInterface)) {
                throw new RuntimeException(...);
            }
        }
    }
}
```

- **弃用原因**：bshaffer `OAuth2\Server` 构造时做 `instanceof OAuth2\Storage\ClientCredentialsInterface` 强校验（`lib/OAuth2/Server.php` 第 604 行起）。去 `implements` 后 `instanceof` 返回 false，Server 直接抛 `LogicException("You must supply a storage object implementing ...")`。
- **验证**：在 Windows 本地用 probe 脚本模拟过，去 `implements` 后 `instanceof` 立即返回 false，**与我们要解决的报错同等严重**。
- **回滚**：4 个 storage 类已回滚到 `implements` 写法。

### 方案 2：让 autoloader 在类加载时强制 require implements 的接口

- **弃用原因**：PHP 不暴露"类加载时机" hook。autoloader 只能响应"找不到类"，不能在 class 声明 resolve 接口前主动注入。

## 后果

- **正面**：
  - **Linux 服务器 PHP-FPM/CLI/Cron 进程 cwd 不在 webroot** 时，4 个 storage 类能正常被 Varien_Autoload 加载
  - **autoloader 注册时机不可控**（helper 还没被实例化）的场景下，4 个 storage 类不会因接口解析失败而崩
  - bshaffer `OAuth2\Server` 的 `instanceof` 强校验通过（保留 `implements`）
  - 现有业务逻辑（getter / setter）零修改
- **负面**：
  - 4 个 storage 类文件顶部多了 2-3 行 `require_once`，略微不优雅
  - 未来新增 `implements OAuth2\...` 的 storage 类时必须记得同步加 `require_once` 父接口（**通过代码审查把关**）
  - 未来新增 controller 入口时必须顶部加 `XFE_OAuth2_Autoloader::register()`（**通过代码审查把关**）

## 调试 / 复现

### 复现脚本（Linux 模拟非 webroot cwd）

```bash
# 在仓库根目录外运行（模拟 cwd != webroot）
cat > /tmp/repro_oauth2_storage.php <<'EOF'
<?php
define('DS', DIRECTORY_SEPARATOR);
define('BP', '/var/www/m1-test.com'); // 改成你的实际路径
chdir(sys_get_temp_dir()); // 关键：把 cwd 切到 /tmp
require_once BP . '/app/code/community/XFE/OAuth2/Model/Storage/AccessToken.php';
$obj = new XFE_OAuth2_Model_Storage_AccessToken();
var_dump($obj instanceof OAuth2\Storage\AccessTokenInterface);
EOF
php /tmp/repro_oauth2_storage.php
```

- **修复前**（去掉 BP 绝对路径 + 没顶部 require_once 接口）：第一行 `require_once` 报 `include(OAuth2\Storage\AccessTokenInterface.php) failed to open stream`
- **修复后**：输出 `bool(true)`

### 复现路径

| 修复 | 复现步骤 |
|------|---------|
| **autoloader 路径**（Linux cwd） | Linux 服务器 + PHP-FPM / CLI / Cron + `chdir(sys_get_temp_dir())` 后 require |
| **class 声明 resolve**（helper 还没实例化） | 在 helper 之前引用 storage 类的入口 + 不顶部 require_once autoloader |

## 受影响文件

| 路径 | 改动 | 类型 |
|------|------|------|
| `lib/XFE/OAuth2/Autoloader.php` | `$libDir` 从相对路径 `lib/` 改为 `BP . '/lib'`（绝对路径），附 `defined('BP')` 兜底 | 修复 |
| `app/code/community/XFE/OAuth2/Model/Storage/AccessToken.php` | 顶部追加 `require_once` autoloader + `require_once` `AccessTokenInterface.php` | 修复 |
| `app/code/community/XFE/OAuth2/Model/Storage/ClientCredentials.php` | 顶部追加 `require_once` autoloader + `require_once` `ClientCredentialsInterface.php` + `ScopeInterface.php` | 修复 |
| `app/code/community/XFE/OAuth2/Model/Storage/RefreshToken.php` | 顶部追加 `require_once` autoloader + `require_once` `RefreshTokenInterface.php` | 修复 |
| `app/code/community/XFE/OAuth2/Model/Storage/AuthorizationCode.php` | 顶部追加 `require_once` autoloader + `require_once` `AuthorizationCodeInterface.php` | 修复 |
| `app/code/community/XFE/OAuth2/Model/Grant/ClientCredentials.php` | 顶部追加 `require_once` autoloader + `register()`（父类 `OAuth2\GrantType\ClientCredentials` 必须先 resolve） | 修复 |
| `app/code/community/XFE/OAuth2/controllers/ClientController.php` | 顶部追加 `require_once` autoloader + `register()`（防御性兜底） | 修复 |
| `app/code/community/XFE/OAuth2/controllers/Adminhtml/Xfeoauth2/ClientController.php` | 同上 | 修复 |
| `app/code/community/XFE/OAuth2/controllers/ApiController.php` | 同上 | 修复 |
| `app/code/community/XFE/OAuth2/controllers/LoginController.php` | 同上 | 修复 |

## 验证步骤

1. `find app/code/community/XFE/OAuth2/Model/Storage/ -name "*.php" -exec php -l {} \;` 全部通过
2. 模拟 Linux 非 webroot cwd：直接 `require_once` 任一 storage 类 + `new` 一个对象 + `instanceof` 检查父接口，**全部返回 true**
3. 在真实 Magento 1 环境中访问 `/oauth2/client/index` 正常渲染
4. `php -l` 全部改动文件通过

## 修订记录

| 日期 | 变更 |
|------|------|
| 2026-09-09 | 初版：根因分析（Linux cwd + autoloader 注册时机）+ 4 处 storage 类顶部 require_once 父接口 + autoloader 改绝对路径 + 4 个 controller 入口补 register + 已弃方案说明 |
