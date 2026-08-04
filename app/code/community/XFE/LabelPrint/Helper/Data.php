<?php

/**
 * XFE Label Print Helper
 *
 * Public API other modules use to record that a label was produced for
 * a shipment / tracking number. Persists the label file under
 * var/xfe/labelprint/YYYY/MM/ and writes (or updates) a row in
 * xfe_label_print so admins can trace the print history and download
 * the file again from the grid.
 *
 * Files live under var/ rather than media/ because media/ is served
 * directly by the web server; var/ is not, so the stored labels can
 * only be retrieved through the admin downloadAction and cannot be
 * discovered / scraped by URL guessing. The YYYY/MM sub-directories
 * keep individual months from accumulating tens of thousands of files.
 *
 * Three public entry points are provided:
 *
 *   1. record($payload)             direct, structured call
 *   2. dispatchLabelResponse(...)   dispatches the
 *                                  "xfe_labelprint_response_received"
 *                                  event; the bundled observer turns
 *                                  the payload into a record()
 *   3. replaceLabel / replaceByMonth
 *                                  re-print semantics: the existing
 *                                  path_file is moved to old_path_file
 *                                  and the new file takes over
 *
 * Callers that prefer loose coupling (typical for cross-module
 * integration with a carrier / print module) should dispatch the
 * event. Callers that already hold all data locally can call
 * recordFromResponse() directly to skip the event round-trip.
 *
 * event payload keys (Varien_Object under "response"):
 *   - tracking_number_id (int)    preferred
 *   - label_content (string)      raw / base64 / data:URL / filesystem path
 *   - label_path / path_file      caller already placed the file
 *   - label_format / label_filename
 *   - any other scalar key        copied into additional_data
 */
class XFE_LabelPrint_Helper_Data extends Mage_Core_Helper_Abstract
{
    /** Sub-directory under var/ where label files are stored. */
    const VAR_SUBDIR = 'xfe/labelprint';

    /** Name of the dispatched event. */
    const EVENT_RESPONSE_RECEIVED = 'xfe_labelprint_response_received';

    /**
     * Direct, structured entry point. See class docblock for accepted
     * keys.
     *
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
     * Look up the most recent printed label for an order / tracking
     * number in a given year-month partition. Useful when a carrier
     * re-prints a label and we want to know which file on disk was
     * the previous artifact before overwriting it.
     *
     * Matching rules:
     *   - at least one of $orderId / $trackingNumberId must be non-zero
     *   - $ym accepts "YYYY-MM" or "YYYY/MM"; defaults to the current
     *     month when empty
     *   - only rows whose path_file starts with xfe/labelprint/{ym}/
     *     are considered (the partition the caller asked about)
     *   - tie-break: most recent id first
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
     * Replace the file of an existing row. The current path_file is
     * moved to old_path_file (so admins can still download the prior
     * artifact) and the new file is stored under
     * var/xfe/labelprint/YYYY/MM/.
     *
     * The existing order_id / tracking_number_id are preserved.
     *
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
     * Convenience wrapper around findLatestByMonth() + replaceLabel().
     *
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
     * Dispatch the "label response received" event so other modules can
     * observe it without a hard dependency on this helper.
     *
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
     * Same payload shape as dispatchLabelResponse(), but records the
     * row directly. Useful when the caller already has the data and
     * does not need the event round-trip.
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
     * Resolve the absolute filesystem directory where label files are
     * stored for a given (year, month). Created on demand. Defaults to
     * the current month.
     *
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
     * Relative directory name (relative to var/, no trailing slash).
     *
     * @param int|string|null $time
     * @return string
     */
    public function getRelativeDir($time = null)
    {
        $ts = $this->_toTimestamp($time);
        return self::VAR_SUBDIR . '/' . $this->_formatYearMonth($ts);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Apply payload to a row and save it. Returns the saved id.
     *
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
     * Resolve $payload into a var-relative path string, or null when
     * the payload has no file to store.
     *
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
     * Look up the most recent row for a tracking number. Returns null
     * when no row exists.
     *
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
     * Persist raw / base64 / path-based content to disk and return the
     * var-relative path (under YYYY/MM/). Returns false on failure.
     *
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
     * Decode label_content into raw bytes. Accepts:
     *   - raw binary
     *   - base64 (with or without the data: URL prefix)
     *   - an absolute filesystem path (returns the file's bytes)
     *   - a path that exists under either var/ or media/ (joined with
     *     the matching base dir)
     *
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
     * Strip any directory traversal / dangerous characters from a caller
     * supplied filename hint. Returns a safe basename (without extension).
     *
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
     * Turn a caller-supplied path into a var-relative path. Accepts
     * absolute paths and var-relative / media-relative paths. Returns
     * false when the file does not exist under any writable base.
     *
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
     * Coerce a "time" value into a Unix timestamp. Accepts int,
     * numeric string, strtotime-compatible string, or null (now).
     *
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
     * Format a Unix timestamp as a YYYY/MM partition string.
     *
     * @param int $ts
     * @return string
     */
    protected function _formatYearMonth($ts)
    {
        return date('Y/m', (int)$ts);
    }

    /**
     * Normalise a "YYYY-MM" or "YYYY/MM" string into "YYYY/MM".
     * Returns null when the value is not a usable month.
     *
     * @param string $ym
     * @return string|null
     */
    protected function _normaliseYearMonth($ym)
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
}
