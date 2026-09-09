<?php

/**
 * 自定义属性批量导入服务(1.0.15+)
 *
 * 解析 CSV(每行一条属性定义)并 upsert。11 列:
 *   entity_type    必填, carrier | account | ftp_account | logo
 *   field_key      必填, /^[a-z0-9_]{1,64}$/
 *   label          必填, 1~64 字符
 *   field_type     必填, text | number | select | multiselect | boolean
 *   options_csv    条件, select/multiselect 逗号分隔
 *   default_value  可选, scalar;multiselect 用 | 分隔
 *   is_required    可选, 0/1 (默认 0)
 *   is_active      可选, 0/1 (默认 1)
 *   sort_order     可选, int (默认 0)
 *   description    可选, string
 *
 * 行为:
 *   - (entity_type, field_key) 不存在 -> 创建
 *   - (entity_type, field_key) 存在且 is_active=1 -> 更新
 *   - (entity_type, field_key) 存在但 is_active=0 -> 重新激活 + 更新
 *   - 同 (entity_type, field_key) 出现多次 -> 取最后一次, 记入 warnings
 *
 * 依赖方向(单向):
 *   CustomAttribute_Importer ─▶  CustomAttributeService (L3, write)
 *                            ─▶  Result (本包)
 */
class XFE_Carrier_Model_Service_CustomAttribute_Importer
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
     * 从文件路径读 CSV(供 service 用,无 $_FILES)。
     *
     * @param string $filePath
     * @return XFE_Carrier_Model_Service_CustomAttribute_Importer_Result
     */
    public function importFile($filePath)
    {
        $result = new XFE_Carrier_Model_Service_CustomAttribute_Importer_Result();
        if (!is_file($filePath)) {
            $result->addError(0, $this->_hlp()->__('File not found: %s', $filePath));
            return $result;
        }
        $handle = @fopen($filePath, 'r');
        if (!$handle) {
            $result->addError(0, $this->_hlp()->__('Failed to read the file.'));
            return $result;
        }
        $rows = $this->_readRows($handle, $result);
        fclose($handle);
        if ($result->hasErrors() && empty($rows)) {
            return $result;
        }
        return $this->_applyRows($rows, $result);
    }

    /**
     * 从已 fgetcsv 解码的内存行导入(便于测试)。
     *
     * @param array<int, array<string, string>> $rows
     * @return XFE_Carrier_Model_Service_CustomAttribute_Importer_Result
     */
    public function importRows(array $rows)
    {
        $result = new XFE_Carrier_Model_Service_CustomAttribute_Importer_Result();
        return $this->_applyRows($rows, $result);
    }

    /**
     * 从 $_FILES 单项读 + 解析。
     *
     * @param array $file
     * @return XFE_Carrier_Model_Service_CustomAttribute_Importer_Result
     */
    public function importUpload(array $file)
    {
        $result = new XFE_Carrier_Model_Service_CustomAttribute_Importer_Result();

        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $result->addError(0, $this->_hlp()->__('No file was uploaded.'));
            return $result;
        }
        if (!empty($file['error'])) {
            $result->addError(0, $this->_hlp()->__('Upload error code %d.', (int) $file['error']));
            return $result;
        }
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, array('csv', 'txt'), true)) {
            $result->addError(
                0,
                $this->_hlp()->__('Unsupported file extension "%s"; please upload .csv.', $ext)
            );
            return $result;
        }
        return $this->importFile($file['tmp_name']);
    }

    /**
     * 读取 CSV 行(表头解析 + BOM 兼容 + 空行跳过)。
     *
     * @param resource $handle
     * @param XFE_Carrier_Model_Service_CustomAttribute_Importer_Result $result
     * @return array
     */
    protected function _readRows($handle, $result)
    {
        $header = $this->_fgetcsv($handle);
        if (!$header) {
            $result->addError(0, $this->_hlp()->__('CSV has no header row.'));
            return array();
        }
        $headerMap = array();
        foreach ($header as $i => $name) {
            $name = trim((string) $name);
            if ($i === 0) {
                $name = preg_replace('/^\xEF\xBB\xBF/', '', $name);
            }
            $headerMap[strtolower($name)] = $i;
        }
        foreach (array('entity_type', 'field_key', 'label', 'field_type') as $required) {
            if (!isset($headerMap[$required])) {
                $result->addError(0, $this->_hlp()->__('CSV is missing required column "%s".', $required));
                return array();
            }
        }
        $rows = array();
        while (($raw = $this->_fgetcsv($handle)) !== false) {
            if ($raw === array(null) || (count($raw) === 1 && trim((string) $raw[0]) === '')) {
                continue;
            }
            $row = array();
            foreach ($headerMap as $col => $colIdx) {
                $row[$col] = isset($raw[$colIdx]) ? $raw[$colIdx] : '';
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * 逐行应用 upsert。
     *
     * @param array $rows
     * @param XFE_Carrier_Model_Service_CustomAttribute_Importer_Result $result
     * @return XFE_Carrier_Model_Service_CustomAttribute_Importer_Result
     */
    protected function _applyRows(array $rows, $result)
    {
        if (empty($rows)) {
            $result->addError(0, $this->_hlp()->__('CSV is empty.'));
            return $result;
        }

        $service = XFE_Carrier_Model_Service_Registry::customAttributeService();
        $seen = array();   // (entity_type, field_key) => [lastIdx, wasActivated]

        foreach ($rows as $idx => $row) {
            $rowNo = $idx + 2;
            $entityType = isset($row['entity_type']) ? trim((string) $row['entity_type']) : '';
            $fieldKey   = isset($row['field_key'])   ? trim((string) $row['field_key'])   : '';

            // 必填校验
            if (!in_array($entityType, XFE_Carrier_Domain_CustomAttribute::ALLOWED_ENTITY_TYPES, true)) {
                $result->addError($rowNo, $this->_hlp()->__('Invalid entity_type: %s', $entityType));
                $result->skipped++;
                continue;
            }
            if (!preg_match('/^[a-z0-9_]{1,64}$/', $fieldKey)) {
                $result->addError($rowNo, $this->_hlp()->__('Invalid field_key: %s', $fieldKey));
                $result->skipped++;
                continue;
            }
            if (trim((string) ($row['label'] ?? '')) === '') {
                $result->addError($rowNo, $this->_hlp()->__('label is required.'));
                $result->skipped++;
                continue;
            }
            if (!in_array(trim((string) ($row['field_type'] ?? '')), XFE_Carrier_Domain_CustomField::ALLOWED_TYPES, true)) {
                $result->addError($rowNo, $this->_hlp()->__('Invalid field_type: %s', $row['field_type']));
                $result->skipped++;
                continue;
            }

            $seenKey = $entityType . '|' . $fieldKey;
            if (isset($seen[$seenKey])) {
                $result->addError($rowNo, $this->_hlp()->__(
                    '重复的 (entity_type, field_key), 取最后一次: (entity_type=%s, field_key=%s)',
                    $entityType, $fieldKey
                ));
            }
            $seen[$seenKey] = $rowNo;

            // 检测激活: 同 (entity_type, field_key) 是否有 is_active=0 记录
            $existing = Mage::getModel('xfe_carrier/custom_attribute')
                ->getCollection()
                ->addEntityTypeFilter($entityType)
                ->addFieldToFilter('field_key', $fieldKey);
            $wasInactive = false;
            $existingId = 0;
            foreach ($existing as $ex) {
                if ((int) $ex->getData('is_active') === 0) {
                    $wasInactive = true;
                }
                if ((int) $ex->getData('is_active') === 1) {
                    $existingId = (int) $ex->getId();
                }
            }

            try {
                if ($existingId > 0) {
                    $service->updateDef($existingId, $row);
                    $result->updated++;
                } else {
                    $service->createDef($row);
                    $result->created++;
                }
                if ($wasInactive) {
                    $result->activated++;
                }
            } catch (Exception $e) {
                $result->addError($rowNo, $e->getMessage());
                $result->skipped++;
            }
        }
        return $result;
    }

    /**
     * 兼容 PHP 8.4 fgetcsv 签名变更。
     *
     * @param resource $handle
     * @return array|false
     */
    protected function _fgetcsv($handle)
    {
        if (PHP_VERSION_ID >= 80400) {
            return fgetcsv($handle, 0, ',', '"', '');
        }
        return fgetcsv($handle);
    }

    /**
     * @return XFE_Carrier_Helper_Data
     */
    protected function _hlp()
    {
        return Mage::helper('xfe_carrier');
    }
}
