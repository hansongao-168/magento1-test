<?php
/**
 * Carriers API - scope: carriers
 *
 * Read-only business API for carrier (物流商) data.
 * Auth: Bearer token obtained via client_credentials grant (no username/password).
 *
 * Endpoints:
 *   GET /api/v2/carriers              - list carriers
 *   GET /api/v2/carriers/:id          - carrier detail
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Api_Carriers extends XFE_OAuth2_Model_Api_Abstract
{
    protected $_requiredScope = 'carriers';

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
                    $this->_getCarrier((int)$params[0]);
                } else {
                    $this->_getCarriers();
                }
                break;
            default:
                $this->_helper->sendJsonError(405, 'bad_request', 'Method not allowed');
        }
    }

    /**
     * GET /api/v2/carriers - list carriers
     */
    protected function _getCarriers()
    {
        $page  = $this->_getPage();
        $limit = $this->_getLimit();
        $store = $this->_getStoreId();

        $collection = Mage::getModel('xfe_carrier/carrier')->getCollection()
            ->addFieldToSelect(array('entity_id', 'code', 'name', 'status', 'sort_order', 'note', 'created_at'))
            ->setOrder('sort_order', 'ASC')
            ->setOrder('entity_id', 'ASC')
            ->setPage($page, $limit)
            ->load();

        $items = array();
        foreach ($collection as $carrier) {
            $items[] = $this->_carrierToArray($carrier, $store);
        }

        $this->_paginated($items, $page, $limit, $collection->getSize());
    }

    /**
     * GET /api/v2/carriers/:id - carrier detail
     *
     * @param int $id
     */
    protected function _getCarrier($id)
    {
        $carrier = Mage::getModel('xfe_carrier/carrier')->load($id);
        if (!$carrier->getId()) {
            $this->_helper->sendJsonError(404, 'not_found', 'Carrier not found');
            return;
        }

        $store = $this->_getStoreId();
        $this->_success($this->_carrierToArray($carrier, $store, true));
    }

    /**
     * Map a carrier model to a public array.
     *
     * @param XFE_Carrier_Model_Carrier $carrier
     * @param int $storeId
     * @param bool $includeLogo
     * @return array
     */
    protected function _carrierToArray(XFE_Carrier_Model_Carrier $carrier, $storeId = 0, $includeLogo = false)
    {
        // Pin the store scope so store 0 (admin/default) deterministically
        // resolves to the base name/note instead of the current front store.
        $carrier->setStoreId($storeId);

        $data = array(
            'id'                 => (int)$carrier->getId(),
            'code'               => $carrier->getCode(),
            'name'               => (string)$carrier->getStoreName(),
            'status'             => (int)$carrier->getStatus(),
            'sort_order'         => (int)$carrier->getSortOrder(),
            'note'               => (string)$carrier->getStoreNote(),
            'created_at'         => $carrier->getCreatedAt(),
        );

        if ($includeLogo) {
            $data['logo_url'] = $carrier->getLogoUrl();
        }

        return $data;
    }

    /**
     * Resolve store id from request params (defaults to 0 = admin/default scope).
     *
     * @return int
     */
    protected function _getStoreId()
    {
        return max(0, (int)Mage::app()->getRequest()->getParam('store', 0));
    }
}
