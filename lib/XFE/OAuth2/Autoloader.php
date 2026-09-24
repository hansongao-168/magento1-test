<?php
/**
 * XFE_OAuth2 - bundled library autoloader
 *
 * Registers PSR-4 autoloader for bshaffer/oauth2-server-php.
 * Called once when the module first initializes.
 *
 * 【历史 bug 修复 - 2026-09-09】
 * 原实现用 dirname(dirname(dirname(__FILE__))) 算出的相对路径 'lib/'，
 * 在 Linux PHP-FPM/CLI/Cron 进程 cwd 不是 webroot 时 file_exists() 永远 false，
 * 进而 PHP 退回到 include(类名) 时也找不到，触发：
 *   ErrorException: include(OAuth2\Storage\AccessTokenInterface.php):
 *                   failed to open stream: No such file or directory
 * 修复办法：使用 BP（仓库根绝对路径，由 app/Mage.php 在引导时定义），
 *          仅在 BP 未定义（如单元测试）时退回相对路径。
 *
 * 【2026-09-11 修复 autoloader 队列顺序】
 * Varien_Autoload::autoload()（lib/Varien/Autoload.php 第 81 行）对每个类都会
 * include($classFile) 兜底，而 OAuth2\*Interface 在 include_path 下找不到，
 * 每次 PHP 编译 OAuth2\Server 等类时都会先抛
 *   Warning: include(): Failed opening 'OAuth2\Controller\AuthorizeControllerInterface.php' for inclusion
 * 再交给下一个 autoloader 处理。修复：register() 用
 * spl_autoload_register($cb, true, true) 的 prepend=true，把本闭包放到队列首位。
 * 对非 OAuth2\ 命名空间类立刻 return，控制权回到 Varien_Autoload，行为不变。
 */
class XFE_OAuth2_Autoloader
{
    /**
     * @var bool
     */
    private static $_registered = false;

    /**
     * Register autoloaders for all bundled namespaces
     *
     * @return void
     */
    public static function register()
    {
        if (self::$_registered) {
            return;
        }
        self::$_registered = true;

        // 【必须用绝对路径】Linux 上 PHP-FPM/CLI/Cron 进程 cwd 不一定是
        // webroot，相对路径 'lib/...' 会导致 file_exists() 永远 false。
        // 优先用 BP（Magento 引导时定义，指向仓库根绝对路径），
        // 兜底用 __FILE__ 计算的相对路径（单元测试 / 独立 CLI 场景）。
        if (defined('BP')) {
            $libDir = BP . DS . 'lib';
        } else {
            $libDir = dirname(dirname(dirname(__FILE__)));
        }

        // bshaffer/oauth2-server-php namespace prefix 'OAuth2\\'
        //
        // spl_autoload_register 第 3 个参数 $prepend = true：
        //   把本闭包放到 SPL autoloader 队列首位。
        //   这样 OAuth2\*Interface 类引用时第一个命中本闭包，
        //   Varien_Autoload 不会再 include('OAuth2\...Interface.php') 失败 warning。
        // 第 2 个参数 $throw = true 在找不到类时抛 RuntimeException 而不是默默失败；
        //   与本闭包的语义自洽（本闭包只关心 OAuth2\ 命名空间，对其他类直接 return 即可）。
        spl_autoload_register(function ($class) use ($libDir) {
            if (strncmp('OAuth2\\', $class, 7) !== 0) {
                return;
            }

            $relativeClass = substr($class, 7);
            $file = $libDir
                  . DS . 'OAuth2'
                  . DS . str_replace('\\', DS, $relativeClass)
                  . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        }, true, true);
    }
}
