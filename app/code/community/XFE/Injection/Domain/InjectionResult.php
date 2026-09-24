<?php

/**
 * XFE_Injection_Domain_InjectionResult
 *
 * 调用结果容器(L1):Runner::trigger() 返回值。
 *
 * 每个 calling 的返回值通过 callingId 索引。
 *
 * 关联文档:docs/architecture/injection-api.md §2.2
 */
final class XFE_Injection_Domain_InjectionResult implements ArrayAccess, IteratorAggregate, Countable
{
    /** @var array<string,mixed> */
    private $_data;

    public function __construct(array $data = array())
    {
        $this->_data = $data;
    }

    /**
     * 装载某个 calling 的返回值。
     *
     * @param string $callingId
     * @param mixed  $value
     * @return self
     */
    public function set($callingId, $value)
    {
        $this->_data[(string) $callingId] = $value;
        return $this;
    }

    /**
     * 取某个 calling 的返回值。
     *
     * @param string $callingId
     * @param mixed  $default
     * @return mixed
     */
    public function get($callingId, $default = null)
    {
        return array_key_exists($callingId, $this->_data) ? $this->_data[$callingId] : $default;
    }

    public function has($callingId)
    {
        return array_key_exists((string) $callingId, $this->_data);
    }

    /**
     * 取第一个非 null 的返回值。
     * 约定:单 calling 场景下,调用方直接 first() 拿到预期结果。
     *
     * @return mixed
     */
    public function first()
    {
        foreach ($this->_data as $value) {
            if ($value !== null) {
                return $value;
            }
        }
        return null;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
    {
        return $this->_data;
    }

    public function isEmpty()
    {
        return empty($this->_data);
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
        unset($this->_data[(string) $offset]);
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
        return 'InjectionResult[' . count($this->_data) . ' entries]';
    }
}
