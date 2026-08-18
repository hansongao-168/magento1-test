<?php

/**
 * Carrier Importer Service
 *
 * Parses a CSV upload of carrier rows and upserts them. Each row contains:
 *   name               (required, varchar 255)
 *   code               (required, varchar 64, unique by carrier convention)
 *   shipping_company_id (optional, int - may be negative placeholder IDs)
 *
 * Behaviour:
 *   - missing required field => row skipped, error reported
 *   - existing carrier with the same code → updated (name + shipping_company_id)
 *   - new code → inserted
 *   - duplicate code within the same file → first wins, later rows error
 *
 * Returns a Result object with:
 *   - created:   int count
 *   - updated:   int count
 *   - skipped:   int count
 *   - errors:    array<int, string>   row-number -> message (1-indexed, header excluded)
 *
 * The Importer has no dependencies on other Carrier services; it talks
 * to the DB through Mage::getModel('xfe_carrier/carrier'). shipping_company_id
 * accepts any integer (including negative placeholder ids like -1001..-1010);
 * only non-integer values are rejected.
 */
class XFE_Carrier_Model_Service_Importer
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
     * Import rows from an in-memory CSV payload (already fgetcsv-decoded).
     * Returns XFE_Carrier_Model_Service_Importer_Result.
     *
     * @param array<int, array<string, string>> $rows
     * @return XFE_Carrier_Model_Service_Importer_Result
     */
    public function importRows(array $rows)
    {
        $result = new XFE_Carrier_Model_Service_Importer_Result();

        if (empty($rows)) {
            $result->addError(0, $this->_hlp()->__('CSV is empty.'));
            return $result;
        }

        $seenCodes = array();

        foreach ($rows as $idx => $row) {
            // $idx is 0-based after header; user-facing row number = idx + 2
            $rowNo = $idx + 2;

            $name    = isset($row['name']) ? trim((string)$row['name']) : '';
            $code    = isset($row['code']) ? trim((string)$row['code']) : '';
            $scRaw   = isset($row['shipping_company_id']) ? trim((string)$row['shipping_company_id']) : '';

            if ($name === '') {
                $result->addError($rowNo, $this->_hlp()->__('Name is required.'));
                $result->skipped++;
                continue;
            }
            if ($code === '') {
                $result->addError($rowNo, $this->_hlp()->__('Code is required.'));
                $result->skipped++;
                continue;
            }
            if (isset($seenCodes[$code])) {
                $result->addError(
                    $rowNo,
                    $this->_hlp()->__('Duplicate code "%s" within the same file (also row %d).', $code, $seenCodes[$code])
                );
                $result->skipped++;
                continue;
            }
            $seenCodes[$code] = $rowNo;

            $scId = null;
            if ($scRaw !== '') {
                // Accept any integer (including negative placeholder ids like
                // -1001..-1010 and arbitrary positive ids). Only reject values
                // that cannot be an integer at all.
                if (!is_numeric($scRaw) || (string)(int)$scRaw !== (string)trim($scRaw)) {
                    $result->addError(
                        $rowNo,
                        $this->_hlp()->__('Shipping company id "%s" is not an integer.', $scRaw)
                    );
                    $result->skipped++;
                    continue;
                }
                $scId = (int)$scRaw;
            }

            try {
                /** @var XFE_Carrier_Model_Carrier $model */
                $model = Mage::getModel('xfe_carrier/carrier');
                $existing = $model->load($code, 'code');
                if ($existing && $existing->getId()) {
                    $existing->setName($name);
                    $existing->setShippingCompanyId($scId);
                    $existing->save();
                    $result->updated++;
                } else {
                    $model->setName($name);
                    $model->setCode($code);
                    $model->setShippingCompanyId($scId);
                    $model->setStatus(1);
                    $model->setSortOrder(0);
                    $model->save();
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
     * Read the CSV upload from $_FILES, validate it, and return rows
     * keyed by lower-case header name. Returns a list of errors via
     * the same Result object.
     *
     * @param array $file            single entry from $_FILES
     * @return XFE_Carrier_Model_Service_Importer_Result
     */
    public function importUpload(array $file)
    {
        $result = new XFE_Carrier_Model_Service_Importer_Result();

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
        $mime = isset($file['type']) ? strtolower((string)$file['type']) : '';
        $ext  = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
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

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            $result->addError(0, $this->_hlp()->__('CSV has no header row.'));
            return $result;
        }

        $headerMap = array();
        foreach ($header as $i => $name) {
            $headerMap[strtolower(trim((string)$name))] = $i;
        }
        // Required: name, code
        foreach (array('name', 'code') as $required) {
            if (!isset($headerMap[$required])) {
                fclose($handle);
                $result->addError(0, $this->_hlp()->__('CSV is missing required column "%s".', $required));
                return $result;
            }
        }

        $rows = array();
        $lineNo = 1;
        while (($raw = fgetcsv($handle)) !== false) {
            $lineNo++;
            if ($raw === array(null) || (count($raw) === 1 && trim((string)$raw[0]) === '')) {
                continue; // skip blank lines
            }
            $row = array();
            foreach ($headerMap as $col => $colIdx) {
                $row[$col] = isset($raw[$colIdx]) ? $raw[$colIdx] : '';
            }
            $rows[] = $row;
        }
        fclose($handle);

        $rowResult = $this->importRows($rows);
        // Merge: errors of the row pass become errors of the file result too.
        foreach ($rowResult->getErrors() as $rowNo => $msg) {
            $result->addError($rowNo, $msg);
        }
        $result->created += $rowResult->created;
        $result->updated += $rowResult->updated;
        $result->skipped += $rowResult->skipped;
        return $result;
    }

    /**
     * Emit a CSV template as an attachment download.
     *
     * @param string $outFile absolute path on the server (e.g. temp file)
     * @return bool
     */
    public function writeTemplate($outFile)
    {
        $rows = array(
            array('name', 'code', 'shipping_company_id'),
            array('顺丰国际标快测试', 'sf_test', '-1001'),
            array('FedEx-IE测试', 'fedex_test', '-1003'),
        );
        $handle = @fopen($outFile, 'w');
        if (!$handle) {
            return false;
        }
        foreach ($rows as $r) {
            fputcsv($handle, $r);
        }
        fclose($handle);
        return true;
    }

    /**
     * @return XFE_Carrier_Helper_Data
     */
    protected function _hlp()
    {
        return Mage::helper('xfe_carrier');
    }
}