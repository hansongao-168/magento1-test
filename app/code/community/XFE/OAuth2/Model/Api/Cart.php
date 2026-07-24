<?php
/**
 * Cart API - scope: basic (requires customer-level token)
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Api_Cart extends XFE_OAuth2_Model_Api_Abstract
{
    protected $_requiredScope = 'basic';
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
                $this->_getCart();
                break;
            case 'POST':
                $this->_addToCart();
                break;
            default:
                $this->_helper->sendJsonError(405, 'bad_request', 'Method not allowed');
        }
    }

    /**
     * GET /api/v2/cart/items - list cart items
     */
    protected function _getCart()
    {
        $quote = $this->_getQuote();
        $items = array();

        foreach ($quote->getAllVisibleItems() as $item) {
            $items[] = array(
                'item_id'    => $item->getId(),
                'product_id' => $item->getProductId(),
                'sku'        => $item->getSku(),
                'name'       => $item->getName(),
                'qty'        => (int)$item->getQty(),
                'price'      => (float)$item->getPrice(),
                'row_total'  => (float)$item->getRowTotal(),
            );
        }

        $this->_success(array(
            'items_count'   => $quote->getItemsCount(),
            'items_qty'     => (int)$quote->getItemsQty(),
            'subtotal'      => (float)$quote->getSubtotal(),
            'grand_total'   => (float)$quote->getGrandTotal(),
            'currency'      => $quote->getQuoteCurrencyCode(),
            'items'         => $items,
        ));
    }

    /**
     * POST /api/v2/cart - add item to cart
     */
    protected function _addToCart()
    {
        $request = Mage::app()->getRequest();
        $productId = (int)$request->getParam('product_id');
        $qty = max(1, (int)$request->getParam('qty', 1));

        if (!$productId) {
            $this->_helper->sendJsonError(400, 'bad_request', 'product_id is required');
            return;
        }

        $product = Mage::getModel('catalog/product')->load($productId);
        if (!$product->getId() || !$product->isSaleable()) {
            $this->_helper->sendJsonError(400, 'bad_request', 'Product is not available');
            return;
        }

        $quote = $this->_getQuote();

        try {
            $quote->addProduct($product, $qty);
            $quote->collectTotals()->save();

            $this->_helper->sendJson(200, array(
                'success' => true,
                'message' => 'Product added to cart',
                'product' => array(
                    'id'   => $product->getId(),
                    'sku'  => $product->getSku(),
                    'name' => $product->getName(),
                    'qty'  => $qty,
                ),
            ));
        } catch (Exception $e) {
            $this->_helper->sendJsonError(400, 'bad_request', $e->getMessage());
        }
    }

    /**
     * Get or create quote for current customer
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote()
    {
        $customerId = $this->_tokenData['user_id'];
        $storeId = Mage::app()->getStore()->getId();

        $quote = Mage::getModel('sales/quote')
            ->setStoreId($storeId)
            ->loadByCustomer($customerId);

        if (!$quote->getId()) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            $quote->assignCustomer($customer);
            $quote->setStoreId($storeId);
            $quote->save();
        }

        return $quote;
    }
}
