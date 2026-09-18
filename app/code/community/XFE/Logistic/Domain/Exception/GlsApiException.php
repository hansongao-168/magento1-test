<?php

/**
 * GLS API 调用异常。
 *
 * 携带 GLS HTTP 状态码，供上层区分处理：
 *  - 400/4xx：请求本身有问题（TrackID 无效、结构错误），重试无益，应提示用户修正。
 *  - 500/503：GLS 服务端异常，可稍后重试。
 *
 * 属于 L1 Domain 层，继承 Mage_Core_Exception 以便上层按 Magento 惯例捕获。
 *
 * @category   Community
 * @package    XFE_Logistic
 */
class XFE_Logistic_Domain_Exception_GlsApiException extends Mage_Core_Exception
{
    /** @var int HTTP 状态码 */
    protected $_httpCode;

    /**
     * @param string     $message
     * @param int        $httpCode
     * @param Exception  $previous
     */
    public function __construct($message = '', $httpCode = 0, ?Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->_httpCode = (int) $httpCode;
    }

    /**
     * GLS 返回的 HTTP 状态码。
     *
     * @return int
     */
    public function getHttpCode()
    {
        return $this->_httpCode;
    }

    /**
     * 是否为客户端请求错误（4xx），重试无益。
     *
     * @return bool
     */
    public function isClientError()
    {
        return $this->_httpCode >= 400 && $this->_httpCode < 500;
    }

    /**
     * 是否为服务端异常（5xx），可稍后重试。
     *
     * @return bool
     */
    public function isServerError()
    {
        return $this->_httpCode >= 500 && $this->_httpCode < 600;
    }
}
