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
}
