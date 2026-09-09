<?php

/**
 * 承运商规则批量导出服务。
 *
 * 加载全部承运商规则（含条件树）并生成 CSV 字符串。每行一条规则：
 *   carrier_code, module_code, name, description, status,
 *   is_cancel_on_failure, sort_order, priority, account_code, conditions_json
 *
 * 输出不含 rule_id 等系统自增字段，保证导入到新环境时可干净重建。
 * 导出使用 UTF-8 with BOM，确保 Excel 直接打开中文不乱码。
 *
 * 依赖方向（单向）：
 *   Rule_Exporter ─▶ Carrier_Rule（DB 实体，读取 + 条件树）
 *                  ─▶ Carrier     （由 carrier_id 反查 code）
 *                  ─▶ Carrier_Account（由 account_id 反查 account_no）
 */
class XFE_Carrier_Model_Service_Rule_Exporter
{
    /** @var self|null */
    protected static $_instance = null;

    public static function instance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * 导出全部承运商规则为 CSV 字符串。
     *
     * @return string 带 BOM 的 CSV
     */
    public function exportAll()
    {
        $rows = array(array(
            'carrier_code', 'module_code', 'name', 'description', 'status',
            'is_cancel_on_failure', 'sort_order', 'priority', 'account_code', 'conditions_json',
        ));

        $collection = Mage::getModel('xfe_carrier/carrier_rule')->getCollection();
        $collection->load();

        $carrierCache = array();
        $accountCache = array();

        foreach ($collection as $rule) {
            /** @var XFE_Carrier_Model_Carrier_Rule $rule */
            // getConditionsData() 自带兜底：集合行未加载条件树时会自动
            // afterLoad() 补全，因此这里无需依赖集合的 loadConditions()。
            $carrierId = (int)$rule->getCarrierId();
            $carrierCode = $this->_resolveCarrierCode($carrierId, $carrierCache);

            $accountId = (int)$rule->getAccountId();
            $accountCode = $accountId
                ? $this->_resolveAccountNo($carrierId, $accountId, $accountCache)
                : '';

            $conditionsJson = Mage::helper('core')->jsonEncode($rule->getConditionsData());

            $rows[] = array(
                $carrierCode,
                (string)$rule->getModuleCode(),
                (string)$rule->getName(),
                (string)$rule->getDescription(),
                (string)(int)$rule->getStatus(),
                (string)(int)$rule->getIsCancelOnFailure(),
                (string)(int)$rule->getSortOrder(),
                (string)(int)$rule->getPriority(),
                $accountCode,
                $conditionsJson,
            );
        }

        return $this->_toCsv($rows);
    }

    /**
     * 由 carrier_id 反查承运商 code（带进程内缓存）。
     *
     * @param int   $carrierId
     * @param array &$cache
     * @return string
     */
    protected function _resolveCarrierCode($carrierId, array &$cache)
    {
        if (isset($cache[$carrierId])) {
            return $cache[$carrierId];
        }
        $carrier = Mage::getModel('xfe_carrier/carrier')->load($carrierId);
        $code = $carrier && $carrier->getId() ? (string)$carrier->getCode() : '';
        $cache[$carrierId] = $code;
        return $code;
    }

    /**
     * 由 account_id 反查账号 account_no（带进程内缓存）。
     *
     * @param int   $carrierId
     * @param int   $accountId
     * @param array &$cache
     * @return string
     */
    protected function _resolveAccountNo($carrierId, $accountId, array &$cache)
    {
        $key = $carrierId . ':' . $accountId;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $account = Mage::getModel('xfe_carrier/carrier_account')->load($accountId);
        $no = $account && $account->getId() ? (string)$account->getAccountNo() : '';
        $cache[$key] = $no;
        return $no;
    }

    /**
     * 将二维数组编码为 CSV（UTF-8 with BOM）。
     *
     * @param array<int, array<int, string>> $rows
     * @return string
     */
    protected function _toCsv(array $rows)
    {
        $out = "\xEF\xBB\xBF"; // UTF-8 BOM
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $r) {
            $this->_fputcsv($handle, $r);
        }
        rewind($handle);
        $out .= stream_get_contents($handle);
        fclose($handle);
        return $out;
    }

    /**
     * 兼容不同 PHP 版本的 fputcsv 调用。
     *
     * - PHP >= 8.4：$escape 默认值由 "\\" 改为 ""，需显式传 "" 以消除 Deprecated。
     * - PHP  < 8.4：$escape 不能传空字符串（会 Warning: escape must be a character），
     *   使用默认值即可。
     *
     * @param resource $handle
     * @param array    $fields
     * @return int|false
     */
    protected function _fputcsv($handle, array $fields)
    {
        if (PHP_VERSION_ID >= 80400) {
            return fputcsv($handle, $fields, ',', '"', '');
        }
        return fputcsv($handle, $fields);
    }
}
