<?php

/**
 * 承运商账号批量导出服务。
 *
 * 加载全部承运商账号并生成 CSV 字符串。每行一条账号：
 *   carrier_code, account_name, account_no, api_key, api_secret,
 *   username, password, endpoint_url, status, sort_order, note
 *
 * 输出不含 account_id 等系统自增字段，保证导入到新环境时可干净重建。
 * 导出使用 UTF-8 with BOM，确保 Excel 直接打开中文不乱码。
 *
 * 依赖方向（单向）：
 *   Account_Exporter ─▶ Carrier_Account（DB 实体，读取）
 *                    ─▶ Carrier        （由 carrier_id 反查 code）
 */
class XFE_Carrier_Model_Service_Account_Exporter
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
     * 导出全部承运商账号为 CSV 字符串。
     *
     * @return string 带 BOM 的 CSV
     */
    public function exportAll()
    {
        $rows = array(array(
            'carrier_code', 'account_name', 'account_no', 'api_key', 'api_secret',
            'username', 'password', 'endpoint_url', 'status', 'sort_order', 'note',
            'custom_fields_json',
        ));

        $collection = Mage::getModel('xfe_carrier/carrier_account')->getCollection();
        $collection->load();

        $carrierCache = array();

        foreach ($collection as $account) {
            /** @var XFE_Carrier_Model_Carrier_Account $account */
            $carrierCode = $this->_resolveCarrierCode((int)$account->getCarrierId(), $carrierCache);

            $rows[] = array(
                $carrierCode,
                (string)$account->getAccountName(),
                (string)$account->getAccountNo(),
                (string)$account->getApiKey(),
                (string)$account->getApiSecret(),
                (string)$account->getUsername(),
                (string)$account->getPassword(),
                (string)$account->getEndpointUrl(),
                (string)(int)$account->getStatus(),
                (string)(int)$account->getSortOrder(),
                (string)$account->getNote(),
                (string)$account->getCustomFieldsJson(),
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
