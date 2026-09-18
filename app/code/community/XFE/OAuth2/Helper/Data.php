<?php
/**
 * XFE_OAuth2 Helper
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Register bundled library autoloader on construction
     */
    protected function _construct()
    {
        require_once BP . '/lib/XFE/OAuth2/Autoloader.php';
        XFE_OAuth2_Autoloader::register();
        parent::_construct();
    }

    /**
     * Generate a UUID v4 string
     *
     * @return string
     */
    public function generateUuid()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Generate a random token string
     *
     * @param int $length
     * @return string
     */
    public function generateToken($length = 40)
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Generate bcrypt hash of client secret
     *
     * @param string $secret
     * @return string
     */
    public function hashSecret($secret)
    {
        return password_hash($secret, PASSWORD_BCRYPT);
    }

    /**
     * Verify bcrypt hash against plain secret
     *
     * @param string $secret
     * @param string $hash
     * @return bool
     */
    public function verifySecret($secret, $hash)
    {
        return password_verify($secret, $hash);
    }

    /**
     * Encrypt sensitive data for storage
     *
     * @param string $data
     * @return string
     */
    public function encryptData($data)
    {
        return Mage::helper('core')->encrypt($data);
    }

    /**
     * Decrypt sensitive data from storage
     *
     * @param string $data
     * @return string
     */
    public function decryptData($data)
    {
        return Mage::helper('core')->decrypt($data);
    }

    /**
     * Send JSON response
     *
     * @param int $code
     * @param mixed $data
     * @param array $meta
     * @return void
     */
    public function sendJson($code = 200, $data = null, array $meta = null)
    {
        $response = array('code' => $code);

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        Mage::app()->getResponse()
            ->clearHeaders()
            ->setHeader('Content-Type', 'application/json', true)
            ->setHttpResponseCode($code)
            ->setBody(json_encode($response))
            ->sendResponse();
        exit;
    }

    /**
     * Send JSON error response
     *
     * @param int $code
     * @param string $error
     * @param string $message
     * @return void
     */
    public function sendJsonError($code = 400, $error = 'bad_request', $message = '')
    {
        $response = array(
            'code' => $code,
            'error' => $error,
            'message' => $message
        );

        Mage::app()->getResponse()
            ->clearHeaders()
            ->setHeader('Content-Type', 'application/json', true)
            ->setHttpResponseCode($code)
            ->setBody(json_encode($response))
            ->sendResponse();
        exit;
    }

    /**
     * Get OAuth2 config value
     *
     * @param string $path
     * @param int|null $storeId
     * @return mixed
     */
    public function getConfig($path, $storeId = null)
    {
        return Mage::getStoreConfig('xfeoauth2/' . $path, $storeId);
    }

    /**
     * Log OAuth2 related messages
     *
     * @param string $message
     * @param int $level
     * @return void
     */
    public function log($message, $level = null)
    {
        Mage::log($message, $level, 'xfeoauth2.log', true);
    }

    /**
     * Canonical list of grant type identifiers known to this server.
     *
     * Used by both the admin and storefront save flows to sanitize the
     * `grant_types` column. Adding a new grant type here will also allow the
     * storage layer to admit it via {@see self::isAllowedGrantType()}.
     *
     * @return string[]
     */
    public function getAllowedGrantTypes()
    {
        return array(
            'authorization_code',
            'client_credentials',
            'refresh_token',
        );
    }

    /**
     * Membership check against the canonical grant type list, case-insensitive.
     *
     * @param string $grantType
     * @return bool
     */
    public function isAllowedGrantType($grantType)
    {
        $grantType = strtolower(trim((string)$grantType));
        return in_array($grantType, $this->getAllowedGrantTypes(), true);
    }

    /**
     * Normalize a grant_types submission into a canonical comma-separated
     * string of allowed, lowercase, deduplicated identifiers.
     *
     * Accepts any of: array, comma-separated string, newline-separated string,
     * whitespace-separated string, mixed punctuation. Order is preserved by
     * first-seen.
     *
     * Examples:
     *   'client_credentials , refresh_token , BOGUS'
     *     => 'client_credentials,refresh_token'
     *   "client_credentials\nrefresh_token"
     *     => 'client_credentials,refresh_token'
     *   array('client_credentials', 'REFRESH_TOKEN')
     *     => 'client_credentials,refresh_token'
     *
     * @param string|array $raw
     * @return string  comma-separated, lowercased, deduped; '' if nothing allowed
     */
    public function normalizeGrantTypes($raw)
    {
        if (is_array($raw)) {
            $raw = implode(',', $raw);
        }
        if (!is_string($raw)) {
            $raw = (string)$raw;
        }

        // Split on any whitespace OR comma; case-fold; trim punctuation.
        $tokens = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_map('strtolower', array_map('trim', $tokens));

        $allowed = array_map('strtolower', $this->getAllowedGrantTypes());

        $seen = array();
        $out  = array();
        foreach ($tokens as $tok) {
            if ($tok === '' || isset($seen[$tok])) {
                continue;
            }
            if (!in_array($tok, $allowed, true)) {
                // drop unknown grant types silently - keeps junk out of the DB
                continue;
            }
            $seen[$tok] = true;
            $out[]      = $tok;
        }

        return implode(',', $out);
    }


    /**
     * Default client_secret TTL in days.
     *
     * Source of truth is the system config node
     * `xfeoauth2/general/client_secret_ttl_days`. The value is clamped to
     * the inclusive range [1, 3650] (10 years) and falls back to 90 when
     * the config is missing, not numeric, or out of range. We deliberately
     * do NOT pin a default in system.xml so this helper is the single
     * source of truth - that way an operator who deletes the config still
     * gets the documented 90-day default.
     *
     * @param int|null $storeId
     * @return int
     */
    public function getDefaultSecretTtlDays($storeId = null)
    {
        $default = 90;
        $raw     = $this->getConfig('general/client_secret_ttl_days', $storeId);
        if ($raw === null || $raw === '') {
            return $default;
        }
        $value = (int)$raw;
        if ($value < 1) {
            return $default;
        }
        if ($value > 3650) {
            return $default;
        }
        return $value;
    }

    /**
     * Compute the SQL DATETIME string for `now + ttlDays`.
     *
     * Centralised here so the controller/Grid layers never have to reason
     * about timezone or `time()` vs `now()` directly.
     *
     * @param int $ttlDays
     * @return string  'YYYY-MM-DD HH:MM:SS' in UTC
     */
    public function calcSecretExpiresAt($ttlDays)
    {
        $ttl = max(1, (int)$ttlDays);
        return gmdate('Y-m-d H:i:s', time() + $ttl * 86400);
    }

    /**
     * Rotate the client_secret on a Client model: mint a new raw token,
     * re-write the bcrypt hash + AES recoverable copy, and refresh the
     * expires_at / last_rotated_at / updated_at columns. Returns the
     * plaintext new secret so the caller can show it to the operator.
     *
     * @param XFE_OAuth2_Model_Client $client  must already be loaded
     * @return string  the new plaintext client_secret
     */
    public function rotateClientSecret(XFE_OAuth2_Model_Client $client)
    {
        $secret = $this->generateToken(32);
        $now    = gmdate('Y-m-d H:i:s');
        $ttl    = $this->getDefaultSecretTtlDays();

        $client->setClientSecret($this->hashSecret($secret));
        $client->setClientSecretEncrypted($this->encryptData($secret));
        $client->setClientSecretExpiresAt($this->calcSecretExpiresAt($ttl));
        $client->setClientSecretLastRotatedAt($now);
        $client->setUpdatedAt($now);
        $client->save();

        return $secret;
    }

    /**
     * UI-only "is this secret past its expiry?" check.
     *
     * Does NOT influence token issuance (Storage\ClientCredentials keeps
     * verifying the bcrypt hash and ignores expires_at - see ADR 0007).
     * This is purely so the Grid / list template can render a red "Expired"
     * badge and so the regenerate button copy can warn the operator.
     *
     * Semantics:
     *   - NULL expires_at   => false (unknown legacy row, treat as not expired)
     *   - expires_at < now  => true
     *   - expires_at >= now => false
     *
     * @param XFE_OAuth2_Model_Client $client
     * @return bool
     */
    public function isClientSecretExpired(XFE_OAuth2_Model_Client $client)
    {
        $expiresAt = $client->getClientSecretExpiresAt();
        if ($expiresAt === null || $expiresAt === '') {
            return false;
        }
        $ts = strtotime((string)$expiresAt);
        if ($ts === false) {
            return false;
        }
        return $ts < time();
    }

    /**
     * Number of whole days until the secret expires.
     *
     * Negative if the secret is already past expiry, null if the row has
     * no expires_at (legacy client). Used by the Grid / list template to
     * pick a row color: green > 30, yellow 0..30, red < 0, grey null.
     *
     * @param XFE_OAuth2_Model_Client $client
     * @return int|null
     */
    public function getSecretDaysUntilExpiry(XFE_OAuth2_Model_Client $client)
    {
        $expiresAt = $client->getClientSecretExpiresAt();
        if ($expiresAt === null || $expiresAt === '') {
            return null;
        }
        $ts = strtotime((string)$expiresAt);
        if ($ts === false) {
            return null;
        }
        $diffSeconds = $ts - time();
        return (int)floor($diffSeconds / 86400);
    }
}
