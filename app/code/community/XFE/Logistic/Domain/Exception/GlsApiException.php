<?php

/**
 * GLS API 调用异常。
 *
 * 携带 GLS HTTP 状态码 + 机器可读错误码，供上层区分处理：
 *  - 400/4xx：请求本身有问题（TrackID 无效、结构错误），重试无益，应提示用户修正。
 *  - 500/503：GLS 服务端异常，可稍后重试。
 *  - 命中"业务错误码白名单"（见 GlsApiConfig::POD_NOT_AVAILABLE_ERROR_CODES）时，
 *    由 L3 PodService 转换为专门的业务异常（如 PoDNotAvailableException）。
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
     * GLS 机器可读错误码（Error header 的值，如 NO_POD_IMAGE_FOUND）。
     *
     * 为空字符串表示响应未携带 Error header（L2 协议层提取不到）。
     *
     * @var string
     */
    protected $_errorCode;

    /**
     * GLS 人类可读原文（Message header 的值，未拼中文前缀）。
     *
     * 与 {@see getMessage()} 区别：后者是构造时拼好"中文前缀 + HTTP 状态码 + 原文"
     * 的版本，供最终用户展示；本字段保留 GLS 原文，便于日志与 L3 内部构造新异常。
     *
     * @var string
     */
    protected $_rawMessage;

    /**
     * @param string     $message   完整异常消息（已拼中文前缀 + HTTP 状态码 + 原文）
     * @param int        $httpCode  GLS HTTP 状态码
     * @param string     $errorCode GLS 机器可读错误码（Error header），默认空字符串
     * @param string     $rawMessage GLS 人类可读原文（Message header），默认空字符串
     * @param Exception  $previous
     */
    public function __construct(
        $message = '',
        $httpCode = 0,
        $errorCode = '',
        $rawMessage = '',
        ?Exception $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->_httpCode   = (int) $httpCode;
        $this->_errorCode  = (string) $errorCode;
        $this->_rawMessage = (string) $rawMessage;
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
     * GLS 机器可读错误码（Error header 的值）。
     *
     * 为空字符串表示响应未携带 Error header。
     *
     * @return string
     */
    public function getErrorCode()
    {
        return $this->_errorCode;
    }

    /**
     * GLS 人类可读原文（Message header 的值，未拼中文前缀）。
     *
     * 用于日志或 L3 Service 构造新业务异常时携带 GLS 原文。
     * 与 {@see getMessage()}（已拼前缀）语义不同。
     *
     * @return string
     */
    public function getRawMessage()
    {
        return $this->_rawMessage;
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