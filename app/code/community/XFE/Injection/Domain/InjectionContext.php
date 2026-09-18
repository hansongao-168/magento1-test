<?php

/**
 * XFE_Injection_Domain_InjectionContext
 *
 * 调用上下文(L1):作为参数载体传入 Runner,被 calling 引用。
 *
 * 可变 Map 容器,实现 ArrayAccess + IteratorAggregate 便于:
 *   - \$context['carrierCode'] = 'gls';
 *   - foreach (\$context as \$key => \$value) { ... }
 *
 * 允许存储任意 PHP 值(数组、对象、字符串、null)。
 * 不做类型校验,所有值由 calling 的 from 表达式自行解析。
 *
 * 关联文档:docs/architecture/injection-api.md §2.1
 */
final class XFE_Injection_Domain_InjectionContext implements ArrayAccess, IteratorAggregate, Countable
{
    /** @var array<string,mixed> */
    private $_data;

    public function __construct(array $data = array())
    {
        $this->_data = $data;
    }

    /**
     * 取一个值,key 不存在时返回 \$default。
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return array_key_exists($key, $this->_data) ? $this->_data[$key] : $default;
    }

    /**
     * 设置一个值,返回 self 便于链式调用。
     *
     * @param string $key
     * @param mixed  $value
     * @return self
     */
    public function set($key, $value)
    {
        $this->_data[(string) $key] = $value;
        return $this;
    }

    public function has($key)
    {
        return array_key_exists((string) $key, $this->_data);
    }

    public function remove($key)
    {
        unset($this->_data[(string) $key]);
        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
    {
        return $this->_data;
    }

    // ----- ArrayAccess -----
    #[ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    #[ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    #[ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    // ----- IteratorAggregate -----
    #[ReturnTypeWillChange]
    public function getIterator()
    {
        return new ArrayIterator($this->_data);
    }

    // ----- Countable -----
    #[ReturnTypeWillChange]
    public function count()
    {
        return count($this->_data);
    }

    public function __toString()
    {
        return 'InjectionContext[' . count($this->_data) . ' keys]';
    }
}
