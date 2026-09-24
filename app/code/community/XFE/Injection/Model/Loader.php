<?php

/**
 * XFE_Injection_Model_Loader
 *
 * 启动期入口:扫描所有启用模块的 etc/injection.xml,合并到 Registry。
 *
 * 触发时机:
 *   - 第一次 XFE_Injection_Model_Runner::trigger() 被调用时(懒加载)
 *   - 或在 app/etc/modules/XFE_Injection.xml 之后通过 Observer 主动调用
 *
 * 关联文档:docs/architecture/injection-architecture.md §5.1
 */
final class XFE_Injection_Model_Loader
{
    /** @var bool 是否已加载(避免重复加载) */
    private static $_loaded = false;

    /**
     * 加载所有启用模块的 injection.xml。
     * 重复调用无副作用。
     *
     * @return XFE_Injection_Model_Registry
     */
    public static function loadAll()
    {
        if (self::$_loaded) {
            return XFE_Injection_Model_Registry::getInstance();
        }

        if (!class_exists('Mage', false)) {
            throw new RuntimeException('XFE_Injection_Model_Loader: Mage not initialized');
        }

        $modules = (array) Mage::getConfig()->getNode('modules');
        $merger  = new XFE_Injection_Model_Config_Merger();

        foreach ($modules as $moduleName => $moduleInfo) {
            /** @var SimpleXMLElement $moduleInfo */
            if ((string) $moduleInfo->active !== 'true') {
                continue;
            }
            $configPath = $this->_resolveConfigPath((string) $moduleName);
            if ($configPath === null) {
                continue;
            }
            if (!is_file($configPath)) {
                continue;
            }
            $content = file_get_contents($configPath);
            if ($content === false || trim($content) === '') {
                continue;
            }
            try {
                $merger->mergeFromXml((string) $moduleName, $content);
            } catch (Exception $e) {
                Mage::logException($e);
                // 严格模式下重新抛出;默认宽松模式只记日志
                if (Mage::getStoreConfigFlag('xfe_injection/general/strict_mode')) {
                    throw $e;
                }
            }
        }

        // 完整性校验
        $registry = XFE_Injection_Model_Registry::getInstance();
        $registry->validateIntegrity();

        // 冗余检测(只警告,不阻塞;真正的环由 Runner 运行时检测)
        $redundant = $registry->detectRedundantCallings();
        if (!empty($redundant) && class_exists('Mage', false)) {
            Mage::log(
                '[XFE_Injection] ' . count($redundant) . ' redundant calling pattern(s) detected: '
                . json_encode(array_map(function($r) {
                    return $r['hook'] . '->' . $r['service'] . ' (' . count($r['calling_ids']) . ' callings)';
                }, $redundant)),
                null,
                'system.log'
            );
        }

        // 调试日志
        if (Mage::getStoreConfigFlag('xfe_injection/general/debug')) {
            Mage::log(
                sprintf(
                    '[XFE_Injection] loaded: %d hooks, %d services, %d callings',
                    $registry->countHooks(),
                    $registry->countServices(),
                    $registry->countCallings()
                ),
                null,
                'system.log'
            );
        }

        self::$_loaded = true;
        return $registry;
    }

    /**
     * 单文件加载(测试用)。
     *
     * @param string $moduleName
     * @param string $xmlPath
     */
    public static function loadFile($moduleName, $xmlPath)
    {
        $merger = new XFE_Injection_Model_Config_Merger();
        $merger->mergeFromXml($moduleName, file_get_contents($xmlPath));
    }

    /**
     * 测试用:重置加载标记。
     */
    public static function resetForTesting()
    {
        self::$_loaded = false;
        XFE_Injection_Model_Registry::resetForTesting();
    }

    /**
     * 把模块名解析为 etc/injection.xml 绝对路径。
     *
     * 命名规则:
     *   XFE_Carrier        -> app/code/community/XFE/Carrier/etc/injection.xml
     *   Mage_Core          -> app/code/core/Mage/Core/etc/injection.xml
     *
     * @param string $moduleName
     * @return string|null
     */
    private static function _resolveConfigPath($moduleName)
    {
        $parts = explode('_', $moduleName, 2);
        if (count($parts) !== 2) {
            return null;
        }
        list($vendor, $module) = $parts;

        $candidates = array(
            Mage::getBaseDir('app') . DS . 'code' . DS . 'community' . DS . $vendor . DS . $module . DS . 'etc' . DS . 'injection.xml',
            Mage::getBaseDir('app') . DS . 'code' . DS . 'core' . DS . $vendor . DS . $module . DS . 'etc' . DS . 'injection.xml',
            Mage::getBaseDir('app') . DS . 'code' . DS . 'local' . DS . $vendor . DS . $module . DS . 'etc' . DS . 'injection.xml',
        );

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }
}
