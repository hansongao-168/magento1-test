<?php
/**
 * 从 Mage_Sales_Model_Order 构造 XFE_Carrier 规则 MatchContext。
 *
 * 收货字段直接从 shipping address 取；
 * 账单字段从 billing address 取；
 * 用户 ID、客户组取自 Order 自身；
 * 包裹尺寸由调用方注入，volume 由 length*width*height 计算。
 */
class XFE_Carrier_Model_Service_Rule_OrderContextBuilder
{
    /** @var Mage_Sales_Model_Order */
    protected $_order;

    protected $_packageCount = 0;
    protected $_packageWeight = 0;
    protected $_length = 0;
    protected $_width = 0;
    protected $_height = 0;

    /**
     * 生产调用传 Mage_Sales_Model_Order；测试用 Varien_Object 模拟同样可用，
     * 因为 Order 继承自 Varien_Object 且仅依赖 getter 接口
     * (getShippingAddress / getBillingAddress / getCustomerId /
     * getCustomerGroupId / getGrandTotal)。
     */
    public function setOrder(Varien_Object $order)
    {
        $this->_order = $order;
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

        $shipping = $this->_order->getShippingAddress();
        $billing  = $this->_order->getBillingAddress();

        $context->set('country_code', $shipping ? $shipping->getCountryId() : null);
        $context->set('city',         $shipping ? $shipping->getCity() : null);
        $context->set('zip_code',     $shipping ? $shipping->getPostcode() : null);

        $context->set('billing_country_code', $billing ? $billing->getCountryId() : null);
        $context->set('billing_city',        $billing ? $billing->getCity() : null);
        $context->set('billing_region',      $billing ? $billing->getRegion() : null);
        $context->set('billing_zip',         $billing ? $billing->getPostcode() : null);

        $context->set('customer_group', $this->_order->getCustomerGroupId());
        $context->set('user_id',        $this->_order->getCustomerId());
        $context->set('order_amount',   (float)$this->_order->getGrandTotal());

        $context->set('package_count',   $this->_packageCount);
        $context->set('package_weight',  $this->_packageWeight);
        $context->set('length', $this->_length);
        $context->set('width',  $this->_width);
        $context->set('height', $this->_height);
        $context->set('volume', (float)($this->_length * $this->_width * $this->_height));

        return $context;
    }
}
