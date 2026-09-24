<?php

class Mage_Core_Helper_Abstract
{
    public function __($value)
    {
        return $value;
    }
}

class Mage
{
    private static $_helper;

    public static function helper($name)
    {
        if (!self::$_helper) {
            require_once __DIR__ . '/../../Helper/Data.php';
            self::$_helper = new XFE_Carrier_Helper_Data();
        }

        return self::$_helper;
    }
}

class Mage_Core_Model_Abstract
{
    protected $_data = array();

    public function setData($key, $value = null)
    {
        if (is_array($key)) {
            $this->_data = array_merge($this->_data, $key);
        } else {
            $this->_data[$key] = $value;
        }

        return $this;
    }

    public function getData($key = null)
    {
        if ($key === null) {
            return $this->_data;
        }

        return isset($this->_data[$key]) ? $this->_data[$key] : null;
    }

    protected function _getData($key)
    {
        return $this->getData($key);
    }
}

spl_autoload_register(function ($class) {
    if (strpos($class, 'XFE_Carrier_Model_') !== 0) {
        return;
    }

    $parts = explode('_', $class);
    array_shift($parts);
    array_shift($parts);
    array_shift($parts);
    $path = implode('/', $parts) . '.php';
    $candidate = __DIR__ . '/../../Model/' . $path;
    if (file_exists($candidate)) {
        require_once $candidate;
    }
});

$failed = 0;

function assertConditionDescription($expected, $actual, $label)
{
    global $failed;
    if ($expected === $actual) {
        echo '[PASS] ' . $label . PHP_EOL;
        return;
    }

    $failed++;
    echo '[FAIL] ' . $label . ' - expected ' . var_export($expected, true)
        . ', got ' . var_export($actual, true) . PHP_EOL;
}

$helper = Mage::helper('xfe_carrier');
assertConditionDescription('订单创建时间', $helper->getConditionAttributeOptions()['order_created_at'], 'order created time attribute label');
assertConditionDescription('datetime', $helper->getAttributeTypeMap()['order_created_at'], 'order created time attribute type');
$datetimeOperators = $helper->getDatetimeOperators();
assertConditionDescription(null, isset($datetimeOperators['in']) ? $datetimeOperators['in'] : null, 'datetime operators do not expose string-only in');
assertConditionDescription('范围 (x~y)', $datetimeOperators['between'], 'datetime operators expose range');

$rule = new XFE_Carrier_Model_Carrier_Rule();
$rule->setConditionsData(array(
    array(
        'aggregator' => 'all',
        'conditions' => array(
            array('attribute' => 'country_code', 'operator' => '==', 'value' => 'US'),
            array('attribute' => 'order_amount', 'operator' => '>=', 'value' => '100'),
        ),
    ),
));
assertConditionDescription('目的地国家 等于 US 且 订单金额 大于等于 100', $rule->getConditionsDescription(), 'flat condition description');

$nestedRule = new XFE_Carrier_Model_Carrier_Rule();
$nestedRule->setConditionsData(array(
    array(
        'aggregator' => 'any',
        'conditions' => array(
            array('attribute' => 'city', 'operator' => 'contains', 'value' => 'New'),
            array(
                'type' => 'group',
                'aggregator' => 'all',
                'conditions' => array(
                    array('attribute' => 'order_created_at', 'operator' => '>', 'value' => '2026-08-20 09:00:00'),
                    array('attribute' => 'user_id', 'operator' => 'is_not_null'),
                ),
            ),
        ),
    ),
));
assertConditionDescription('目的城市 包含 New 或 （订单创建时间 大于 2026-08-20 09:00:00 且 用户ID 不为空）', $nestedRule->getConditionsDescription(), 'nested condition description');

$unknownRule = new XFE_Carrier_Model_Carrier_Rule();
$unknownRule->setConditionsData(array(
    array(
        'aggregator' => 'all',
        'conditions' => array(
            array('attribute' => 'legacy_attribute', 'operator' => 'is_null'),
        ),
    ),
));
assertConditionDescription('legacy_attribute 为空', $unknownRule->getConditionsDescription(), 'unknown attribute and null operator description');

$emptyRule = new XFE_Carrier_Model_Carrier_Rule();
$emptyRule->setConditionsData(array());
assertConditionDescription('匹配所有', $emptyRule->getConditionsDescription(), 'empty condition description');

if ($failed > 0) {
    exit(1);
}

echo 'ALL CONDITION DESCRIPTION TESTS PASSED' . PHP_EOL;
