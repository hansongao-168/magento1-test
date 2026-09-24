<?php

/**
 * GLS Proof of Delivery 业务用例（Service）。
 *
 * 属于 L3 Service 层：编排 Gateway + Domain，实现
 *  "输入 GLS 运单号 → 调用 GLS API → 解码 ImageData → 返回 POD 值对象"。
 *
 * 实现 {@see XFE_Logistic_Api_PodApiInterface}，上层（L4）可面向接口编程。
 *
 * @category   Community
 * @package    XFE_Logistic
 */
class XFE_Logistic_Service_PodService implements XFE_Logistic_Api_PodApiInterface
{
    /** @var XFE_Logistic_Model_Print_Gls_GlsGateway */
    protected $_gateway;

    /**
     * @param XFE_Logistic_Model_Print_Gls_GlsGateway|null $gateway
     */
    public function __construct(
        ?XFE_Logistic_Model_Print_Gls_GlsGateway $gateway = null
    ) {
        $this->_gateway = $gateway ?: Mage::getModel('xfe_logistic/print_gls_glsGateway');
    }

    /**
     * {@inheritdoc}
     */
    public function getProofOfDelivery($trackId)
    {
        $trackId = trim((string) $trackId);
        if ($trackId === '') {
            Mage::throwException('GLS 运单号（TrackID）不能为空');
        }

        $config = 'XFE_Logistic_Domain_Constant_GlsApiConfig';

        // 主流路径（ADR 0021 最终态）：通过 XML 注入从 XFE_Carrier 拿 GLS API 凭据。
        // 注入返 null = 配置缺失，硬失败提示 admin 立即排查（Carrier 后台需配 GLS 账号）。
        $credentials = $this->_resolveCredentialsViaInjection('gls', array());
        if (!is_array($credentials) || empty($credentials['endpoint_url'])) {
            Mage::throwException('GLS Carrier account not configured (injection returned null)');
        }

        $parcelPodUrl = rtrim((string)$credentials['endpoint_url'], '/')
            . '/' . $config::RESOURCE_PARCELPOD;
        $basicAuthUser = (string)(isset($credentials['username']) ? $credentials['username'] : '');
        $basicAuthPass = (string)(isset($credentials['password']) ? $credentials['password'] : '');
        $basicAuthHeader = $config::AUTH_SCHEME_BASIC . ' '
            . base64_encode($basicAuthUser . ':' . $basicAuthPass);

        // L2 GlsGateway 在非 2xx 时抛 GlsApiException，并携带 Error / Message header 信息；
        // 这里按 ADR 0028 在 L3 做业务分类：命中"PoD 不可用"白名单 → 转抛 PoDNotAvailableException。
        try {
            $podItem = $this->_gateway->requestParcelPod(
                $trackId,
                $parcelPodUrl,
                $basicAuthHeader
            );
        } catch (XFE_Logistic_Domain_Exception_GlsApiException $e) {
            $errorCode = $e->getErrorCode();
            if ($errorCode !== ''
                && in_array($errorCode, $config::POD_NOT_AVAILABLE_ERROR_CODES, true)
            ) {
                throw new XFE_Logistic_Domain_Exception_PoDNotAvailableException(
                    $trackId,
                    $errorCode,
                    $e->getRawMessage()
                );
            }
            throw $e;
        }

        $podTrackId = isset($podItem[$config::RESPONSE_TRACK_ID])
            ? (string) $podItem[$config::RESPONSE_TRACK_ID]
            : $trackId;

        $imageData = isset($podItem[$config::RESPONSE_IMAGE_DATA])
            ? (string) $podItem[$config::RESPONSE_IMAGE_DATA]
            : '';

        // ImageData 为 Base64 编码，解码后得到原始 POD 文件字节
        $raw = base64_decode($imageData);
        if ($raw === false || $raw === '') {
            Mage::throwException('GLS POD 返回的 ImageData 无效或为空');
        }

        // 依据解码后的文件头 magic bytes 动态判断文件类型（PDF/PNG/JPEG/ZPL 等）
        $mimeType = $this->_gateway->detectMimeType($raw);

        return new XFE_Logistic_Domain_PodResult(
            $podTrackId,
            $mimeType,
            $raw
        );
    }

    /**
     * 通过 XML 注入从 XFE_Carrier 拿 GLS API 完整凭据（array 形式）。
     *
     * 与 public resolveCredentialsViaInjection（只返 account_id）对比：
     * 本方法返完整凭据 array{username,password,endpoint_url,...}，
     * 供 getProofOfDelivery 主流路径直接组装 URL + Basic Auth。
     *
     * 调用链：
     *   _resolveCredentialsViaInjection('gls', $contextValues)
     *     -> XFE_Injection_Model_Runner::trigger('hook_logistic_before_request', $injCtx)
     *       -> 查找 calling_logistic_resolve_credentials
     *         -> service_carrier_get_credentials::getCredentialsByCarrier
     *           -> 返回 array 或 null
     *
     * 单向依赖：本方法依赖 XFE_Injection 公共模块 + XFE_Carrier adapter service，
     * 不出现 use XFE_Carrier_ 业务模块（与 public 旁路方法同基线）。
     *
     * @param string $carrierCode   承运商 code（gls / chronopost）
     * @param array  $contextValues 业务上下文 key=>value 集合
     * @return array|null 完整凭据 array；无 carrier 或无账号时返回 null
     */
    private function _resolveCredentialsViaInjection($carrierCode, array $contextValues)
    {
        $injectionContext = new XFE_Injection_Domain_InjectionContext(array(
            'carrierCode'   => (string) $carrierCode,
            'contextValues' => $contextValues,
        ));
        $result = XFE_Injection_Model_Runner::trigger(
            'hook_logistic_before_request',
            $injectionContext
        );
        $first = $result->first();
        return is_array($first) ? $first : null;
    }

    /**
     * 演示方法：通过 XML 注入方式解析 GLS 承运商账号。
     *
     * 这是 XFE_Injection 集成示例，与 getProofOfDelivery() 共存。
     * 未来 OAuth2 凭据迁移时，本方法将被合入主流路径，
     * 实现 Logistic 与 Carrier 的声明式解耦。
     *
     * 调用链：
     *   $this->resolveCredentialsViaInjection('gls', $context)
     *     -> XFE_Injection_Model_Runner::trigger('hook_logistic_before_request', $injCtx)
     *       -> 查找 calling_logistic_resolve_account
     *         -> service_carrier_resolve_account::resolveAccountId
     *           -> 返回 account_id
     *
     * 与 getProofOfDelivery 的关系：
     *   - getProofOfDelivery 仍走 Helper 直读 system config(旧路径)
     *   - 本方法走 XML 注入(新路径,与 Carrier 注入式解耦)
     *   - 两条路径并存,行为可对照
     *
     * 单向依赖：
     *   - 本方法依赖 XFE_Injection_Model_Runner(公共模块 L3)
     *   - 不直接依赖任何 XFE_Carrier_* 类(通过 XFE_Injection 公共模块注入,第二个参数为原生 array,见 ADR 0016)
     *
     * @param string $carrierCode  承运商 code,如 gls / chronopost
     * @param array  $contextValues 业务上下文 key=>value 集合
     * @return int|null 解析到的 carrier_account 主键;无 carrier 或无账号时返回 null
     */
    public function resolveCredentialsViaInjection($carrierCode, array $contextValues)
    {
        $injectionContext = new XFE_Injection_Domain_InjectionContext(array(
            'carrierCode'   => (string) $carrierCode,
            'contextValues' => $contextValues,
        ));
        $result = XFE_Injection_Model_Runner::trigger(
            'hook_logistic_before_request',
            $injectionContext
        );
        return $result->first();
    }
}