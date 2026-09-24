<?php

/**
 * XFE_Injection_Model_Registry
 *
 * 全局注入点 + 服务 + 调用关系注册表(单例)。
 *
 * 单向依赖(L3):
 *   Registry ─▶ Domain (HookDefinition / ServiceDefinition / CallingDefinition)
 *
 * 禁止:
 *   - 引用任何业务模块的类(XFE_Carrier / XFE_Logistic 等)
 *   - 解析 XML 或加载文件(职责归 Loader)
 *
 * 关联文档:docs/architecture/injection-api.md §3.2
 */
final class XFE_Injection_Model_Registry
{
    /** @var self|null */
    private static $_instance = null;

    /** @var array<string,XFE_Injection_Domain_HookDefinition> hookId => Definition */
    private $_hooks = array();

    /** @var array<string,XFE_Injection_Domain_ServiceDefinition> serviceId => Definition */
    private $_services = array();

    /** @var array<string,XFE_Injection_Domain_CallingDefinition> callingId => Definition */
    private $_callings = array();

    /** 启动顺序(供调试用): moduleName => int */
    private $_loadOrder = array();

    /**
     * 单例入口。
     */
    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * 测试用:重置整个注册表。
     */
    public static function resetForTesting()
    {
        self::$_instance = null;
    }

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    // ------------------------------------------------------------------
    // 注册(由 Loader/Merger 调用)
    // ------------------------------------------------------------------

    /**
     * @throws XFE_Injection_Domain_Exception_DuplicateHookException
     */
    public function registerHook(XFE_Injection_Domain_HookDefinition $hook)
    {
        $id = $hook->getId();
        if (isset($this->_hooks[$id])) {
            $firstModule = $this->_hooks[$id]->getModule();
            throw new XFE_Injection_Domain_Exception_DuplicateHookException(
                $id,
                $firstModule ?: '?',
                $hook->getModule() ?: '?'
            );
        }
        $this->_hooks[$id] = $hook;
        $this->_bumpLoadOrder($hook->getModule());
    }

    /**
     * @throws XFE_Injection_Domain_Exception_DuplicateServiceException
     */
    public function registerService(XFE_Injection_Domain_ServiceDefinition $svc)
    {
        $id = $svc->getId();
        if (isset($this->_services[$id])) {
            throw new XFE_Injection_Domain_Exception_DuplicateServiceException(
                $id,
                $this->_services[$id]->getModule() ?: '?',
                $svc->getModule() ?: '?'
            );
        }
        $this->_services[$id] = $svc;
        $this->_bumpLoadOrder($svc->getModule());
    }

    /**
     * callingId 重复时静默覆盖(允许消费方覆盖默认 calling)。
     */
    public function registerCalling(XFE_Injection_Domain_CallingDefinition $call)
    {
        $this->_callings[$call->getId()] = $call;
        $this->_bumpLoadOrder($call->getModule());
    }

    private function _bumpLoadOrder($module)
    {
        if (!$module) {
            return;
        }
        if (!isset($this->_loadOrder[$module])) {
            $this->_loadOrder[$module] = count($this->_loadOrder) + 1;
        }
    }

    // ------------------------------------------------------------------
    // 查询
    // ------------------------------------------------------------------

    public function hasHook($hookId)
    {
        return isset($this->_hooks[(string) $hookId]);
    }

    public function hasService($serviceId)
    {
        return isset($this->_services[(string) $serviceId]);
    }

    public function hasCalling($callingId)
    {
        return isset($this->_callings[(string) $callingId]);
    }

    public function getHook($hookId)
    {
        $id = (string) $hookId;
        if (!isset($this->_hooks[$id])) {
            throw new XFE_Injection_Domain_Exception_UnknownHookException($id);
        }
        return $this->_hooks[$id];
    }

    public function getService($serviceId)
    {
        $id = (string) $serviceId;
        if (!isset($this->_services[$id])) {
            throw new XFE_Injection_Domain_Exception_UnknownServiceException($id);
        }
        return $this->_services[$id];
    }

    public function getCalling($callingId)
    {
        $id = (string) $callingId;
        if (!isset($this->_callings[$id])) {
            throw new XFE_Injection_Domain_Exception_InvalidArgumentException(
                'Unknown calling: ' . $id
            );
        }
        return $this->_callings[$id];
    }

    /**
     * 取所有挂在某个 hook 上的 calling。
     *
     * @param string $hookId
     * @return XFE_Injection_Domain_CallingDefinition[]
     */
    public function getCallingsForHook($hookId)
    {
        $hookId = (string) $hookId;
        $result = array();
        foreach ($this->_callings as $call) {
            if ($call->getHookId() === $hookId) {
                $result[] = $call;
            }
        }
        return $result;
    }

    /**
     * @return XFE_Injection_Domain_HookDefinition[]
     */
    public function getAllHooks()
    {
        return array_values($this->_hooks);
    }

    /**
     * @return XFE_Injection_Domain_ServiceDefinition[]
     */
    public function getAllServices()
    {
        return array_values($this->_services);
    }

    /**
     * @return XFE_Injection_Domain_CallingDefinition[]
     */
    public function getAllCallings()
    {
        return array_values($this->_callings);
    }

    public function countHooks()
    {
        return count($this->_hooks);
    }

    public function countServices()
    {
        return count($this->_services);
    }

    public function countCallings()
    {
        return count($this->_callings);
    }

    /**
     * @return array<string,int>
     */
    public function getLoadOrder()
    {
        return $this->_loadOrder;
    }

    // ------------------------------------------------------------------
    // 完整性校验
    // ------------------------------------------------------------------

    /**
     * 校验所有 calling 引用的 hook 和 service 都被声明。
     * 任何未声明的引用抛出对应异常。
     *
     * @throws XFE_Injection_Domain_Exception_UnknownHookException
     * @throws XFE_Injection_Domain_Exception_UnknownServiceException
     */
    public function validateIntegrity()
    {
        foreach ($this->_callings as $call) {
            if (!isset($this->_hooks[$call->getHookId()])) {
                throw new XFE_Injection_Domain_Exception_UnknownHookException($call->getHookId());
            }
            if (!isset($this->_services[$call->getServiceId()])) {
                throw new XFE_Injection_Domain_Exception_UnknownServiceException($call->getServiceId());
            }
        }
    }

    /**
     * 检测显式的"同 hook 上重复调用同一 service"冗余模式。
     *
     * 这是配置级的最常见错误模式:同一个 hook 上挂了多个 calling,
     * 而这些 calling 又引用了同一个 service,触发顺序不定。
     *
     * 注意:本方法**不**做真正的运行时环检测(那是 Runner 的职责,需要跟踪 trigger 栈)。
     *
     * @return array<int,array{hook:string,service:string,calling_ids:string[]}>
     */
    public function detectRedundantCallings()
    {
        $hookToService = array();  // "hook|service" => [callingId,...]
        foreach ($this->_callings as $cid => $call) {
            $key = $call->getHookId() . '|' . $call->getServiceId();
            if (!isset($hookToService[$key])) {
                $hookToService[$key] = array();
            }
            $hookToService[$key][] = $cid;
        }

        $redundant = array();
        foreach ($hookToService as $key => $cids) {
            if (count($cids) <= 1) {
                continue;
            }
            list($hook, $service) = explode('|', $key, 2);
            $redundant[] = array(
                'hook'        => $hook,
                'service'     => $service,
                'calling_ids' => $cids,
            );
        }

        return $redundant;
    }
}
