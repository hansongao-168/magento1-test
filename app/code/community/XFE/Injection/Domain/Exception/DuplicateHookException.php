<?php

/**
 * XFE_Injection_Domain_Exception_DuplicateHookException
 *
 * 同一 hookId 被多个模块的 injection.xml 重复声明时抛出。
 */
class XFE_Injection_Domain_Exception_DuplicateHookException extends RuntimeException
{
    /** @var string */
    private $_hookId;

    /** @var string */
    private $_firstModule;

    /** @var string */
    private $_secondModule;

    public function __construct($hookId, $firstModule, $secondModule, $code = 0, ?Exception $previous = null)
    {
        $this->_hookId        = (string) $hookId;
        $this->_firstModule   = (string) $firstModule;
        $this->_secondModule  = (string) $secondModule;
        parent::__construct(
            sprintf(
                'Duplicate injection hook "%s": first declared by "%s", then again by "%s".',
                $this->_hookId,
                $this->_firstModule,
                $this->_secondModule
            ),
            $code,
            $previous
        );
    }

    public function getHookId()
    {
        return $this->_hookId;
    }
}
