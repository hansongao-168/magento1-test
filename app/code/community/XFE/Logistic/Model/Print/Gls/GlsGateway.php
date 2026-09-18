<?php

/**
 * GLS Web API cURL 适配器（Gateway）。
 *
 * 属于 L2 Gateway 层：仅负责 HTTP 传输与响应解析，不含业务判断。
 * 对外暴露 POD 调用能力，供 L3 Service 编排使用。
 *
 * 位于 Print/Gls 目录：表示 GLS 打印（POD 面单/凭证）类网关。
 *
 * @category   Community
 * @package    XFE_Logistic
 */
class XFE_Logistic_Model_Print_Gls_GlsGateway
{
    /**
     * 调用 GLS Collect POD 端点。
     *
     * @param string $trackId GLS 运单号
     * @param string $url     POD 端点完整 URL
     * @param string $authHeader Basic Auth 头值（含 "Basic " 前缀）
     * @return array 解析后的 PODItem：{ TrackID, ImageData }
     * @throws Mage_Core_Exception 当 HTTP 失败或响应无法解析
     */
    public function requestParcelPod($trackId, $url, $authHeader)
    {
        $config = 'XFE_Logistic_Domain_Constant_GlsApiConfig';

        $headers = array(
            $config::HEADER_ACCEPT        => $config::ACCEPT_JSON,
            $config::HEADER_CONTENT_TYPE  => $config::CONTENT_TYPE_JSON,
            $config::HEADER_AUTHORIZATION => $authHeader,
        );

        $body = json_encode(array($config::FIELD_TRACK_ID => (string) $trackId));

        $response = $this->_httpRequest($url, $headers, $body);

        if ($response['error']) {
            Mage::throwException('GLS POD 请求失败: ' . $response['error']);
        }

        $httpCode = (int) $response['http_code'];
        $successCodes = array($config::HTTP_OK, $config::HTTP_CREATED);
        if (!in_array($httpCode, $successCodes)) {
            // GLS 错误消息位于响应 header，而非 body（见文档 Error Messages 章节）。
            // 抛带 HTTP 状态码的异常，供上层区分 400（请求错误）/5xx（服务端错误）。
            $glsMessage = $this->_extractGlsErrorMessage($response['headers']);
            $message = $glsMessage !== ''
                ? sprintf('GLS POD 请求失败（HTTP %s）: %s', $httpCode, $glsMessage)
                : sprintf('GLS POD 请求失败（HTTP %s）', $httpCode);

            throw new XFE_Logistic_Domain_Exception_GlsApiException($message, $httpCode);
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded) || !isset($decoded[$config::RESPONSE_POD_ITEM])) {
            Mage::throwException('GLS POD 响应结构无法解析');
        }

        return $decoded[$config::RESPONSE_POD_ITEM];
    }

    /**
     * 根据 base64 解码后的原始字节判断文件类型（MIME）。
     *
     * 通过文件头 magic bytes 识别常见格式：PDF、PNG、JPEG、GIF。
     * 无法识别为二进制图片/文档时，回退判断是否为 ZPL 打印指令文本；
     * 仍无法识别则返回 octet-stream。
     *
     * @param string $rawData base64 解码后的原始字节
     * @return string MIME 类型
     */
    public function detectMimeType($rawData)
    {
        $rawData = (string) $rawData;
        $config  = 'XFE_Logistic_Domain_Constant_GlsApiConfig';

        if ($rawData === '') {
            return $config::MIME_OCTET_STREAM;
        }

        $len = strlen($rawData);

        // PDF：%PDF
        if ($len >= 4 && substr($rawData, 0, 4) === '%PDF') {
            return $config::MIME_PDF;
        }

        // PNG：\x89PNG
        if ($len >= 4 && substr($rawData, 0, 4) === "\x89PNG") {
            return $config::MIME_PNG;
        }

        // JPEG：\xFF\xD8\xFF
        if ($len >= 3 && substr($rawData, 0, 3) === "\xFF\xD8\xFF") {
            return $config::MIME_JPEG;
        }

        // GIF：GIF87a / GIF89a
        if ($len >= 6 && substr($rawData, 0, 6) === 'GIF87a'
            || $len >= 6 && substr($rawData, 0, 6) === 'GIF89a'
        ) {
            return $config::MIME_GIF;
        }

        // ZPL：纯可打印 ASCII 文本，且含 Zebra 打印指令特征
        if ($this->_looksLikeZpl($rawData)) {
            return $config::MIME_ZPL;
        }

        return $config::MIME_OCTET_STREAM;
    }

    /**
     * 判断原始字节是否像 ZPL 打印指令文本。
     *
     * ZPL 是纯文本指令（通常以 ^XA 开头、^XZ 结尾，含 ^FS 等命令）。
     * 仅当整体为可打印 ASCII 且包含 ^ 开头的 ZPL 命令时判定为 ZPL。
     *
     * @param string $rawData
     * @return bool
     */
    protected function _looksLikeZpl($rawData)
    {
        if ($rawData === '') {
            return false;
        }

        // 前 64 字节内必须有 "^XA" 特征，且整体为可打印 ASCII
        $head = substr($rawData, 0, 64);
        if (strpos($head, '^XA') === false) {
            return false;
        }

        // 采样前 512 字节，若含有不可打印控制符（除换行/回车/制表）则判为二进制
        $sample = substr($rawData, 0, 512);
        $length = strlen($sample);
        for ($i = 0; $i < $length; $i++) {
            $ord = ord($sample[$i]);
            if ($ord < 32 && $ord !== 9 && $ord !== 10 && $ord !== 13) {
                return false;
            }
        }

        return true;
    }

    /**
     * 从 HTTP 响应 header 中提取 GLS 错误消息。
     *
     * GLS 规范：错误消息在响应 header 中。不同环境下具体字段名可能不同，
     * 这里探测常见错误字段，找不到则返回空字符串。
     *
     * @param array $headers header 名（小写）=> 值
     * @return string
     */
    protected function _extractGlsErrorMessage(array $headers)
    {
        $candidates = array(
            'message',
            'errormessage',
            'error',
            'description',
            'x-glserror',
        );

        foreach ($candidates as $key) {
            if (isset($headers[$key]) && trim($headers[$key]) !== '') {
                return (string) $headers[$key];
            }
        }

        return '';
    }

    /**
     * 执行 cURL POST 请求。
     *
     * @param string $url
     * @param array  $headers  header name => value
     * @param string $body
     * @return array Keys: http_code (int), body (string), headers (array), error (string|null)
     */
    protected function _httpRequest($url, array $headers, $body)
    {
        $curlHeaders = array();
        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }

        $responseHeaders = array();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, XFE_Logistic_Domain_Constant_GlsApiConfig::CURL_TIMEOUT_SECONDS);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $headerLine) use (&$responseHeaders) {
            $trimmed = trim($headerLine);
            if ($trimmed === '' || strpos($trimmed, ':') === false) {
                // 忽略空行与状态行（HTTP/1.1 200 OK）
                return strlen($headerLine);
            }

            list($name, $value) = explode(':', $trimmed, 2);
            $responseHeaders[strtolower(trim($name))] = trim($value);
            return strlen($headerLine);
        });

        $content  = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        return array(
            'http_code' => (int) $httpCode,
            'body'      => $content === false ? '' : $content,
            'headers'   => $responseHeaders,
            'error'     => $error ?: null,
        );
    }
}
