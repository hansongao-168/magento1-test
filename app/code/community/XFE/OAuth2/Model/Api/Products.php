<?php
/**
 * Products API - scope: basic
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Api_Products extends XFE_OAuth2_Model_Api_Abstract
{
    protected $_requiredScope = 'basic';

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
                    $this->_getProduct((int)$params[0]);
                } else {
                    $this->_getProducts();
                }
                break;
            default:
                $this->_helper->sendJsonError(405, 'bad_request', 'Method not allowed');
        }
    }

    /**
     * GET /api/v2/products - list products
     */
    protected function _getProducts()
    {
        $page = $this->_getPage();
        $limit = $this->_getLimit();

        $collection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect(array('name', 'sku', 'price', 'thumbnail', 'url_key'))
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('visibility', array('in' => array(2, 3, 4)))
            ->setPage($page, $limit)
            ->load();

        $items = array();
        foreach ($collection as $product) {
            $items[] = array(
                'id'          => $product->getId(),
                'sku'         => $product->getSku(),
                'name'        => $product->getName(),
                'price'       => (float)$product->getPrice(),
                'currency'    => Mage::app()->getStore()->getCurrentCurrencyCode(),
                'url'         => $product->getProductUrl(),
                'image'       => Mage::helper('catalog/image')->init($product, 'thumbnail')->resize(150)->__toString(),
            );
        }

        $this->_paginated($items, $page, $limit, $collection->getSize());
    }

    /**
     * GET /api/v2/products/:id - product detail
     */
    protected function _getProduct($id)
    {
        $product = Mage::getModel('catalog/product')->load($id);
        if (!$product->getId()) {
            $this->_helper->sendJsonError(404, 'not_found', 'Product not found');
            return;
        }

        $this->_success(array(
            'id'          => $product->getId(),
            'sku'         => $product->getSku(),
            'name'        => $product->getName(),
            'description' => $product->getDescription(),
            'short_description' => $product->getShortDescription(),
            'price'       => (float)$product->getPrice(),
            'special_price' => (float)$product->getSpecialPrice(),
            'currency'    => Mage::app()->getStore()->getCurrentCurrencyCode(),
            'url'         => $product->getProductUrl(),
            'is_in_stock' => Mage::getModel('cataloginventory/stock_item')->loadByProduct($product)->getIsInStock(),
        ));
    }
}
