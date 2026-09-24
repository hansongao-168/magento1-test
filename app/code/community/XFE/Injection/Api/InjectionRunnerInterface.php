<?php

/**
 * XFE_Injection_Api_InjectionRunnerInterface
 *
 * 业务模块对外契约(可选)。
 *
 * 本接口列出 Runner 提供的公共静态方法签名,业务模块可以 implements
 * 该接口或直接使用静态方法 XFE_Injection_Model_Runner::trigger / invoke。
 *
 * 关联文档:docs/architecture/injection-api.md §1
 */
interface XFE_Injection_Api_InjectionRunnerInterface
{
    /**
     * 触发一个 hook。
     *
     * @param string $hookName
     * @param XFE_Injection_Domain_InjectionContext $context
     * @param int|null $maxDepth
     * @return XFE_Injection_Domain_InjectionResult
     */
    public static function trigger(
        $hookName,
        XFE_Injection_Domain_InjectionContext $context,
        $maxDepth = null
    );

    /**
     * 直接调用一个 service。
     *
     * @param string $serviceId
     * @param string $methodName
     * @param array $args
     * @return mixed
     */
    public static function invoke($serviceId, $methodName = '', array $args = array());
}
