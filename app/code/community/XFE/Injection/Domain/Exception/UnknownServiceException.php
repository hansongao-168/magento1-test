<?php

/**
 * XFE_Injection_Domain_Exception_UnknownServiceException
 *
 * 当 calling 引用的 serviceId 未注册时抛出。
 */
class XFE_Injection_Domain_Exception_UnknownServiceException extends RuntimeException
{
    /** @var string */
    private $_serviceId;

    public function __construct($serviceId, $code = 0, ?Exception $previous = null)
    {
        $this->_serviceId = (string) $serviceId;
        parent::__construct(
            sprintf('Unknown injection service: "%s". Declare it in etc/injection.xml first.', $this->_serviceId),
            $code,
            $previous
        );
    }

    public function getServiceId()
    {
        return $this->_serviceId;
    }
}
