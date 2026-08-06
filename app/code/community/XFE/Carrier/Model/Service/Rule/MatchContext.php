<?php

/**
 * MatchContext DTO
 *
 * Immutable snapshot of the data we know about a shipment / order / request
 * at the moment we want to pick an account or a logo. The Resolver feeds
 * this object to the Evaluator; nothing about persistence leaks in here.
 *
 * Allowed attributes (mirrors Helper::getConditionAttributeOptions()):
 *   country_code          string
 *   city                  string
 *   zip_code              string
 *   package_count         int|float
 *   package_weight        float
 *   length                float
 *   width                 float
 *   height                float
 *   volume                float
 *   order_amount          float
 *   customer_group        string|int
 *   user_id               int
 *   billing_country_code  string
 *   billing_city          string
 *   billing_region        string
 *   billing_zip           string
 *
 * Callers should use the named setters OR the create() factory. Any
 * extra key passed in is preserved verbatim so the rule engine stays
 * forward-compatible with new condition attributes.
 */
class XFE_Carrier_Model_Service_Rule_MatchContext
{
    /** @var array<string,mixed> */
    protected $_values = array();

    public function __construct(array $values = array())
    {
        $this->_values = $values;
    }

    /**
     * Named-argument factory for readability at call sites.
     *
     * @param array<string,mixed> $values
     * @return self
     */
    public static function create(array $values = array())
    {
        return new self($values);
    }

    /**
     * Generic getter. Returns null when the key is absent.
     *
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        return isset($this->_values[$key]) ? $this->_values[$key] : null;
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @return self
     */
    public function with($key, $value)
    {
        $copy = clone $this;
        $copy->_values[$key] = $value;
        return $copy;
    }

    /**
     * In-place setter; null clears the slot.
     *
     * @param string $key
     * @param mixed  $value
     * @return self
     */
    public function set($key, $value)
    {
        $this->_values[$key] = $value;
        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
    {
        return $this->_values;
    }
}
