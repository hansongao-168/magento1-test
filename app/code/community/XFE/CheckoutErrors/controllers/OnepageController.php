<?php
require_once 'Mage/Checkout/controllers/OnepageController.php';

class XFE_CheckoutErrors_OnepageController extends Mage_Checkout_OnepageController
{
    /**
     * Protected checkout AJAX actions
     */
    protected $_protectedActions = [
        'saveBilling',
        'saveShipping',
        'saveShippingMethod',
        'savePayment',
        'saveOrder',
    ];

    /**
     * Override dispatch to wrap protected actions in try-catch
     */
    public function dispatch($action)
    {
        $actionName = strtolower($this->getRequest()->getActionName());
        $normalizedActions = array_map('strtolower', $this->_protectedActions);

        if (in_array($actionName, $normalizedActions)) {
            try {
                return parent::dispatch($action);
            } catch (Zend_Db_Exception $e) {
                return $this->_handleCheckoutError($e);
            }
        }
        return parent::dispatch($action);
    }

    /**
     * Unified error handler: log + return JSON
     *
     * @param Exception $e
     * @return $this
     */
    protected function _handleCheckoutError(Exception $e)
    {
        $refId = 'ERR-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -4));
        $request = $this->getRequest();
        $actionName = $request->getActionName();

        // Determine customer info
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $customerId = $customer && $customer->getId() ? $customer->getId() : 0;
        $customerEmail = $customer ? $customer->getEmail() : '';

        // Also try to get email from billing data if guest
        if (!$customerEmail) {
            $billingData = $request->getPost('billing', []);
            $customerEmail = isset($billingData['email']) ? $billingData['email'] : '';
        }

        // 1. Save to database
        try {
            Mage::getModel('xfe_checkouterrors/checkoutError')
                ->setReferenceId($refId)
                ->setStep($actionName)
                ->setErrorMessage($e->getMessage())
                ->setErrorTrace($e->getTraceAsString())
                ->setRequestData(serialize($request->getParams()))
                ->setCustomerId($customerId)
                ->setCustomerEmail($customerEmail)
                ->setQuoteId((int)Mage::getSingleton('checkout/session')->getQuoteId())
                ->setStoreId((int)Mage::app()->getStore()->getId())
                ->save();
        } catch (Exception $dbE) {
            Mage::log(
                'Failed to save checkout error to DB: ' . $dbE->getMessage(),
                Zend_Log::ERR, 'checkout_error.log', true
            );
        }

        // 2. Write file log
        try {
            Mage::helper('xfe_checkouterrors')->logError($refId, $actionName, $e, $request);
        } catch (Exception $logE) {
            // Silently ignore file logging failure
        }

        // 3. Return JSON response
        $msg = $this->__('下单失败，请联系客服，参考号：%s', $refId);
        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody(
            Mage::helper('core')->jsonEncode([
                'error'   => true,
                'message' => $msg,
                'ref_id'  => $refId,
            ])
        );

        return $this;
    }
}
