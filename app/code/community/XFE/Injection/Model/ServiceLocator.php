<?php

/**
 * XFE_Injection_Model_ServiceLocator
 *
 * 按 ServiceDefinition 解析出可调用的对象实例。
 *
 * 解析策略:
 *   1. 检查 override 缓存(单测 / 运行时 mock)
 *   2. 检查实例缓存(避免重复 new)
 *   3. 通过 Magento factory 尝试 \$className;若失败直接 new
 *   4. 缓存并返回
 *
 * 关联文档:docs/architecture/injection-api.md §3.4
 */
final class XFE_Injection_Model_ServiceLocator
{
    /** @var array<string,object> serviceId => instance */
    private $_instances = array();

    /** @var array<string,object> serviceId => override(测试用) */
    private $_overrides = array();

    /**
     * 给定 ServiceDefinition,返回一个可调用对象。
     *
     * @param XFE_Injection_Domain_ServiceDefinition $svc
     * @return object
     */
    public function resolve(XFE_Injection_Domain_ServiceDefinition $svc)
    {
        $id = $svc->getId();

        if (isset($this->_overrides[$id])) {
            return $this->_overrides[$id];
        }
        if (isset($this->_instances[$id])) {
            return $this->_instances[$id];
        }

        $className = $svc->getClassName();
        $instance  = $this->_instantiate($className);
        $this->_instances[$id] = $instance;
        return $instance;
    }

    /**
     * 实例化策略:
     *   1. 类已存在(class_exists) → 直接 new \$className()
     *   2. 类名形如 foo/bar → 当作 Magento model alias, Mage::getModel('foo/bar')
     *   3. 兜底 new \$className()(上面已覆盖)
     *
     * @param string $className
     * @return object
     */
    private function _instantiate($className)
    {
        if (class_exists($className)) {
            return new $className();
        }

        // 兼容 Magento model alias (含 '/')
        if (strpos($className, '/') !== false && class_exists('Mage', false)) {
            try {
                $obj = Mage::getModel($className);
                if ($obj !== false) {
                    return $obj;
                }
            } catch (Exception $e) {
                // 忽略,继续尝试下方逻辑
            }
        }

        throw new RuntimeException(
            sprintf('XFE_Injection: cannot instantiate service class "%s"', $className)
        );
    }

    /**
     * 测试用:替换某个 service 的实例。
     *
     * @param string $serviceId
     * @param object $instance
     */
    public function setOverride($serviceId, $instance)
    {
        $this->_overrides[(string) $serviceId] = $instance;
    }

    /**
     * 测试用:清除某个 service 的 override。
     */
    public function clearOverride($serviceId)
    {
        unset($this->_overrides[(string) $serviceId]);
    }

    /**
     * 测试用:清除所有 override。
     */
    public function clearAllOverrides()
    {
        $this->_overrides = array();
    }

    /**
     * 测试用:清空实例缓存(强制下次 resolve 时重新 new)。
     */
    public function clearInstanceCache()
    {
        $this->_instances = array();
    }

    /**
     * 测试用:重置整个 locator。
     */
    public function reset()
    {
        $this->_instances = array();
        $this->_overrides = array();
    }
}
