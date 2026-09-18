<?php

/**
 * Proof of Delivery 值对象。
 *
 * 属于 L1 Domain 层，纯 PHP，不继承 Mage_*。
 * 承载 GLS POD 返回的不可变数据：运单号 + 解码后的原始 POD 文件字节。
 *
 * @category   Community
 * @package    XFE_Logistic
 */
final class XFE_Logistic_Domain_PodResult
{
    /** @var string GLS 运单号 */
    private $_trackId;

    /** @var string POD 文件 MIME 类型 */
    private $_mimeType;

    /** @var string 解码后的 POD 原始字节 */
    private $_rawData;

    /**
     * @param string $trackId  GLS 运单号
     * @param string $mimeType 文件 MIME 类型
     * @param string $rawData  解码后的原始文件字节
     */
    public function __construct($trackId, $mimeType, $rawData)
    {
        $this->_trackId  = (string) $trackId;
        $this->_mimeType = (string) $mimeType;
        $this->_rawData  = (string) $rawData;
    }

    /**
     * @return string
     */
    public function getTrackId()
    {
        return $this->_trackId;
    }

    /**
     * @return string
     */
    public function getMimeType()
    {
        return $this->_mimeType;
    }

    /**
     * @return string
     */
    public function getRawData()
    {
        return $this->_rawData;
    }

    /**
     * 根据 MIME 类型返回保存文件的扩展名（小写，不含点）。
     *
     * 未识别的 MIME 回退为 'bin'。
     *
     * @return string
     */
    public function getExtension()
    {
        $map = XFE_Logistic_Domain_Constant_GlsApiConfig::$mimeToExtension;
        return isset($map[$this->_mimeType]) ? $map[$this->_mimeType] : 'bin';
    }

    /**
     * 结构化数组（供日志/序列化用，不暴露凭据）。
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'track_id'   => $this->_trackId,
            'mime_type'  => $this->_mimeType,
            'extension'  => $this->getExtension(),
            'raw_data'   => $this->_rawData,
        );
    }
}
