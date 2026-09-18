<?php

/**
 * XFE_Injection_Domain_Exception_DuplicateServiceException
 *
 * 同一 serviceId 被多个模块的 injection.xml 重复声明时抛出。
 */
class XFE_Injection_Domain_Exception_DuplicateServiceException extends RuntimeException
{
    /** @var string */
    private $_serviceId;

    public function __construct($serviceId, $firstModule, $secondModule, $code = 0, ?Exception $previous = null)
    {
        $this->_serviceId = (string) $serviceId;
        parent::__construct(
            sprintf(
                'Duplicate injection service "%s": first by "%s", then by "%s".',
                $this->_serviceId,
                $firstModule,
                $secondModule
            ),
            $code,
            $previous
        );
    }

    public function getServiceId()
    {
        return $this->_serviceId;
    }
}
