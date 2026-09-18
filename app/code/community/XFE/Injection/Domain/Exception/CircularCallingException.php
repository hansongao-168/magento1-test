<?php

/**
 * XFE_Injection_Domain_Exception_CircularCallingException
 *
 * calling 链形成环(A→B→A)时由 Registry::detectCircularCallings() 抛出。
 * 也可由 Runner 在运行时检测到过深嵌套时抛出。
 */
class XFE_Injection_Domain_Exception_CircularCallingException extends RuntimeException
{
    /** @var array<string> 调用链上的 hookId */
    private $_chain;

    public function __construct(array $chain, $code = 0, ?Exception $previous = null)
    {
        $this->_chain = array_values($chain);
        parent::__construct(
            'Circular injection calling detected: ' . implode(' -> ', $this->_chain),
            $code,
            $previous
        );
    }

    public function getChain()
    {
        return $this->_chain;
    }
}
