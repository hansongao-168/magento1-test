<?php
/**
 * Customers API - scope: customers (requires admin scope for listing)
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Api_Customers extends XFE_OAuth2_Model_Api_Abstract
{
    protected $_requiredScope = 'customers';

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
                    $this->_getCustomer((int)$params[0]);
                } else {
                    $this->_getCustomers();
                }
                break;
            default:
                $this->_helper->sendJsonError(405, 'bad_request', 'Method not allowed');
        }
    }

    /**
     * GET /api/v2/customers - list customers (admin scope required)
     */
    protected function _getCustomers()
    {
        // Only admin scope can list all customers
        $tokenScopes = explode(' ', $this->_tokenData['scope']);
        if (!in_array('admin', $tokenScopes)) {
            $this->_helper->sendJsonError(401, 'insufficient_scope',
                'Admin scope required to list customers');
            return;
        }

        $page = $this->_getPage();
        $limit = $this->_getLimit();

        $customers = Mage::getResourceModel('customer/customer_collection')
            ->addNameToSelect()
            ->addAttributeToSelect(array('email'))
            ->setPage($page, $limit)
            ->load();

        $items = array();
        foreach ($customers as $customer) {
            $items[] = array(
                'id'         => $customer->getId(),
                'email'      => $customer->getEmail(),
                'name'       => $customer->getName(),
                'created_at' => $customer->getCreatedAt(),
            );
        }

        $this->_paginated($items, $page, $limit, $customers->getSize());
    }

    /**
     * GET /api/v2/customers/:id - customer detail
     */
    protected function _getCustomer($id)
    {
        $tokenScopes = explode(' ', $this->_tokenData['scope']);

        // Non-admin users can only view their own profile
        if (!in_array('admin', $tokenScopes)) {
            if ($id != $this->_tokenData['user_id']) {
                $this->_helper->sendJsonError(403, 'forbidden', 'Cannot access other customer data');
                return;
            }
        }

        $customer = Mage::getModel('customer/customer')->load($id);
        if (!$customer->getId()) {
            $this->_helper->sendJsonError(404, 'not_found', 'Customer not found');
            return;
        }

        $addresses = array();
        foreach ($customer->getAddresses() as $address) {
            $addresses[] = array(
                'id'        => $address->getId(),
                'street'    => $address->getStreetFull(),
                'city'      => $address->getCity(),
                'region'    => $address->getRegion(),
                'postcode'  => $address->getPostcode(),
                'country'   => $address->getCountry(),
                'telephone' => $address->getTelephone(),
                'is_default_billing'  => (bool)$address->getIsDefaultBilling(),
                'is_default_shipping' => (bool)$address->getIsDefaultShipping(),
            );
        }

        $this->_success(array(
            'id'         => $customer->getId(),
            'email'      => $customer->getEmail(),
            'firstname'  => $customer->getFirstname(),
            'lastname'   => $customer->getLastname(),
            'created_at' => $customer->getCreatedAt(),
            'addresses'  => $addresses,
        ));
    }
}
