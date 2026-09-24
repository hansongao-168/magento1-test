<?php
require_once __DIR__ . '/../../../../../../../../app/Mage.php';
Mage::app();

class XFE_Carrier_Test_Service_Rule_QuoteContextBuilderTest extends PHPUnit_Framework_TestCase
{
    public function testMapsBillingAndShippingFromQuote()
    {
        $shipping = new Varien_Object(['country_id' => 'US', 'city' => 'NYC', 'postcode' => '10001']);
        $billing  = new Varien_Object(['country_id' => 'CN', 'city' => '北京', 'region' => '北京市', 'postcode' => '100000']);

        $quote = new Varien_Object([
            'customer_id'       => 7,
            'customer_group_id' => 1,
            'grand_total'       => 88.0,
        ]);
        $quote->setShippingAddress($shipping);
        $quote->setBillingAddress($billing);

        $builder = new XFE_Carrier_Model_Service_Rule_QuoteContextBuilder();
        $builder->setQuote($quote);
        $builder->setPackageWeight(1.2);

        $context = $builder->build();

        $this->assertSame('US', $context->get('country_code'));
        $this->assertSame('CN', $context->get('billing_country_code'));
        $this->assertSame('北京', $context->get('billing_city'));
        $this->assertSame('北京市', $context->get('billing_region'));
        $this->assertSame(7, $context->get('user_id'));
        $this->assertSame(1.2, $context->get('package_weight'));
    }
}
