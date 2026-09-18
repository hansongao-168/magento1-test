<?php

/**
 * XFE_Injection_Domain_HookDefinition
 *
 * 触发点的纯数据定义(L1)。
 *
 * 不可变值对象,所有字段在构造时设定。
 * 不持有任何外部资源或引用,允许被序列化(单测用)。
 */
final class XFE_Injection_Domain_HookDefinition
{
    /** @var string */
    private $_hookId;

    /** @var string */
    private $_description;

    /** @var string 提供该 hook 的模块名(可空,运行时由 Merger 注入) */
    private $_module;

    public function __construct($hookId, $description = '', $module = '')
    {
        if (!is_string($hookId) || $hookId === '') {
            throw new InvalidArgumentException('HookDefinition: hookId must be non-empty string');
        }
        $this->_hookId      = $hookId;
        $this->_description = (string) $description;
        $this->_module      = (string) $module;
    }

    public function getId()
    {
        return $this->_hookId;
    }

    public function getDescription()
    {
        return $this->_description;
    }

    public function getModule()
    {
        return $this->_module;
    }

    public function withModule($module)
    {
        return new self($this->_hookId, $this->_description, (string) $module);
    }

    public function __toString()
    {
        return 'HookDefinition[' . $this->_hookId . ' @ ' . ($this->_module ?: '?') . ']';
    }
}
