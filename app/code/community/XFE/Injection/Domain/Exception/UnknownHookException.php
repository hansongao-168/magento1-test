<?php

/**
 * XFE_Injection_Domain_Exception_UnknownHookException
 *
 * 当 Runner::trigger() 收到一个未在 Registry 注册的 hook 时抛出。
 */
class XFE_Injection_Domain_Exception_UnknownHookException extends RuntimeException
{
    /** @var string */
    private $_hookId;

    public function __construct($hookId, $code = 0, ?Exception $previous = null)
    {
        $this->_hookId = (string) $hookId;
        parent::__construct(
            sprintf('Unknown injection hook: "%s". Declare it in etc/injection.xml first.', $this->_hookId),
            $code,
            $previous
        );
    }

    public function getHookId()
    {
        return $this->_hookId;
    }
}
