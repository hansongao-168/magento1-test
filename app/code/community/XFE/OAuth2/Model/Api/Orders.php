<?php
/**
 * Orders API - scope: orders
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Api_Orders extends XFE_OAuth2_Model_Api_Abstract
{
    protected $_requiredScope = 'orders';
    protected $_requireCustomer = true;

    /**
     * @param string $method
     * @param array $params
     * @return void
     */
    public function dispatch($method, array $params = array())
    {
        if (!$this->authenticate()) {
            return;
        }

        switch (strtoupper($method)) {
            case 'GET':
                if (!empty($params[0])) {
                    $this->_getOrder((int)$params[0]);
                } else {
                    $this->_getOrders();
                }
                break;
            default:
                $this->_helper->sendJsonError(405, 'bad_request', 'Method not allowed');
        }
    }

    /**
     * GET /api/v2/orders - list customer's orders
     */
    protected function _getOrders()
    {
        $page = $this->_getPage();
        $limit = $this->_getLimit();
        $customerId = $this->_tokenData['user_id'];

        $orders = Mage::getResourceModel('sales/order_collection')
            ->addFieldToSelect(array('increment_id', 'created_at', 'status', 'grand_total', 'order_currency_code'))
            ->addFieldToFilter('customer_id', $customerId)
            ->setOrder('created_at', 'DESC')
            ->setPage($page, $limit)
            ->load();

        $items = array();
        foreach ($orders as $order) {
            $items[] = array(
                'increment_id'  => $order->getIncrementId(),
                'status'        => $order->getStatus(),
                'created_at'    => $order->getCreatedAt(),
                'grand_total'   => (float)$order->getGrandTotal(),
                'currency'      => $order->getOrderCurrencyCode(),
            );
        }

        $this->_paginated($items, $page, $limit, $orders->getSize());
    }

    /**
     * GET /api/v2/orders/:id - order detail
     */
    protected function _getOrder($id)
    {
        $order = Mage::getModel('sales/order')->loadByIncrementId($id);
        if (!$order->getId()) {
            $order = Mage::getModel('sales/order')->load($id);
        }

        if (!$order->getId()) {
            $this->_helper->sendJsonError(404, 'not_found', 'Order not found');
            return;
        }

        // Verify ownership
        if ($order->getCustomerId() != $this->_tokenData['user_id']) {
            $this->_helper->sendJsonError(403, 'forbidden', 'Order does not belong to you');
            return;
        }

        $items = array();
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = array(
                'sku'   => $item->getSku(),
                'name'  => $item->getName(),
                'qty'   => (int)$item->getQtyOrdered(),
                'price' => (float)$item->getPrice(),
                'row_total' => (float)$item->getRowTotal(),
            );
        }

        $this->_success(array(
            'increment_id'   => $order->getIncrementId(),
            'status'         => $order->getStatus(),
            'state'          => $order->getState(),
            'created_at'     => $order->getCreatedAt(),
            'grand_total'    => (float)$order->getGrandTotal(),
            'subtotal'       => (float)$order->getSubtotal(),
            'shipping_amount' => (float)$order->getShippingAmount(),
            'tax_amount'     => (float)$order->getTaxAmount(),
            'discount_amount' => (float)$order->getDiscountAmount(),
            'currency'       => $order->getOrderCurrencyCode(),
            'items'          => $items,
            'shipping_address' => $this->_getAddressData($order->getShippingAddress()),
            'billing_address'  => $this->_getAddressData($order->getBillingAddress()),
        ));
    }

    /**
     * @param Mage_Sales_Model_Order_Address|null $address
     * @return array|null
     */
    protected function _getAddressData($address)
    {
        if (!$address) {
            return null;
        }
        return array(
            'firstname' => $address->getFirstname(),
            'lastname'  => $address->getLastname(),
            'street'    => $address->getStreetFull(),
            'city'      => $address->getCity(),
            'region'    => $address->getRegion(),
            'postcode'  => $address->getPostcode(),
            'country'   => $address->getCountry(),
            'telephone' => $address->getTelephone(),
        );
    }
}
