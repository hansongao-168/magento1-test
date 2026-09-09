<?php
/**
 * 从 Mage_Sales_Model_Quote 构造 MatchContext。
 * 用于 Cart/Checkout 预览阶段。
 */
class XFE_Carrier_Model_Service_Rule_QuoteContextBuilder
{
    /** @var Mage_Sales_Model_Quote */
    protected $_quote;

    protected $_packageCount = 0;
    protected $_packageWeight = 0;
    protected $_length = 0;
    protected $_width = 0;
    protected $_height = 0;

    /**
     * 生产调用传 Mage_Sales_Model_Quote；测试用 Varien_Object 模拟，
     * 因为 Quote 继承自 Varien_Object 且仅依赖 getter 接口。
     */
    public function setQuote(Varien_Object $quote)
    {
        $this->_quote = $quote;
        return $this;
    }

    public function setPackageCount($count)   { $this->_packageCount = (int)$count;     return $this; }
    public function setPackageWeight($weight) { $this->_packageWeight = (float)$weight; return $this; }
    public function setLength($length)        { $this->_length = $length;               return $this; }
    public function setWidth($width)          { $this->_width = $width;                 return $this; }
    public function setHeight($height)        { $this->_height = $height;               return $this; }

    /**
     * @return XFE_Carrier_Model_Service_Rule_MatchContext
     */
    public function build()
    {
        $context = new XFE_Carrier_Model_Service_Rule_MatchContext();

        $shipping = $this->_quote->getShippingAddress();
        $billing  = $this->_quote->getBillingAddress();

        $context->set('country_code', $shipping ? $shipping->getCountryId() : null);
        $context->set('city',         $shipping ? $shipping->getCity() : null);
        $context->set('zip_code',     $shipping ? $shipping->getPostcode() : null);

        $context->set('billing_country_code', $billing ? $billing->getCountryId() : null);
        $context->set('billing_city',        $billing ? $billing->getCity() : null);
        $context->set('billing_region',      $billing ? $billing->getRegion() : null);
        $context->set('billing_zip',         $billing ? $billing->getPostcode() : null);

        $context->set('customer_group', $this->_quote->getCustomerGroupId());
        $context->set('user_id',        $this->_quote->getCustomerId());
        $context->set('order_amount',   (float)$this->_quote->getGrandTotal());

        $context->set('package_count',  $this->_packageCount);
        $context->set('package_weight', $this->_packageWeight);
        $context->set('length', $this->_length);
        $context->set('width',  $this->_width);
        $context->set('height', $this->_height);
        $context->set('volume', (float)($this->_length * $this->_width * $this->_height));

        return $context;
    }
}
