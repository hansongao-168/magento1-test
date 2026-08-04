<?php

class XFE_LabelPrint_Helper_Data extends Mage_Core_Helper_Abstract
{

    const VAR_SUBDIR = 'xfe/labelprint';

    const EVENT_RESPONSE_RECEIVED = 'xfe_labelprint_response_received';

    /**
     * @param array $payload
     * @return int|false
     */
    public function record(array $payload)
    {
        if (!$payload) {
            return false;
        }

        $orderId          = isset($payload['order_id']) ? (int)$payload['order_id'] : null;
        $trackingNumberId = isset($payload['tracking_number_id'])
            ? (int)$payload['tracking_number_id']
            : null;

        // Resolve the path the helper will own. When the caller already
        // placed the file, normalise to a var-relative path and skip
        // file IO entirely.
        $relPath = $this->_resolveRelPath($payload);

        // Upsert by tracking_number_id when supplied and non-zero.
        $row = null;
        if ($trackingNumberId) {
            $row = $this->_findLatestByTrackingNumber($trackingNumberId);
        }

        if ($row === null) {
            $row = Mage::getModel('xfe_labelprint/print');
        }

        return $this->_applyAndSave($row, $payload, $relPath, $orderId, $trackingNumberId, false);
    }

    /**
     *
     * @param int $orderId
     * @param int $trackingNumberId
     * @param string $ym
     * @return XFE_LabelPrint_Model_Print|null Null when nothing matches.
     */
    public function findLatestByMonth($orderId, $trackingNumberId, $ym = '')
    {
        $ym = $this->_normaliseYearMonth($ym);
        if ($ym === null) {
            return null;
        }

        $filters = array();
        if ((int)$orderId > 0) {
            $filters['order_id'] = (int)$orderId;
        }
        if ((int)$trackingNumberId > 0) {
            $filters['tracking_number_id'] = (int)$trackingNumberId;
        }
        if (!$filters) {
            return null;
        }

        $prefix = self::VAR_SUBDIR . '/' . $ym . '/';
        $filters['path_file'] = array('like' => $prefix . '%');

        $collection = Mage::getModel('xfe_labelprint/print')->getCollection()
            ->addFieldToFilter($filters)
            ->setOrder('id', 'DESC');

        foreach ($collection as $row) {
            $abs = Mage::getBaseDir('var') . DS
                . str_replace('/', DS, (string)$row->getPathFile());
            if (is_file($abs)) {
                return $row;
            }
        }

        // Fall back to any path_file (may live in a different month)
        // so admins can still re-link a row whose file was moved.
        $relaxed = $filters;
        unset($relaxed['path_file']);
        $collection = Mage::getModel('xfe_labelprint/print')->getCollection()
            ->addFieldToFilter($relaxed)
            ->setOrder('id', 'DESC');
        foreach ($collection as $row) {
            $abs = Mage::getBaseDir('var') . DS
                . str_replace('/', DS, (string)$row->getPathFile());
            if (is_file($abs)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param XFE_LabelPrint_Model_Print $row
     * @param array $payload New file payload; supports path_file /
     *                       label_content / additional_data / printed_at.
     * @return int|false Saved row id, or false on failure.
     */
    public function replaceLabel(XFE_LabelPrint_Model_Print $row, array $payload)
    {
        if (!$row->getId()) {
            return false;
        }

        $relPath = $this->_resolveRelPath($payload);
        if ($relPath === null) {
            return false;
        }

        $payload['order_id'] = (int)$row->getOrderId();
        $payload['tracking_number_id'] = (int)$row->getTrackingNumberId();

        return $this->_applyAndSave($row, $payload, $relPath, null, null, true);
    }

    /**
     * @param int $orderId
     * @param int $trackingNumberId
     * @param string $ym "YYYY-MM" or "YYYY/MM"; empty = current month
     * @param array $payload New file payload.
     * @return int|false Saved row id, or false when nothing matched.
     */
    public function replaceByMonth($orderId, $trackingNumberId, $ym, array $payload)
    {
        $row = $this->findLatestByMonth($orderId, $trackingNumberId, $ym);
        if ($row === null) {
            return false;
        }
        return $this->replaceLabel($row, $payload);
    }

    /**
     * @param mixed $order
     * @param string $carrierModule
     * @param Varien_Object $response
     */
    public function dispatchLabelResponse($order, $carrierModule, Varien_Object $response)
    {
        Mage::dispatchEvent(self::EVENT_RESPONSE_RECEIVED, array(
            'order'          => $order,
            'carrier_module' => (string)$carrierModule,
            'response'       => $response,
        ));
    }

    /**
     *
     * @param mixed $order
     * @param string $carrierModule
     * @param Varien_Object $response
     * @return int|false
     */
    public function recordFromResponse($order, $carrierModule, Varien_Object $response)
    {
        $payload = array();

        if ($order instanceof Mage_Sales_Model_Order && $order->getId()) {
            $payload['order_id'] = (int)$order->getId();
        }

        if ($response->getTrackingNumberId()) {
            $payload['tracking_number_id'] = (int)$response->getTrackingNumberId();
        }

        $labelContent = $response->getLabelContent();
        if ($labelContent !== null && $labelContent !== '') {
            $payload['label_content'] = $labelContent;
            if ($response->getLabelFormat()) {
                $payload['label_format'] = (string)$response->getLabelFormat();
            }
            if ($response->getLabelFilename()) {
                $payload['label_filename'] = (string)$response->getLabelFilename();
            }
        } else {
            $existing = $response->getLabelPath() ?: $response->getPathFile();
            if ($existing) {
                $payload['path_file'] = (string)$existing;
            }
        }

        $additional = array();
        if ($carrierModule !== '') {
            $additional['carrier_module'] = (string)$carrierModule;
        }
        $reserved = array(
            'tracking_number_id', 'label_content', 'label_path', 'path_file',
            'label_format', 'label_filename',
        );
        foreach ($response->getData() as $key => $value) {
            if (in_array($key, $reserved, true)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $additional[$key] = $value;
            }
        }
        if ($additional) {
            $payload['additional_data'] = $additional;
        }

        return $this->record($payload);
    }

    /**
     * @param int|string|null $time Unix timestamp or strtotime-compatible string
     * @return string
     */
    public function getAbsoluteDir($time = null)
    {
        $ts = $this->_toTimestamp($time);
        $dir = Mage::getBaseDir('var') . DS . self::VAR_SUBDIR . DS . $this->_formatYearMonth($ts);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /**
     * @param int|string|null $time
     * @return string
     */
    public function getRelativeDir($time = null)
    {
        $ts = $this->_toTimestamp($time);
        return self::VAR_SUBDIR . '/' . $this->_formatYearMonth($ts);
    }

    /**
     * @param XFE_LabelPrint_Model_Print $row
     * @param array $payload
     * @param string|null $relPath Pre-resolved var-relative path.
     * @param int|null $orderId
     * @param int|null $trackingNumberId
     * @param bool $isReplace When true, the current path_file is moved
     *                        to old_path_file whenever $relPath is set.
     * @return int|false
     */
    protected function _applyAndSave(
        XFE_LabelPrint_Model_Print $row,
        array $payload,
        $relPath,
        $orderId,
        $trackingNumberId,
        $isReplace
    ) {
        if ($relPath !== null && $isReplace && $row->getPathFile()) {
            $row->setOldPathFile((string)$row->getPathFile());
        }

        $row->setOrderId($orderId !== null ? $orderId : 0);
        $row->setTrackingNumberId($trackingNumberId !== null ? $trackingNumberId : 0);

        if ($relPath !== null) {
            $row->setPathFile($relPath);
        }
        if (array_key_exists('old_path_file', $payload) && $payload['old_path_file'] !== null) {
            $row->setOldPathFile((string)$payload['old_path_file']);
        }
        if (array_key_exists('additional_data', $payload) && $payload['additional_data'] !== null) {
            $row->setAdditionalDataArray($payload['additional_data']);
        }

        try {
            $row->save();
        } catch (Exception $e) {
            Mage::logException($e);
            return false;
        }

        return (int)$row->getId();
    }

    /**
     * @param array $payload
     * @return string|null
     */
    protected function _resolveRelPath(array $payload)
    {
        if (isset($payload['path_file']) && $payload['path_file'] !== '') {
            $relPath = $this->_normalizeExistingPath($payload['path_file']);
            return $relPath === false ? null : $relPath;
        }
        if (isset($payload['label_content']) && $payload['label_content'] !== '') {
            $format = isset($payload['label_format'])
                ? strtolower((string)$payload['label_format'])
                : 'pdf';
            if ($format === '') {
                $format = 'pdf';
            }
            $filenameHint = isset($payload['label_filename'])
                ? (string)$payload['label_filename']
                : '';
            $time = isset($payload['printed_at']) ? strtotime((string)$payload['printed_at']) : null;
            $relPath = $this->_storeContent($payload['label_content'], $format, $filenameHint, $time);
            return $relPath === false ? null : $relPath;
        }
        return null;
    }

    /**
     * @param int $trackingNumberId
     * @return XFE_LabelPrint_Model_Print|null
     */
    protected function _findLatestByTrackingNumber($trackingNumberId)
    {
        $trackingNumberId = (int)$trackingNumberId;
        if ($trackingNumberId <= 0) {
            return null;
        }
        $collection = Mage::getModel('xfe_labelprint/print')->getCollection()
            ->addFieldToFilter('tracking_number_id', $trackingNumberId)
            ->setOrder('id', 'DESC');
        foreach ($collection as $row) {
            return $row;
        }
        return null;
    }

    /**
     * @param string $content
     * @param string $format
     * @param string $filenameHint
     * @param int|string|null $time Timestamp for the YYYY/MM partition.
     * @return string|false
     */
    protected function _storeContent($content, $format, $filenameHint, $time = null)
    {
        $bytes = $this->_decodeContent($content);
        if ($bytes === false) {
            return false;
        }

        $ts    = $this->_toTimestamp($time);
        $dir   = $this->getAbsoluteDir($ts);
        $base  = $this->_sanitizeFilename($filenameHint) ?: 'label';
        $stamp = date('Ymd_His', $ts);
        $rand  = substr(bin2hex(random_bytes(4)), 0, 6);
        $name  = sprintf('%s_%s_%s.%s', $stamp, $rand, $base, $format);
        $abs   = $dir . DS . $name;

        $written = @file_put_contents($abs, $bytes);
        if ($written === false) {
            return false;
        }

        return self::VAR_SUBDIR . '/' . $this->_formatYearMonth($ts) . '/' . $name;
    }

    /**
     * @param string $content
     * @return string|false
     */
    protected function _decodeContent($content)
    {
        if (!is_string($content) || $content === '') {
            return false;
        }

        // Existing absolute file path
        if (is_file($content)) {
            $bytes = @file_get_contents($content);
            return $bytes === false ? false : $bytes;
        }

        // data: URL
        if (strncmp($content, 'data:', 5) === 0) {
            $semi = strpos($content, ';base64,');
            if ($semi !== false) {
                $b64 = substr($content, $semi + 8);
                $out = base64_decode($b64, true);
                return $out === false ? false : $out;
            }
            $comma = strpos($content, ',', 5);
            if ($comma !== false) {
                return rawurldecode(substr($content, $comma + 1));
            }
            return false;
        }

        // var/ or media/ relative path (preferred order: var first,
        // since that is where the helper itself stores files).
        foreach (array('var', 'media') as $baseType) {
            $candidate = Mage::getBaseDir($baseType) . DS
                . str_replace('/', DS, ltrim($content, '/'));
            if (is_file($candidate)) {
                $bytes = @file_get_contents($candidate);
                return $bytes === false ? false : $bytes;
            }
        }

        // Heuristic: looks like base64?
        $trim = trim($content);
        if ($trim !== '' && preg_match('/^[A-Za-z0-9\/+]+={0,2}$/', $trim) && strlen($trim) % 4 === 0) {
            $decoded = base64_decode($trim, true);
            if ($decoded !== false && $decoded !== '') {
                return $decoded;
            }
        }

        // Fall back to raw bytes
        return $content;
    }

    /**
     * @param string $hint
     * @return string
     */
    protected function _sanitizeFilename($hint)
    {
        $hint = (string)$hint;
        if ($hint === '') {
            return '';
        }
        $hint = preg_replace('/\.[A-Za-z0-9]{1,8}$/', '', $hint);
        $hint = preg_replace('/[^A-Za-z0-9._-]+/', '_', $hint);
        $hint = ltrim($hint, '.');
        return substr($hint, 0, 60);
    }

    /**
     * @param string $path
     * @return string|false
     */
    protected function _normalizeExistingPath($path)
    {
        $path = (string)$path;
        if ($path === '') {
            return false;
        }

        // Absolute?
        if (preg_match('#^(?:[A-Za-z]:[\\\\/]|/|[\\\\])#', $path)) {
            if (!is_file($path)) {
                return false;
            }
            foreach (array('var', 'media') as $baseType) {
                $base = Mage::getBaseDir($baseType);
                if (strncasecmp($path, $base, strlen($base)) === 0) {
                    $rel = substr($path, strlen($base));
                    return ltrim(str_replace(DS, '/', $rel), '/');
                }
            }
            return false;
        }

        // Relative: prefer var/, fall back to media/ for legacy callers.
        foreach (array('var', 'media') as $baseType) {
            $abs = Mage::getBaseDir($baseType) . DS . str_replace('/', DS, $path);
            if (is_file($abs)) {
                return ltrim(str_replace(DS, '/', $path), '/');
            }
        }

        return false;
    }

    /**
     * @param int|string|null $time
     * @return int
     */
    protected function _toTimestamp($time)
    {
        if ($time === null) {
            return time();
        }
        if (is_int($time)) {
            return $time;
        }
        if (is_string($time) && $time !== '' && ctype_digit($time)) {
            return (int)$time;
        }
        $ts = @strtotime((string)$time);
        return $ts === false ? time() : $ts;
    }

    /**
     * @param int $ts
     * @return string
     */
    protected function _formatYearMonth($ts)
    {
        return date('Y/m', (int)$ts);
    }

    /**
     * @param string $ym
     * @return string|null
     */
    protected function _normaliseYearMonth($ym = '')
    {
        $ym = trim((string)$ym);
        if ($ym === '') {
            return date('Y/m');
        }
        if (preg_match('#^(\d{4})[-/](\d{1,2})$#', $ym, $m)) {
            $y = (int)$m[1];
            $mo = (int)$m[2];
            if ($y >= 1970 && $y <= 2100 && $mo >= 1 && $mo <= 12) {
                return sprintf('%04d/%02d', $y, $mo);
            }
        }
        return null;
    }

    public function replacePathFileName($printFile)
    {
        $ym = $this->_normaliseYearMonth();
        if (!$printFile || is_null($ym)) {
            return $printFile;
        }
        $search = 'var/xlogistic/print_file/';
        return str_replace($search, 'var/xlogistic_archive/print_file/' . $ym . '/', $printFile);
    }

    public function normalizePath($path)
    {
        if ($path === null) {
            return '';
        }
        $path = (string)$path;
        $path = trim($path);
        $path = str_replace('\\', '/', $path);          // \ -> /
        $path = preg_replace('#/{2,}#', '/', $path);    // collapse /// -> /, BUT see UNC note below

        // Preserve UNC double-slash prefix (//server/share)
        $isUnc = (substr($path, 0, 2) === '//');
        $path  = preg_replace('#/{2,}#', '/', $path);
        if ($isUnc) {
            $path = '/' . $path;
        }

        // Trim trailing slash (but keep root "/" alone)
        if (strlen($path) > 1 && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    public function escapeLikeKeyword($keyword)
    {
        $keyword = $this->normalizePath($keyword);
        // Escape LIKE metacharacters using "|" as the escape char.
        return addcslashes($keyword, '|%_');
    }

}