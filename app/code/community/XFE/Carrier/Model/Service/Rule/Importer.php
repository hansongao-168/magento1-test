<?php

/**
 * 承运商规则批量导入服务。
 *
 * 解析 CSV（每行一条规则）并 upsert 规则。每行包含：
 *   carrier_code         必填，承运商标识代码，用于定位 carrier_id
 *   module_code          可选，关联模块代码
 *   name                 必填，规则名称；同承运商下作为去重键
 *   description          可选，规则描述
 *   status               可选，1/0，默认 1
 *   is_cancel_on_failure 可选，1/0，默认 0
 *   sort_order           可选，默认 0
 *   priority             可选，默认 0
 *   account_code         可选，账号编号（account_no），用于绑定规则到账号
 *   conditions_json      可选，条件树 JSON 字符串
 *
 * 行为：
 *   - 缺失必填字段 / carrier_code 定位不到承运商 => 跳过该行并报错
 *   - account_code 定位不到账号 => account_id 置空（不报错）
 *   - conditions_json 解析失败 => 跳过该行并报错
 *   - 同承运商下同名规则 => 更新字段与条件树；否则新增
 *
 * 持久化复用 XFE_Carrier_Model_Carrier_Rule 模型（其 _afterSave()
 * 负责写入条件树），不在此重复实现条件树 SQL。
 *
 * 依赖方向（单向）：
 *   Rule_Importer ─▶ Carrier_Rule  （DB 实体，写入）
 *                 ─▶ Carrier       （通过 code 定位，仅读取）
 *                 ─▶ Carrier_Account（通过 account_no 定位，仅读取）
 */
class XFE_Carrier_Model_Service_Rule_Importer
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
     * 从已 fgetcsv 解码的内存行导入规则。
     *
     * @param array<int, array<string, string>> $rows
     * @return XFE_Carrier_Model_Service_Rule_Importer_Result
     */
    public function importRows(array $rows)
    {
        $result = new XFE_Carrier_Model_Service_Rule_Importer_Result();

        if (empty($rows)) {
            $result->addError(0, $this->_hlp()->__('CSV is empty.'));
            return $result;
        }

        // 承运商 code => carrier_id 与账号 account_no => account_id 的进程内缓存，
        // 避免同文件多行反复查库。
        $carrierByCode = array();
        $accountByNo   = array();

        foreach ($rows as $idx => $row) {
            // $idx 为 0 起始（表头之后），面向用户的行号 = idx + 2
            $rowNo = $idx + 2;

            $carrierCode = isset($row['carrier_code']) ? trim((string)$row['carrier_code']) : '';
            $name        = isset($row['name']) ? trim((string)$row['name']) : '';
            $moduleCode  = isset($row['module_code']) ? trim((string)$row['module_code']) : '';
            $description = isset($row['description']) ? trim((string)$row['description']) : '';
            $accountCode = isset($row['account_code']) ? trim((string)$row['account_code']) : '';
            $conditionsRaw = isset($row['conditions_json']) ? trim((string)$row['conditions_json']) : '';

            if ($carrierCode === '') {
                $result->addError($rowNo, $this->_hlp()->__('carrier_code 为必填项。'));
                $result->skipped++;
                continue;
            }
            if ($name === '') {
                $result->addError($rowNo, $this->_hlp()->__('name 为必填项。'));
                $result->skipped++;
                continue;
            }

            $carrierId = $this->_resolveCarrierId($carrierCode, $carrierByCode);
            if (!$carrierId) {
                $result->addError(
                    $rowNo,
                    $this->_hlp()->__('未找到承运商 "%s"。', $carrierCode)
                );
                $result->skipped++;
                continue;
            }

            $conditions = array();
            if ($conditionsRaw !== '') {
                $conditions = Mage::helper('core')->jsonDecode($conditionsRaw);
                if (!is_array($conditions)) {
                    $result->addError(
                        $rowNo,
                        $this->_hlp()->__('conditions_json 不是合法的 JSON 数组。')
                    );
                    $result->skipped++;
                    continue;
                }
            }

            try {
                $rule = Mage::getModel('xfe_carrier/carrier_rule');
                // Upsert：同承运商下按 name 定位已存在规则。
                $existing = Mage::getModel('xfe_carrier/carrier_rule')->getCollection()
                    ->addFieldToFilter('carrier_id', $carrierId)
                    ->addFieldToFilter('name', $name)
                    ->getFirstItem();

                if ($existing && $existing->getId()) {
                    $rule = $existing;
                }

                $rule->setCarrierId($carrierId);

                $accountId = null;
                if ($accountCode !== '') {
                    $accountId = $this->_resolveAccountId($carrierId, $accountCode, $accountByNo);
                }
                $rule->setAccountId($accountId);
                $rule->setModuleCode($moduleCode);
                $rule->setName($name);
                $rule->setDescription($description);
                $rule->setStatus($this->_toInt($row, 'status', 1));
                $rule->setIsCancelOnFailure($this->_toInt($row, 'is_cancel_on_failure', 0));
                $rule->setSortOrder($this->_toInt($row, 'sort_order', 0));
                $rule->setPriority($this->_toInt($row, 'priority', 0));

                // 始终交给模型 _afterSave() 处理条件树：空数组会清空旧条件树
                // （匹配所有），非空数组则整体替换。
                $rule->setGroupsData($conditions);

                $rule->save();

                if ($existing && $existing->getId()) {
                    $result->updated++;
                } else {
                    $result->created++;
                }
            } catch (Exception $e) {
                $result->addError($rowNo, $e->getMessage());
                $result->skipped++;
            }
        }

        return $result;
    }

    /**
     * 读取上传文件，校验并返回按小写表头键名的行数组。
     *
     * @param array $file $_FILES 中单项
     * @return XFE_Carrier_Model_Service_Rule_Importer_Result
     */
    public function importUpload(array $file)
    {
        $result = new XFE_Carrier_Model_Service_Rule_Importer_Result();

        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $result->addError(0, $this->_hlp()->__('No file was uploaded.'));
            return $result;
        }
        if (!empty($file['error'])) {
            $result->addError(0, $this->_hlp()->__('Upload error code %d.', (int)$file['error']));
            return $result;
        }
        if (($file['size'] ?? 0) <= 0) {
            $result->addError(0, $this->_hlp()->__('Uploaded file is empty.'));
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

        $handle = @fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $result->addError(0, $this->_hlp()->__('Failed to read the uploaded file.'));
            return $result;
        }

        $header = $this->_fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            $result->addError(0, $this->_hlp()->__('CSV has no header row.'));
            return $result;
        }

        $headerMap = array();
        foreach ($header as $i => $name) {
            $name = trim((string)$name);
            // 兼容带 UTF-8 BOM 的导出文件（导出服务输出 BOM 以便 Excel 打开）。
            if ($i === 0) {
                $name = preg_replace('/^\xEF\xBB\xBF/', '', $name);
            }
            $headerMap[strtolower($name)] = $i;
        }
        foreach (array('carrier_code', 'name') as $required) {
            if (!isset($headerMap[$required])) {
                fclose($handle);
                $result->addError(0, $this->_hlp()->__('CSV is missing required column "%s".', $required));
                return $result;
            }
        }

        $rows = array();
        while (($raw = $this->_fgetcsv($handle)) !== false) {
            if ($raw === array(null) || (count($raw) === 1 && trim((string)$raw[0]) === '')) {
                continue; // 跳过空行
            }
            $row = array();
            foreach ($headerMap as $col => $colIdx) {
                $row[$col] = isset($raw[$colIdx]) ? $raw[$colIdx] : '';
            }
            $rows[] = $row;
        }
        fclose($handle);

        $rowResult = $this->importRows($rows);
        foreach ($rowResult->getErrors() as $rowNo => $msg) {
            $result->addError($rowNo, $msg);
        }
        $result->created += $rowResult->created;
        $result->updated += $rowResult->updated;
        $result->skipped += $rowResult->skipped;
        return $result;
    }

    /**
     * 生成规则导入 CSV 模板并写入指定文件。
     *
     * @param string $outFile 服务端绝对路径（如临时文件）
     * @return bool
     */
    public function writeTemplate($outFile)
    {
        $rows = array(
            array(
                'carrier_code', 'module_code', 'name', 'description', 'status',
                'is_cancel_on_failure', 'sort_order', 'priority', 'account_code', 'conditions_json',
            ),
            array(
                'sf_test', 'logo', '示例规则-美国件', '匹配发往美国', '1', '0', '0', '10', '',
                '[{"aggregator":"all","conditions":[{"attribute":"country_code","operator":"==","value":"US"}]}]',
            ),
        );
        $handle = @fopen($outFile, 'w');
        if (!$handle) {
            return false;
        }
        foreach ($rows as $r) {
            $this->_fputcsv($handle, $r);
        }
        fclose($handle);
        return true;
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

    /**
     * 兼容不同 PHP 版本的 fgetcsv 调用（PHP 版本差异同上）。
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
     * 读取数值型可选列，非法则回退默认值。
     *
     * @param array  $row
     * @param string $key
     * @param int    $default
     * @return int
     */
    protected function _toInt(array $row, $key, $default)
    {
        if (!isset($row[$key]) || trim((string)$row[$key]) === '') {
            return $default;
        }
        $value = trim((string)$row[$key]);
        return is_numeric($value) ? (int)$value : $default;
    }

    /**
     * 定位承运商 id（带进程内缓存）。
     *
     * @param string $code
     * @param array  &$cache
     * @return int
     */
    protected function _resolveCarrierId($code, array &$cache)
    {
        if (isset($cache[$code])) {
            return $cache[$code];
        }
        $carrier = Mage::getModel('xfe_carrier/carrier')->load($code, 'code');
        $id = $carrier && $carrier->getId() ? (int)$carrier->getId() : 0;
        $cache[$code] = $id;
        return $id;
    }

    /**
     * 在指定承运商下按 account_no 定位账号 id（带进程内缓存）。
     *
     * @param int    $carrierId
     * @param string $accountNo
     * @param array  &$cache
     * @return int|null
     */
    protected function _resolveAccountId($carrierId, $accountNo, array &$cache)
    {
        $key = $carrierId . ':' . $accountNo;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $account = Mage::getModel('xfe_carrier/carrier_account')->getCollection()
            ->addFieldToFilter('carrier_id', $carrierId)
            ->addFieldToFilter('account_no', $accountNo)
            ->getFirstItem();
        $id = $account && $account->getId() ? (int)$account->getId() : 0;
        $cache[$key] = $id;
        return $id ?: null;
    }

    /**
     * @return XFE_Carrier_Helper_Data
     */
    protected function _hlp()
    {
        return Mage::helper('xfe_carrier');
    }
}
