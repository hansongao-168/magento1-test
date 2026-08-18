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
            case 'POST':
                if (!empty($params[0]) && strtolower($params[0]) === 'create') {
                    $this->_createOrder();
                } else {
                    $this->_helper->sendJsonError(400, 'bad_request',
                        'Unknown POST action. Use POST /api/v2/orders/create');
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
            $channel = $this->_getChannelData((int)$order->getId());
            $items[] = array(
                'increment_id'  => $order->getIncrementId(),
                'status'        => $order->getStatus(),
                'created_at'    => $order->getCreatedAt(),
                'grand_total'   => (float)$order->getGrandTotal(),
                'currency'      => $order->getOrderCurrencyCode(),
                'order_channel' => $channel['channel'],
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

        $channel = $this->_getChannelData((int)$order->getId());

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
            'order_channel'  => $channel['channel'],
            'order_channel_source' => $channel['channel_source'],
            'items'          => $items,
            'shipping_address' => $this->_getAddressData($order->getShippingAddress()),
            'billing_address'  => $this->_getAddressData($order->getBillingAddress()),
        ));
    }

    /**
     * POST /api/v2/orders/create - convert an existing quote to an order
     *
     * Expected JSON body:
     * {
     *   "cart_id"              : 123,                // required - Mage_Sales_Model_Quote id
     *   "billing_address_id"   : 45,                 // required - customer/address id
     *   "shipping_address_id"  : 45,                 // optional - defaults to billing
     *   "shipping_method"      : "freeshipping_freeshipping",  // required
     *   "payment_method"       : "checkmo",          // required
     *   "customer_note"        : "optional note"
     * }
     */
    protected function _createOrder()
    {
        $body  = $this->_getJsonBody();
        $owner = (int)$this->_tokenData['user_id'];

        // 1. Validate required fields
        $cartId    = (int)($body['cart_id'] ?? 0);
        $shipMeth  = trim((string)($body['shipping_method'] ?? ''));
        $payMeth   = trim((string)($body['payment_method']  ?? ''));
        $billAddrId = (int)($body['billing_address_id'] ?? 0);
        $shipAddrId = (int)($body['shipping_address_id'] ?? $billAddrId);

        if ($cartId <= 0) {
            $this->_helper->sendJsonError(400, 'bad_request', 'cart_id is required');
            return;
        }
        if ($billAddrId <= 0) {
            $this->_helper->sendJsonError(400, 'bad_request', 'billing_address_id is required');
            return;
        }
        if ($shipMeth === '') {
            $this->_helper->sendJsonError(400, 'bad_request', 'shipping_method is required');
            return;
        }
        if ($payMeth === '') {
            $this->_helper->sendJsonError(400, 'bad_request', 'payment_method is required');
            return;
        }

        // 2. Load quote + verify ownership
        $quote = Mage::getModel('sales/quote')->load($cartId);
        if (!$quote->getId()) {
            $this->_helper->sendJsonError(404, 'not_found', 'Cart not found');
            return;
        }
        if ((int)$quote->getCustomerId() !== $owner) {
            $this->_helper->sendJsonError(403, 'forbidden', 'Cart does not belong to you');
            return;
        }
        if ($quote->getIsActive() != 1 || !$quote->hasItems()) {
            $this->_helper->sendJsonError(409, 'conflict', 'Cart is empty or no longer active');
            return;
        }

        // 3. Apply billing address from customer's address book
        try {
            $billAddr = Mage::getModel('customer/address')->load($billAddrId);
            if (!$billAddr->getId() || (int)$billAddr->getCustomerId() !== $owner) {
                $this->_helper->sendJsonError(403, 'forbidden', 'Invalid billing address');
                return;
            }
            $quote->getBillingAddress()->importCustomerAddress($billAddr)->setSaveInAddressBook(0);

            if ($shipAddrId !== $billAddrId) {
                $shipAddr = Mage::getModel('customer/address')->load($shipAddrId);
                if (!$shipAddr->getId() || (int)$shipAddr->getCustomerId() !== $owner) {
                    $this->_helper->sendJsonError(403, 'forbidden', 'Invalid shipping address');
                    return;
                }
            } else {
                $shipAddr = $billAddr;
            }
            $quote->getShippingAddress()
                ->importCustomerAddress($shipAddr)
                ->setSaveInAddressBook(0)
                ->setCollectShippingRates(true)
                ->collectShippingRates();
            $quote->setShippingMethod($shipMeth);
        } catch (Exception $e) {
            $this->_helper->log('API order create - address error: ' . $e->getMessage());
            $this->_helper->sendJsonError(400, 'bad_request',
                'Could not apply address: ' . $e->getMessage());
            return;
        }

        // 4. Apply payment
        try {
            $payment = $quote->getPayment();
            $payment->setMethod($payMeth);
            $payment->setQuote($quote);
            // Some gateways expect data; pass any extras through verbatim
            if (!empty($body['payment_data']) && is_array($body['payment_data'])) {
                foreach ($body['payment_data'] as $k => $v) {
                    $payment->setData($k, $v);
                }
            }
        } catch (Exception $e) {
            $this->_helper->log('API order create - payment error: ' . $e->getMessage());
            $this->_helper->sendJsonError(400, 'bad_request',
                'Could not apply payment: ' . $e->getMessage());
            return;
        }

        // 5. Customer note (optional)
        if (!empty($body['customer_note'])) {
            $quote->setCustomerNote(trim((string)$body['customer_note']));
        }

        // 6. Collect totals
        $quote->setTotalsCollectedFlag(false)->collectTotals();

        // 7. Submit
        try {
            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();
        } catch (Exception $e) {
            $this->_helper->log('API order create - submit error: ' . $e->getMessage());
            $this->_helper->sendJsonError(500, 'server_error',
                'Failed to create order: ' . $e->getMessage());
            return;
        }

        $order = $service->getOrder();
        if (!$order || !$order->getId()) {
            $this->_helper->sendJsonError(500, 'server_error', 'Order was not created');
            return;
        }

        // 8. Stamp the channel identifier via the dedicated xfe_oauth2_order_channel
        //    association table. Non-fatal: order already exists, just log on failure.
        try {
            Mage::getModel('xfeoauth2/order_channel')->recordChannel(
                (int)$order->getId(),
                XFE_OAuth2_Model_Order_Channel::CHANNEL_API_OAUTH2,
                (string)$this->_tokenData['client_id'],
                (string)$this->_tokenData['client_id'],
                (int)$this->_tokenData['user_id'],
                'customer',
                null
            );
        } catch (Exception $e) {
            $this->_helper->log('API order create - channel stamp error: ' . $e->getMessage());
        }

        // 9. Send new order email (best-effort)
        try {
            if ($order->getCanSendNewEmailFlag()) {
                $order->queueNewOrderEmail();
            }
        } catch (Exception $e) {
            $this->_helper->log('API order create - email error: ' . $e->getMessage());
        }

        // 10. Invalidate the cart so the customer cannot submit twice
        try {
            $quote->setIsActive(false)->save();
        } catch (Exception $e) {
            $this->_helper->log('API order create - inactivate quote error: ' . $e->getMessage());
        }

        $channel = $this->_getChannelData((int)$order->getId());

        $this->_success(array(
            'increment_id'   => $order->getIncrementId(),
            'order_id'       => (int)$order->getId(),
            'status'         => $order->getStatus(),
            'state'          => $order->getState(),
            'grand_total'    => (float)$order->getGrandTotal(),
            'subtotal'       => (float)$order->getSubtotal(),
            'shipping_amount' => (float)$order->getShippingAmount(),
            'tax_amount'     => (float)$order->getTaxAmount(),
            'discount_amount' => (float)$order->getDiscountAmount(),
            'currency'       => $order->getOrderCurrencyCode(),
            'order_channel'  => $channel['channel'],
            'order_channel_source' => $channel['channel_source'],
        ), array('cart_id' => $cartId));
    }

    /**
     * Read the channel mapping for an order from xfe_oauth2_order_channel.
     * Returns array with 'channel' (default 'web') and 'channel_source' (null)
     * if the order has no channel record.
     *
     * @param int $orderId
     * @return array{channel: string, channel_source: string|null}
     */
    protected function _getChannelData($orderId)
    {
        if (!$orderId) {
            return array('channel' => 'web', 'channel_source' => null);
        }
        try {
            $record = Mage::getModel('xfeoauth2/order_channel')->loadByOrderId((int)$orderId);
            if ($record->getId()) {
                return array(
                    'channel'        => $record->getChannel(),
                    'channel_source' => $record->getChannelSource(),
                );
            }
        } catch (Exception $e) {
            $this->_helper->log('Order channel lookup error: ' . $e->getMessage());
        }
        return array('channel' => 'web', 'channel_source' => null);
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
