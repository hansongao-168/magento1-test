<?php

/**
 * XFE_Injection_Domain_CallingDefinition
 *
 * 调用关系的纯数据定义(L1):在哪个 hook 上调用哪个 service 的哪个方法,
 * 以及参数映射列表。
 *
 * 不可变值对象。
 * 关联文档:docs/architecture/injection-api.md §2.5
 */
final class XFE_Injection_Domain_CallingDefinition
{
    /** @var string 全站唯一 calling 标识(默认 hook_service_method) */
    private $_callingId;

    /** @var string hookId */
    private $_hookId;

    /** @var string serviceId */
    private $_serviceId;

    /** @var string 该 calling 实际要调用的方法(可与 ServiceDefinition 的 method 不同) */
    private $_methodName;

    /**
     * @var array 已展开的参数映射数组 [['name' => string, 'from' => string], ...]
     *   from 语法: 'context.<key>' | 'context' | 'literal:<value>' | 'null'
     */
    private $_arguments;

    /** @var string 声明该 calling 的模块名(可空) */
    private $_module;

    public function __construct(
        $callingId,
        $hookId,
        $serviceId,
        $methodName,
        array $arguments = array(),
        $module = ''
    ) {
        if (!is_string($callingId) || $callingId === '') {
            throw new InvalidArgumentException('CallingDefinition: callingId must be non-empty string');
        }
        if (!is_string($hookId) || $hookId === '') {
            throw new InvalidArgumentException('CallingDefinition: hookId must be non-empty string');
        }
        if (!is_string($serviceId) || $serviceId === '') {
            throw new InvalidArgumentException('CallingDefinition: serviceId must be non-empty string');
        }
        if (!is_string($methodName) || $methodName === '') {
            throw new InvalidArgumentException('CallingDefinition: methodName must be non-empty string');
        }
        foreach ($arguments as $i => $arg) {
            if (!isset($arg['name']) || !isset($arg['from'])) {
                throw new InvalidArgumentException("CallingDefinition[$callingId]: arg[$i] must have name+from");
            }
            if (!is_string($arg['name']) || $arg['name'] === '') {
                throw new InvalidArgumentException("CallingDefinition[$callingId]: arg[$i].name must be non-empty");
            }
            if (!is_string($arg['from'])) {
                throw new InvalidArgumentException("CallingDefinition[$callingId]: arg[$i].from must be string");
            }
        }

        $this->_callingId  = $callingId;
        $this->_hookId     = $hookId;
        $this->_serviceId  = $serviceId;
        $this->_methodName = $methodName;
        $this->_arguments  = array_values($arguments);
        $this->_module     = (string) $module;
    }

    public function getId()
    {
        return $this->_callingId;
    }

    public function getHookId()
    {
        return $this->_hookId;
    }

    public function getServiceId()
    {
        return $this->_serviceId;
    }

    public function getMethodName()
    {
        return $this->_methodName;
    }

    public function getArguments()
    {
        return $this->_arguments;
    }

    public function getModule()
    {
        return $this->_module;
    }

    public function withModule($module)
    {
        return new self(
            $this->_callingId,
            $this->_hookId,
            $this->_serviceId,
            $this->_methodName,
            $this->_arguments,
            (string) $module
        );
    }

    public function __toString()
    {
        return 'CallingDefinition[' . $this->_callingId . ': hook=' . $this->_hookId
            . ' svc=' . $this->_serviceId . '::' . $this->_methodName . ']';
    }
}
