<?php

/**
 * XFE_Injection_Domain_ServiceDefinition
 *
 * 服务的纯数据定义(L1):由哪个类、哪个方法提供。
 *
 * 不可变值对象。
 * 关联文档:docs/architecture/injection-api.md §2.4
 */
final class XFE_Injection_Domain_ServiceDefinition
{
    /** @var string */
    private $_serviceId;

    /** @var string */
    private $_className;

    /** @var string */
    private $_methodName;

    /** @var string 提供该 service 的模块名(可空) */
    private $_module;

    /**
     * @param string $serviceId  业务字符串标识(全站唯一)
     * @param string $className  提供服务的 PHP 类(完整类名)
     * @param string $methodName 该服务对外的方法名
     * @param string $module     提供该 service 的模块名
     */
    public function __construct($serviceId, $className, $methodName, $module = '')
    {
        if (!is_string($serviceId) || $serviceId === '') {
            throw new InvalidArgumentException('ServiceDefinition: serviceId must be non-empty string');
        }
        if (!is_string($className) || $className === '') {
            throw new InvalidArgumentException('ServiceDefinition: className must be non-empty string');
        }
        if (!is_string($methodName) || $methodName === '') {
            throw new InvalidArgumentException('ServiceDefinition: methodName must be non-empty string');
        }
        $this->_serviceId  = $serviceId;
        $this->_className  = $className;
        $this->_methodName = $methodName;
        $this->_module     = (string) $module;
    }

    public function getId()
    {
        return $this->_serviceId;
    }

    public function getClassName()
    {
        return $this->_className;
    }

    public function getMethodName()
    {
        return $this->_methodName;
    }

    public function getModule()
    {
        return $this->_module;
    }

    public function withModule($module)
    {
        return new self($this->_serviceId, $this->_className, $this->_methodName, (string) $module);
    }

    public function __toString()
    {
        return 'ServiceDefinition[' . $this->_serviceId . ' = ' . $this->_className . '::' . $this->_methodName . ']';
    }
}
