<?php

/**
 * XFE_Carrier_Service_Account_CredentialViaInjection
 *
 * XML 注入的 service 适配器(L3):把现有的
 * XFE_Carrier_Model_Service_Rule_Resolver 包装为 XML 注入可识别的 service。
 *
 * 单一职责:
 *   - 接收 carrierCode(字符串) + MatchContext,返回 account_id(int|null)
 *   - 内部委托 Rule Resolver,不重复实现规则匹配逻辑
 *
 * 单向依赖:
 *   CredentialViaInjection ─▶  Carrier_Model (L2:Carrier / Resource_Carrier)
 *                             ─▶  Carrier_Service_Rule_Resolver (L3:已存在的 service)
 *                             ─▶  Carrier_Service_Rule_MatchContext (L1:DTO)
 *
 * 不依赖:
 *   - XFE_Injection(任何类):与 XFE_Injection 单向解耦
 *   - XFE_Logistic:与消费方单向解耦
 *
 * 关联文档:
 *   - docs/architecture/injection-carrier-logistic-integration.md §3.1
 *   - docs/architecture/decisions/0015-injection-carrier-logistic-integration.md
 */
class XFE_Carrier_Service_Account_CredentialViaInjection
{
    /**
     * 解析账号主键。
     *
     * XML 注入的入口方法,由 XFE_Injection_Model_ServiceLocator 通过
     *     new XFE_Carrier_Service_Account_CredentialViaInjection()
     * 实例化后调用。
     *
     * @param string $carrierCode  承运商 code,如 gls / chronopost
     * @param XFE_Carrier_Model_Service_Rule_MatchContext $context 业务上下文
     * @param bool   $fallback     无规则命中时是否退到最近可用账号,默认 true
     * @return int|null 解析到的 carrier_account 主键;无 carrier 或无账号时返回 null
     */
    /**
     * 解析账号主键。
     *
     * XML 注入的入口方法。第二个参数改为原生 array，
     * 实现 Logistic ↔ Carrier 零 PHP 类型耦合。
     *
     * @param string   $carrierCode
     * @param array    $contextValues 业务上下文 key=>value 集合
     *   支持的 key(传给 XFE_Carrier_Model_Service_Rule_MatchContext):
     *     country_code / city / zip_code / package_count / package_weight /
     *     length / width / height / volume / order_amount / customer_group /
     *     user_id / billing_country_code / billing_city / billing_region /
     *     billing_zip / order_created_at
     * @param bool     $fallback
     * @return int|null
     */
    public function resolveAccountId($carrierCode, array $contextValues, $fallback = true)
    {
        $carrierCode = trim((string) $carrierCode);
        if ($carrierCode === '') {
            return null;
        }

        $carrierId = $this->_resolveCarrierIdByCode($carrierCode);
        if (!$carrierId) {
            return null;
        }

        // 单一映射点:array → MatchContext
        // 未来如果要换公开 DTO 类型,只改这一处
        $matchContext = new XFE_Carrier_Model_Service_Rule_MatchContext($contextValues);

        // 委托给已存在的 Resolver(不重复实现规则匹配)
        $resolver = XFE_Carrier_Model_Service_Rule_Resolver::instance();
        $result   = $resolver->resolve((int) $carrierId, $matchContext, (bool) $fallback);

        return $result->getAccountId();
    }

    /**
     * 列出该 carrier_code 下的所有可用账号主键。
     *
     * 不走规则匹配, 直接按 carrier_code 过滤 status=1 的账号。
     * 用于 DocumentUpload 这类不需要规则挑选、仅需列出全部账号的场景。
     *
     * @param string $carrierCode  承运商 code
     * @param array  $contextValues 业务上下文(可选, 当前未使用, 仅保持签名一致)
     * @return int[] account_id 列表, 无 carrier 或无账号时返回空数组
     */
    public function listAccountsByCarrier($carrierCode, array $contextValues = array())
    {
        $carrierCode = trim((string) $carrierCode);
        if ($carrierCode === '') {
            return array();
        }

        $carrierId = $this->_resolveCarrierIdByCode($carrierCode);
        if (!$carrierId) {
            return array();
        }

        // 单测环境无 Mage 时安全返回
        if (!class_exists('Mage', false)) {
            return array();
        }

        $collection = Mage::getModel('xfe_carrier/carrier_account')
            ->getCollection()
            ->addFieldToFilter('carrier_id', (int) $carrierId)
            ->addFieldToFilter('status', 1);

        $ids = array();
        foreach ($collection as $account) {
            $ids[] = (int) $account->getId();
        }
        return $ids;
    }

    /**
     * 通过 carrierCode + 业务上下文，拿到完整账号凭据（明文）。
     *
     * 与 resolveAccountId（只返 id）对比：本方法返完整凭据，
     * 供 PodService 等需要直接组装 API 调用的场景。
     *
     * 单向依赖：本方法依赖 XFE_Carrier_Model_Carrier_Account（L2），
     * 不出现 use XFE_Carrier_ 业务模块。
     *
     * @param string $carrierCode   承运商 code（gls / chronopost）
     * @param array  $contextValues 业务上下文 key=>value
     * @return array|null 字段清单见 ADR 0019 §1；无 carrier 或无账号时返回 null
     */
    public function getCredentialsByCarrier($carrierCode, array $contextValues = array())
    {
        $carrierCode = trim((string) $carrierCode);
        if ($carrierCode === '') {
            return null;
        }

        $carrierId = $this->_resolveCarrierIdByCode($carrierCode);
        if (!$carrierId) {
            return null;
        }

        // 单测环境无 Mage 时安全返回
        if (!class_exists('Mage', false)) {
            return null;
        }

        // 委托给 Rule Resolver 拿 account_id（与 resolveAccountId 共享同一套规则匹配逻辑）
        $matchContext = new XFE_Carrier_Model_Service_Rule_MatchContext($contextValues);
        try {
            $accountId = XFE_Carrier_Model_Service_Rule_Resolver::instance()
                ->resolveOne(
                    (int)$carrierId,
                    XFE_Carrier_Model_Service_Rule_Resolver::TARGET_ACCOUNT,
                    $matchContext,
                    true
                );
        } catch (XFE_Carrier_Exception_NoRuleMatch $e) {
            // 严格模式 fallback 失败时返 null，PodService 会 fallback 到 system config
            return null;
        }

        if (!$accountId) {
            return null;
        }

        // load 账号实体抽字段（明文）
        $account = Mage::getModel('xfe_carrier/carrier_account')->load((int)$accountId);
        if (!$account->getId()) {
            return null;
        }

        return array(
            'account_id'    => (int) $account->getId(),
            'carrier_id'    => (int) $carrierId,
            'username'      => $account->getUsername(),
            'password'      => $account->getPassword(),
            'endpoint_url'  => $account->getEndpointUrl(),
            'api_key'       => $account->getApiKey(),
            'api_secret'    => $account->getApiSecret(),
            'used_fallback' => true, // Resolver 内部已 fallback；此处只透传
        );
    }

    /**
     * 把 carrierCode 解析为 carrierId。
     *
     * 通过 XFE_Carrier_Model_Carrier 的 Collection 过滤 code 字段。
     * 走 model 层而非直接 SQL,保留与 Carrier 模块的数据访问一致性。
     *
     * @param string $carrierCode
     * @return int 0 表示未找到
     */
    private function _resolveCarrierIdByCode($carrierCode)
    {
        // 不在此用 Mage::throwException:此方法被 XML 注入静默调用,
        // 异常会冒泡到 Runner::trigger 的返回值,需用 null 表示未找到。
        if (!class_exists('Mage', false)) {
            return 0;
        }

        $carrier = Mage::getModel('xfe_carrier/carrier');
        $collection = $carrier->getCollection()
            ->addFieldToFilter('code', $carrierCode);
        $firstItem = $collection->getFirstItem();
        return (int) $firstItem->getId();
    }
}
