<?php

/**
 * GLS Web API 常量集中定义。
 *
 * 属于 L1 Domain 层，纯 PHP，不继承 Mage_*，无外部依赖。
 * 业务阈值、Header、MIME、资源名等统一在此维护，避免魔法字符串散落。
 *
 * @category   Community
 * @package    XFE_Logistic
 */
final class XFE_Logistic_Domain_Constant_GlsApiConfig
{
    /** @var string Basic Auth 请求头 */
    const HEADER_AUTHORIZATION = 'Authorization';

    /** @var string Accept 请求头 */
    const HEADER_ACCEPT = 'Accept';

    /** @var string Content-Type 请求头 */
    const HEADER_CONTENT_TYPE = 'Content-Type';

    /** @var string Accept 头值（GLS 版本化 JSON） */
    const ACCEPT_JSON = 'application/glsVersion1+json, application/json';

    /** @var string Content-Type 头值 */
    const CONTENT_TYPE_JSON = 'application/glsVersion1+json';

    /** @var string HTTP Basic 认证前缀 */
    const AUTH_SCHEME_BASIC = 'Basic';

    /** @var string 请求体字段：运单号 */
    const FIELD_TRACK_ID = 'TrackID';

    /** @var string 响应顶层节点 */
    const RESPONSE_POD_ITEM = 'PODItem';

    /** @var string 响应 POD 项字段：运单号 */
    const RESPONSE_TRACK_ID = 'TrackID';

    /** @var string 响应 POD 项字段：Base64 图片数据 */
    const RESPONSE_IMAGE_DATA = 'ImageData';

    /** @var string 文件 MIME 类型（PDF） */
    const MIME_PDF = 'application/pdf';

    /** @var string 文件 MIME 类型（PNG 图片） */
    const MIME_PNG = 'image/png';

    /** @var string 文件 MIME 类型（JPEG 图片） */
    const MIME_JPEG = 'image/jpeg';

    /** @var string 文件 MIME 类型（GIF 图片） */
    const MIME_GIF = 'image/gif';

    /** @var string 文件 MIME 类型（ZPL 标签） */
    const MIME_ZPL = 'application/x-zpl';

    /** @var string 文件 MIME 类型（未知二进制） */
    const MIME_OCTET_STREAM = 'application/octet-stream';

    /**
     * MIME 类型 => 文件后缀（小写，不含点）。
     *
     * 用于根据 detectMimeType() 结果生成保存文件的扩展名。
     *
     * @var array
     */
    public static $mimeToExtension = array(
        self::MIME_PDF          => 'pdf',
        self::MIME_PNG          => 'png',
        self::MIME_JPEG         => 'jpg',
        self::MIME_GIF          => 'gif',
        self::MIME_ZPL          => 'zpl',
        self::MIME_OCTET_STREAM => 'bin',
    );

    /** @var int cURL 超时（秒） */
    const CURL_TIMEOUT_SECONDS = 30;

    /** @var int 成功创建状态码 */
    const HTTP_CREATED = 201;

    /** @var int 成功 OK 状态码 */
    const HTTP_OK = 200;
}
