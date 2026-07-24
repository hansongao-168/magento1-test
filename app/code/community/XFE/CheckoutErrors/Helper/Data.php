<?php
class XFE_CheckoutErrors_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Log checkout error to var/log/checkout_error.log
     *
     * @param string     $refId
     * @param string     $step
     * @param Exception  $e
     * @param Mage_Core_Controller_Request_Http $request
     */
    public function logError($refId, $step, Exception $e, $request)
    {
        $log = sprintf(
            "[%s] [%s] [%s] %s\nTrace:\n%s\nRequest: %s\n%s\n",
            date('Y-m-d H:i:s'),
            $refId,
            $step,
            $e->getMessage(),
            $e->getTraceAsString(),
            json_encode($request->getParams()),
            str_repeat('-', 80)
        );
        Mage::log($log, Zend_Log::ERR, 'checkout_error.log', true);
    }
}
