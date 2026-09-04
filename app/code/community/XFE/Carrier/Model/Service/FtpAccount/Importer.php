<?php

/**
 * 承运商 FTP账号批量导入服务。
 *
 * 解析 CSV（每行一条 FTP账号）并 upsert FTP账号。每行包含：
 *   carrier_code  必填，承运商标识代码，用于定位 carrier_id
 *   account_name  必填，账号名称；同一承运商下与 account_no 共同作为去重键
 *   account_no    可选，账号编号
 *   protocol      可选，ftp/sftp/ftps，默认 ftp
 *   host          必填，FTP 主机
 *   port          可选，默认 21
 *   username      可选，登录用户名
 *   password      可选，登录密码
 *   remote_path   可选，远程路径
 *   mode          可选，passive/active，默认 passive
 *   encoding      可选，默认 UTF-8
 *   status        可选，1/0，默认 1
 *   sort_order    可选，默认 0
 *   note          可选，备注
 *
 * 行为：
 *   - 缺失必填字段 / carrier_code 定位不到承运商 => 跳过该行并报错
 *   - 同一承运商下 account_name 与 account_no 均相同 => 更新字段；否则新增
 *
 * 持久化复用 XFE_Carrier_Model_Carrier_FtpAccount 模型（其 _beforeSave()
 * 负责维护 created_at / updated_at）。
 *
 * 依赖方向（单向）：
 *   FtpAccount_Importer ─▶ Carrier_FtpAccount（DB 实体，写入）
 *                       ─▶ Carrier            （通过 code 定位，仅读取）
 */
class XFE_Carrier_Model_Service_FtpAccount_Importer
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
     * 从已 fgetcsv 解码的内存行导入 FTP账号。
     *
     * @param array<int, array<string, string>> $rows
     * @return XFE_Carrier_Model_Service_FtpAccount_Importer_Result
     */
    public function importRows(array $rows)
    {
        $result = new XFE_Carrier_Model_Service_FtpAccount_Importer_Result();

        if (empty($rows)) {
            $result->addError(0, $this->_hlp()->__('CSV is empty.'));
            return $result;
        }

        // 承运商 code => carrier_id 的进程内缓存，避免同文件多行反复查库。
        $carrierByCode = array();

        foreach ($rows as $idx => $row) {
            // $idx 为 0 起始（表头之后），面向用户的行号 = idx + 2
            $rowNo = $idx + 2;

            $carrierCode = isset($row['carrier_code']) ? trim((string)$row['carrier_code']) : '';
            $accountName = isset($row['account_name']) ? trim((string)$row['account_name']) : '';
            $accountNo   = isset($row['account_no']) ? trim((string)$row['account_no']) : '';
            $host        = isset($row['host']) ? trim((string)$row['host']) : '';

            if ($carrierCode === '') {
                $result->addError($rowNo, $this->_hlp()->__('carrier_code 为必填项。'));
                $result->skipped++;
                continue;
            }
            if ($accountName === '') {
                $result->addError($rowNo, $this->_hlp()->__('account_name 为必填项。'));
                $result->skipped++;
                continue;
            }
            if ($host === '') {
                $result->addError($rowNo, $this->_hlp()->__('host 为必填项。'));
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

            try {
                $ftp = Mage::getModel('xfe_carrier/carrier_ftp_account');
                // Upsert：同一承运商下按 account_name + account_no 定位已存在 FTP账号。
                $existing = Mage::getModel('xfe_carrier/carrier_ftp_account')->getCollection()
                    ->addFieldToFilter('carrier_id', $carrierId)
                    ->addFieldToFilter('account_name', $accountName)
                    ->addFieldToFilter('account_no', $accountNo)
                    ->getFirstItem();

                if ($existing && $existing->getId()) {
                    $ftp = $existing;
                }

                $protocol = isset($row['protocol']) ? trim((string)$row['protocol']) : '';
                if (!in_array($protocol, array('ftp', 'sftp', 'ftps'), true)) {
                    $protocol = 'ftp';
                }
                $mode = isset($row['mode']) ? trim((string)$row['mode']) : '';
                if (!in_array($mode, array('passive', 'active'), true)) {
                    $mode = 'passive';
                }
                $encoding = isset($row['encoding']) && trim((string)$row['encoding']) !== ''
                    ? trim((string)$row['encoding'])
                    : 'UTF-8';

                $ftp->setCarrierId($carrierId);
                $ftp->setAccountName($accountName);
                $ftp->setAccountNo($accountNo);
                $ftp->setProtocol($protocol);
                $ftp->setHost($host);
                $ftp->setPort($this->_toInt($row, 'port', 21));
                $ftp->setUsername(isset($row['username']) ? trim((string)$row['username']) : '');
                $ftp->setPassword(isset($row['password']) ? trim((string)$row['password']) : '');
                $ftp->setRemotePath(isset($row['remote_path']) ? trim((string)$row['remote_path']) : '');
                $ftp->setMode($mode);
                $ftp->setEncoding($encoding);
                $ftp->setStatus($this->_toInt($row, 'status', 1));
                $ftp->setSortOrder($this->_toInt($row, 'sort_order', 0));
                $ftp->setNote(isset($row['note']) ? trim((string)$row['note']) : '');
                $ftp->setCustomFieldsJson(
                    $this->_extractCustomFieldsJson($row)
                );

                $ftp->save();

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
     * @return XFE_Carrier_Model_Service_FtpAccount_Importer_Result
     */
    public function importUpload(array $file)
    {
        $result = new XFE_Carrier_Model_Service_FtpAccount_Importer_Result();

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
        foreach (array('carrier_code', 'account_name', 'host') as $required) {
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
     * 生成 FTP账号导入 CSV 模板并写入指定文件。
     *
     * @param string $outFile 服务端绝对路径（如临时文件）
     * @return bool
     */
    public function writeTemplate($outFile)
    {
        $rows = array(
            array(
                'carrier_code', 'account_name', 'account_no', 'protocol', 'host', 'port',
                'username', 'password', 'remote_path', 'mode', 'encoding',
                'status', 'sort_order', 'note', 'custom_fields_json',
            ),
            array(
                'sf_test', '示例FTP账号', 'FTP0001', 'sftp', 'sftp.example.com', '22',
                'username', 'password', '/upload', 'passive', 'UTF-8',
                '1', '10', '示例备注',
                '{"private_key_path":{"label":"私钥路径","type":"text","value":"/etc/keys/id_rsa"}}',
            ),
            array(
                'sf_test', '多选示例FTP', 'FTP0002', 'sftp', 'sftp.example.com', '22',
                '', '', '', '', '',
                '1', '20', 'multiselect 示例',
                '{"allowed_dirs":{"label":"允许目录","type":"multiselect","value":["/in","/out"]}}',
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
     * 把 CSV 一行中的 custom_fields_json 字段解析为合法 JSON 字符串。
     * 与 Account_Importer 同款语义,此处独立实现以保持 FTP 子模块的
     * 自治性(可被独立升级)。
     *
     * @param array $row
     * @return string|null
     */
    protected function _extractCustomFieldsJson(array $row)
    {
        if (!isset($row['custom_fields_json'])) {
            return null;
        }
        $raw = trim((string) $row['custom_fields_json']);
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || $decoded === array()) {
            return null;
        }
        return json_encode(
            (object) $decoded,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
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
     * @return XFE_Carrier_Helper_Data
     */
    protected function _hlp()
    {
        return Mage::helper('xfe_carrier');
    }
}
