<?php

/**
 * GLS Web API 常量集中定义。
 *
 * 属于 L1 Domain 层，纯 PHP，不继承 Mage_*，无外部依赖。
 * 业务阈值、Header、MIME、资源名、错误码等统一在此维护，避免魔法字符串散落。
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
     * 用于根据 detectMimeType() 的结果生成保存文件的扩展名。
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

    /** @var string GLS POD 资源名(协议固定值,不可配置,ADR 0021) */
    const RESOURCE_PARCELPOD = 'parcelpod';

    /** @var int cURL 超时（秒） */
    const CURL_TIMEOUT_SECONDS = 30;

    /** @var int 成功创建状态码 */
    const HTTP_CREATED = 201;

    /** @var int 成功 OK 状态码 */
    const HTTP_OK = 200;

    /**
     * GLS 错误响应 header 名（按实测响应规范定义，2026-09-21 确认）。
     *
     * 失败响应 body 为空，错误信息全部在 header 里。
     * 本模块 _httpRequest 用 strtolower 归一化为小写键存储，
     * 但本常量保留 GLS 原始大小写，供文档 / 日志 / 排障参考。
     *
     * @var string
     */
    const RESPONSE_HEADER_ERROR = 'Error';

    /** @var string 错误响应 header：人类可读英文 */
    const RESPONSE_HEADER_MESSAGE = 'Message';

    /** @var string 错误响应 header：参数 JSON 数组 */
    const RESPONSE_HEADER_ARGS = 'Args';

    /** @var string 错误响应 header：GLS 内部耗时（推测 ms） */
    const RESPONSE_HEADER_SERVER_EXECUTION_TIME = 'ServerExecutionTime';

    /**
     * GLS 业务错误码枚举（已实测确认一项，完整枚举待 GLS B1 答复）。
     *
     * "Error" header 的值是机器可读枚举码，业务判定必须基于它，
     * 不得依赖 "Message" header 的自然语言匹配（文案可能变化或本地化）。
     *
     * @var string
     */
    const ERROR_CODE_NO_POD_IMAGE_FOUND = 'NO_POD_IMAGE_FOUND';

    /**
     * "PoD 暂不可用"业务错误码白名单（当前仅 NO_POD_IMAGE_FOUND）。
     *
     * 业务规则：当 GlsApiException::getErrorCode() 命中此白名单时，
     * PodService 应抛 XFE_Logistic_Domain_Exception_PoDNotAvailableException
     * 而非原 GlsApiException。
     *
     * 维护纪律：取得 GLS 完整 Error 枚举后**仅追加**，不改既有项。
     *
     * 本常量只能被 L3 Service 引用；L2 GlsGateway 不得使用
     * （提交前自检：grep POD_NOT_AVAILABLE_ERROR_CODES GlsGateway.php 应 0 命中）。
     *
     * @var array<int, string>
     */
    const POD_NOT_AVAILABLE_ERROR_CODES = array(
        self::ERROR_CODE_NO_POD_IMAGE_FOUND,
    );
}