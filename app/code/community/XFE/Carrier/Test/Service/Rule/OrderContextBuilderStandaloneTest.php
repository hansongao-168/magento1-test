<?php

class Varien_Object
{
    private $_data = array();

    public function __construct(array $data = array())
    {
        $this->_data = $data;
    }

    public function set($key, $value)
    {
        $this->_data[$key] = $value;
        return $this;
    }

    public function setShippingAddress($address)
    {
        $this->_data['shipping_address'] = $address;
        return $this;
    }

    public function getShippingAddress()
    {
        return isset($this->_data['shipping_address']) ? $this->_data['shipping_address'] : null;
    }

    public function setBillingAddress($address)
    {
        $this->_data['billing_address'] = $address;
        return $this;
    }

    public function getBillingAddress()
    {
        return isset($this->_data['billing_address']) ? $this->_data['billing_address'] : null;
    }

    public function getCustomerGroupId()
    {
        return isset($this->_data['customer_group_id']) ? $this->_data['customer_group_id'] : null;
    }

    public function getCustomerId()
    {
        return isset($this->_data['customer_id']) ? $this->_data['customer_id'] : null;
    }

    public function getGrandTotal()
    {
        return isset($this->_data['grand_total']) ? $this->_data['grand_total'] : 0;
    }

    public function getCreatedAt()
    {
        return isset($this->_data['created_at']) ? $this->_data['created_at'] : null;
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
    $candidate = __DIR__ . '/../../../Model/' . $path;
    if (file_exists($candidate)) {
        require_once $candidate;
    }
});

$order = new Varien_Object(array('created_at' => '2026-08-20 10:30:00'));
$context = (new XFE_Carrier_Model_Service_Rule_OrderContextBuilder())
    ->setOrder($order)
    ->build();

if ($context->get('order_created_at') !== '2026-08-20 10:30:00') {
    echo 'order created time mapping failed' . PHP_EOL;
    exit(1);
}

echo 'ORDER CONTEXT BUILDER TEST PASSED' . PHP_EOL;
