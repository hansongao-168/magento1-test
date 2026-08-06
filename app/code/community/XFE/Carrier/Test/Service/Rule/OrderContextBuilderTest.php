<?php
require_once __DIR__ . '/../../../../../../../../app/Mage.php';
Mage::app();

class XFE_Carrier_Test_Service_Rule_OrderContextBuilderTest extends PHPUnit_Framework_TestCase
{
    public function testMapsBillingAndShippingAddresses()
    {
        $shipping = new Varien_Object([
            'country_id' => 'US',
            'city'       => 'New York',
            'postcode'   => '10001',
        ]);
        $billing = new Varien_Object([
            'country_id' => 'CN',
            'city'       => '上海',
            'region'     => '上海市',
            'postcode'   => '200000',
        ]);
        $order = new Varien_Object([
            'customer_id'       => 42,
            'customer_group_id' => 4,
            'grand_total'       => 199.5,
        ]);
        $order->setShippingAddress($shipping);
        $order->setBillingAddress($billing);

        $builder = new XFE_Carrier_Model_Service_Rule_OrderContextBuilder();
        $builder->setOrder($order);
        $builder->setPackageCount(2);
        $builder->setPackageWeight(3.5);
        $builder->setLength(10);
        $builder->setWidth(20);
        $builder->setHeight(30);

        $context = $builder->build();

        $this->assertSame('US', $context->get('country_code'));
        $this->assertSame('CN', $context->get('billing_country_code'));
        $this->assertSame('上海', $context->get('billing_city'));
        $this->assertSame('上海市', $context->get('billing_region'));
        $this->assertSame('200000', $context->get('billing_zip'));
        $this->assertSame(42, $context->get('user_id'));
        $this->assertSame(4, $context->get('customer_group'));
        $this->assertSame(199.5, $context->get('order_amount'));
        $this->assertSame(2, $context->get('package_count'));
        $this->assertSame(3.5, $context->get('package_weight'));
        $this->assertSame(10, $context->get('length'));
        $this->assertSame(6000.0, $context->get('volume'));
    }

    public function testMissingAddressYieldsNull()
    {
        $order = new Varien_Object([
            'customer_id' => null,
            'grand_total' => 0,
        ]);
        $builder = new XFE_Carrier_Model_Service_Rule_OrderContextBuilder();
        $builder->setOrder($order);

        $context = $builder->build();

        $this->assertNull($context->get('billing_country_code'));
        $this->assertNull($context->get('user_id'));
    }
}
