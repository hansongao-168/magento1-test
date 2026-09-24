<?php

/**
 * XFE_Injection_Model_Runner
 *
 * 运行时调度入口(L3)。
 *
 * 静态方法:
 *   - trigger(hookName, context):触发一个 hook,执行所有挂载 calling,聚合结果
 *   - invoke(serviceId, method, args):直接调用一个 service 的方法
 *
 * 单向依赖:
 *   Runner ─▶ Registry / Loader / ServiceLocator / Domain
 *
 * 禁止:
 *   - 任何业务模块反向依赖 Runner 内部细节
 *   - Runner 直接调用业务模块 Service 之外的方法
 *
 * 关联文档:docs/architecture/injection-api.md §3.1
 */
final class XFE_Injection_Model_Runner
{
    /** @var int 默认最大调用链深度(防 A→B→A 死循环) */
    const DEFAULT_MAX_DEPTH = 5;

    /**
     * 触发一个 hook。
     *
     * @param string $hookName
     * @param XFE_Injection_Domain_InjectionContext $context
     * @param int|null $maxDepth 自定义最大调用链深度,null 走配置
     * @return XFE_Injection_Domain_InjectionResult
     *
     * @throws XFE_Injection_Domain_Exception_UnknownHookException
     * @throws XFE_Injection_Domain_Exception_UnknownServiceException
     * @throws XFE_Injection_Domain_Exception_CircularCallingException
     */
    public static function trigger(
        $hookName,
        XFE_Injection_Domain_InjectionContext $context,
        $maxDepth = null
    ) {
        // 懒加载:首次 trigger 时才扫描所有 injection.xml
        XFE_Injection_Model_Loader::loadAll();

        $registry = XFE_Injection_Model_Registry::getInstance();
        $hookId   = (string) $hookName;

        if (!$registry->hasHook($hookId)) {
            throw new XFE_Injection_Domain_Exception_UnknownHookException($hookId);
        }

        $callings  = $registry->getCallingsForHook($hookId);
        $result    = new XFE_Injection_Domain_InjectionResult();
        $depth     = ($maxDepth === null) ? self::_resolveMaxDepth() : (int) $maxDepth;

        foreach ($callings as $call) {
            $value = self::_invokeOne($call, $context, $depth);
            $result->set($call->getId(), $value);
        }

        return $result;
    }

    /**
     * 直接调用一个 service 的方法(不经过 hook)。
     *
     * @param string $serviceId
     * @param string $methodName 留空走 ServiceDefinition::getMethodName
     * @param array  $args
     * @return mixed
     *
     * @throws XFE_Injection_Domain_Exception_UnknownServiceException
     */
    public static function invoke($serviceId, $methodName = '', array $args = array())
    {
        XFE_Injection_Model_Loader::loadAll();

        $registry = XFE_Injection_Model_Registry::getInstance();
        $svcId    = (string) $serviceId;

        if (!$registry->hasService($svcId)) {
            throw new XFE_Injection_Domain_Exception_UnknownServiceException($svcId);
        }

        $svc     = $registry->getService($svcId);
        $method  = ($methodName === '') ? $svc->getMethodName() : $methodName;
        $locator = new XFE_Injection_Model_ServiceLocator();
        $instance = $locator->resolve($svc);

        if (!method_exists($instance, $method)) {
            throw new RuntimeException(
                sprintf('XFE_Injection: service "%s" has no method "%s"', $svcId, $method)
            );
        }

        return call_user_func_array(array($instance, $method), $args);
    }

    /**
     * 测试用:重置所有缓存。
     */
    public static function resetForTesting()
    {
        XFE_Injection_Model_Loader::resetForTesting();
    }

    // ------------------------------------------------------------------
    // 内部
    // ------------------------------------------------------------------

    /**
     * 解析配置中的最大调用链深度。
     */
    private static function _resolveMaxDepth()
    {
        if (!class_exists('Mage', false)) {
            return self::DEFAULT_MAX_DEPTH;
        }
        try {
            $val = (int) Mage::getStoreConfig('xfe_injection/general/max_calling_depth');
            return $val > 0 ? $val : self::DEFAULT_MAX_DEPTH;
        } catch (Exception $e) {
            return self::DEFAULT_MAX_DEPTH;
        }
    }

    /**
     * 执行单个 calling:解析参数,定位服务,调用方法,返回结果。
     *
     * @param XFE_Injection_Domain_CallingDefinition $call
     * @param XFE_Injection_Domain_InjectionContext $context
     * @param int $maxDepth
     * @return mixed
     */
    private static function _invokeOne(
        XFE_Injection_Domain_CallingDefinition $call,
        XFE_Injection_Domain_InjectionContext $context,
        $maxDepth
    ) {
        if ($maxDepth <= 0) {
            throw new XFE_Injection_Domain_Exception_CircularCallingException(
                array($call->getHookId(), $call->getId())
            );
        }

        $registry = XFE_Injection_Model_Registry::getInstance();
        $svcId    = $call->getServiceId();
        if (!$registry->hasService($svcId)) {
            throw new XFE_Injection_Domain_Exception_UnknownServiceException($svcId);
        }
        $svc = $registry->getService($svcId);

        $locator  = new XFE_Injection_Model_ServiceLocator();
        $instance = $locator->resolve($svc);

        $method = $call->getMethodName();
        if (!method_exists($instance, $method)) {
            throw new RuntimeException(
                sprintf(
                    'XFE_Injection: calling "%s" references undefined method "%s::%s"',
                    $call->getId(),
                    $svc->getClassName(),
                    $method
                )
            );
        }

        // 参数解析
        $args = array();
        foreach ($call->getArguments() as $arg) {
            $args[] = self::_resolveArgument($arg, $context);
        }

        try {
            return call_user_func_array(array($instance, $method), $args);
        } catch (XFE_Injection_Domain_Exception_CircularCallingException $e) {
            throw $e;
        } catch (Exception $e) {
            if (class_exists('Mage', false)) {
                Mage::logException($e);
            }
            // 业务异常允许冒泡,但不在此处重新包装
            throw $e;
        }
    }

    /**
     * 解析单个参数(根据 from 表达式)。
     *
     * 支持:
     *   - context.<key>     :  context->get(key)
     *   - context           :  整个上下文对象
     *   - literal:<value>   :  字面量(字符串)
     *   - null              :  显式 null
     *
     * @param array $arg ['name' => ..., 'from' => ...]
     * @param XFE_Injection_Domain_InjectionContext $context
     * @return mixed
     */
    private static function _resolveArgument(array $arg, XFE_Injection_Domain_InjectionContext $context)
    {
        $from = $arg['from'];
        if ($from === 'null') {
            return null;
        }
        if ($from === 'context') {
            return $context;
        }
        if (strpos($from, 'literal:') === 0) {
            return substr($from, strlen('literal:'));
        }
        if (strpos($from, 'context.') === 0) {
            $key = substr($from, strlen('context.'));
            return $context->get($key);
        }
        throw new XFE_Injection_Domain_Exception_InvalidArgumentException(
            'Unsupported argument "from" expression: ' . $from
        );
    }
}
