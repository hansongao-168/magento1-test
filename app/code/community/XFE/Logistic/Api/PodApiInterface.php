<?php

/**
 * GLS Proof of Delivery（POD）对外契约。
 *
 * 任何实现都必须遵循：
 *  - 输入 GLS 运单号（TrackID）
 *  - 返回 {@see XFE_Logistic_Domain_PodResult}（含解码后的 POD 文件字节）
 *  - 不暴露凭据、不写日志以外的副作用
 *
 * 上层（L4）只依赖此接口或 Service 别名，不直接 new 具体 Gateway。
 *
 * @category   Community
 * @package    XFE_Logistic
 */
interface XFE_Logistic_Api_PodApiInterface
{
    /**
     * 根据 GLS 运单号获取 Proof of Delivery。
     *
     * @param string $trackId GLS 运单号
     * @return XFE_Logistic_Domain_PodResult POD 值对象（含解码后的原始字节）
     * @throws XFE_Logistic_Domain_Exception_PoDNotAvailableException
     *         当 GLS 业务错误码命中"PoD 暂不可用"白名单（参见
     *         {@see XFE_Logistic_Domain_Constant_GlsApiConfig::POD_NOT_AVAILABLE_ERROR_CODES}，
     *         当前含 NO_POD_IMAGE_FOUND）时抛出，表示该运单暂无签收凭证
     *         （业务结论，非调用方错误，重试无益）
     * @throws XFE_Logistic_Domain_Exception_GlsApiException
     *         当 GLS 返回非 2xx 但未命中业务错误码白名单时抛出，
     *         可通过 getHttpCode() 判断 400/4xx（请求错误）/5xx（服务端错误），
     *         可通过 getErrorCode() 读取 GLS 机器可读错误码（可能为空），
     *         可通过 getRawMessage() 读取 GLS 人类可读原文
     * @throws Mage_Core_Exception 当运单号为空、网络失败或响应无法解析时
     */
    public function getProofOfDelivery($trackId);
}