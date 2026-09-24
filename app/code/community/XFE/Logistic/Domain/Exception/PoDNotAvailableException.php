<?php

/**
 * GLS PoD（Proof of Delivery）"暂不可用"业务异常。
 *
 * 业务语义：GLS 返回了"该运单暂无签收凭证"的业务结论（典型枚举码：
 * `NO_POD_IMAGE_FOUND`）。这是**业务事实**，不是端点配错，也不是调用方请求错误。
 *
 * 与 {@see XFE_Logistic_Domain_Exception_GlsApiException} 区别：
 *  - GlsApiException 表示"协议层调用失败"（HTTP 非 2xx），按 4xx/5xx 区分
 *    重试策略；命中"PoD 不可用"白名单（见 GlsApiConfig::POD_NOT_AVAILABLE_ERROR_CODES）
 *    时由 L3 PodService 转换为本异常。
 *  - 本异常专表达"运单本身正常，只是暂无签收凭证"，**不**继承 GlsApiException，
 *    避免被 `isClientError()` 误归类为"调用方请求错误"。
 *
 * 属于 L1 Domain 层，**不**继承 Mage_Core_Exception，保持 L1 不依赖 Magento 的边界。
 * 上层 L4 / UI 可直接渲染中文消息，无需判 HTTP 状态码。
 *
 * @category   Community
 * @package    XFE_Logistic
 */
final class XFE_Logistic_Domain_Exception_PoDNotAvailableException extends Exception
{
    /** @var string GLS 运单号 */
    private $_trackId;

    /** @var string GLS 机器可读错误码（如 NO_POD_IMAGE_FOUND） */
    private $_errorCode;

    /** @var string GLS 人类可读原文（Message header 值，未拼前缀） */
    private $_humanMessage;

    /**
     * 默认中文业务消息模板。
     *
     * @param string $trackId
     * @param string $errorCode
     * @return string
     */
    private static function _defaultMessage($trackId, $errorCode)
    {
        return sprintf(
            'GLS 运单 %s 暂无签收凭证（%s），请稍后再试或联系 GLS 客服。',
            $trackId,
            $errorCode
        );
    }

    /**
     * @param string $trackId      GLS 运单号
     * @param string $errorCode    GLS 机器可读错误码（如 NO_POD_IMAGE_FOUND）
     * @param string $humanMessage GLS 人类可读原文（Message header），默认空字符串
     */
    public function __construct($trackId, $errorCode, $humanMessage = '')
    {
        $this->_trackId      = (string) $trackId;
        $this->_errorCode    = (string) $errorCode;
        $this->_humanMessage = (string) $humanMessage;

        parent::__construct(self::_defaultMessage($this->_trackId, $this->_errorCode));
    }

    /**
     * GLS 运单号。
     *
     * @return string
     */
    public function getTrackId()
    {
        return $this->_trackId;
    }

    /**
     * GLS 机器可读错误码（如 NO_POD_IMAGE_FOUND）。
     *
     * @return string
     */
    public function getErrorCode()
    {
        return $this->_errorCode;
    }

    /**
     * GLS 人类可读原文（Message header 值）。
     *
     * 与 {@see getMessage()} 区别：后者是中文业务消息，前者是 GLS 原文（用于日志）。
     *
     * @return string
     */
    public function getHumanMessage()
    {
        return $this->_humanMessage;
    }
}