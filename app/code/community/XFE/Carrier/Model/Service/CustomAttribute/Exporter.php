<?php

/**
 * 自定义属性批量导出服务(1.0.15+)
 *
 * 拉取 xfe_carrier_custom_attribute 全部行,按 entity_type 升序 + sort_order 升序 +
 * field_key 字母序排序,生成 CSV 字符串(11 列)。
 *
 * 依赖方向(单向):
 *   CustomAttribute_Exporter ─▶  CustomAttributeService (L3, 只读)
 *                            ─▶  Domain (L1, 输出形态)
 */
class XFE_Carrier_Model_Service_CustomAttribute_Exporter
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
     * 导出全部(4 分类)或指定分类的 CSV 字符串。
     *
     * @param string|null $entityType null = 全部 4 分类
     * @return string UTF-8 with BOM
     */
    public function exportToString($entityType = null)
    {
        $rows = array($this->_header());

        $service = XFE_Carrier_Model_Service_Registry::customAttributeService();
        if ($entityType !== null
            && in_array($entityType, XFE_Carrier_Domain_CustomAttribute::ALLOWED_ENTITY_TYPES, true)
        ) {
            $coll = $service->getAllDefs($entityType);
        } else {
            $coll = new XFE_Carrier_Domain_CustomAttributeCollection();
            foreach (XFE_Carrier_Domain_CustomAttribute::ALLOWED_ENTITY_TYPES as $t) {
                foreach ($service->getAllDefs($t) as $def) {
                    $coll->add($def);
                }
            }
        }

        foreach ($coll as $def) {
            /** @var XFE_Carrier_Domain_CustomAttribute $def */
            $rows[] = $this->_row($def);
        }
        return $this->_toCsv($rows);
    }

    /**
     * 生成空 CSV 模板(只表头 + 1 行示例)。
     *
     * @return string
     */
    public function writeTemplate()
    {
        $rows = array($this->_header());
        // 示例:账号 (account) 分类的 warehouse_code
        $rows[] = array(
            XFE_Carrier_Domain_CustomAttribute::ENTITY_TYPE_ACCOUNT,
            'warehouse_code',
            '仓库代码',
            'text',
            '',
            '',
            '0',
            '1',
            '10',
            '示例: 仓库代码',
        );
        // 示例: 账号 (account) 分类的 service_level (select)
        $rows[] = array(
            XFE_Carrier_Domain_CustomAttribute::ENTITY_TYPE_ACCOUNT,
            'service_level',
            '服务等级',
            'select',
            'standard,express,economy',
            'standard',
            '1',
            '1',
            '20',
            '必填 select',
        );
        // 示例: 账号 (account) 分类的 supported_areas (multiselect 自由标签)
        $rows[] = array(
            XFE_Carrier_Domain_CustomAttribute::ENTITY_TYPE_ACCOUNT,
            'supported_areas',
            '支持区域',
            'multiselect',
            '',
            '华东|华南|华北',
            '0',
            '1',
            '30',
            'multiselect 自由标签示例 (| 分隔)',
        );
        return $this->_toCsv($rows);
    }

    /**
     * 11 列表头。
     *
     * @return array
     */
    protected function _header()
    {
        return array(
            'entity_type',
            'field_key',
            'label',
            'field_type',
            'options_csv',
            'default_value',
            'is_required',
            'is_active',
            'sort_order',
            'description',
        );
    }

    /**
     * @param XFE_Carrier_Domain_CustomAttribute $def
     * @return array
     */
    protected function _row(XFE_Carrier_Domain_CustomAttribute $def)
    {
        $default = $def->getDefaultValue();
        if (is_array($default)) {
            // multiselect 用 | 分隔(参见 ADR 0005)
            $default = implode('|', $default);
        } elseif ($default === null) {
            $default = '';
        } elseif (is_bool($default)) {
            $default = $default ? '1' : '0';
        }

        return array(
            $def->getEntityType(),
            $def->getFieldKey(),
            $def->getLabel(),
            $def->getFieldType(),
            $def->getOptions() === null ? '' : implode(',', $def->getOptions()),
            (string) $default,
            $def->isRequired() ? '1' : '0',
            $def->isActive()   ? '1' : '0',
            (string) (int) $def->getSortOrder(),
            $def->getDescription() === null ? '' : (string) $def->getDescription(),
        );
    }

    /**
     * @param array $rows
     * @return string
     */
    protected function _toCsv(array $rows)
    {
        $out = "\xEF\xBB\xBF";
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
     * 兼容 PHP 8.4 fputcsv 签名变更。
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
