<?php

/**
 * Default observer for xfe_labelprint_response_received.
 *
 * Carrier / print modules dispatch the event from
 * XFE_LabelPrint_Helper_Data::dispatchLabelResponse(); this observer
 * unpacks the order / carrier module / Varien_Object response
 * payload and writes a row into xfe_label_print via the helper.
 *
 * Modules that need to pre-process or suppress the record (e.g. to
 * skip a known carrier) can simply remove this observer via their own
 * config.xml or set $observer->setEventName('...') in a different
 * scope.
 */
class XFE_LabelPrint_Model_Observer
{
    /**
     * Listen for "xfe_labelprint_response_received".
     *
     * Expected event data:
     *   - order          Mage_Sales_Model_Order|null
     *   - carrier_module string
     *   - response       Varien_Object
     *
     * @param Varien_Event_Observer $observer
     */
    public function onResponseReceived(Varien_Event_Observer $observer)
    {
        $event = $observer->getEvent();
        if ($event === null) {
            return;
        }

        $order         = $event->getOrder();
        $carrierModule = (string)$event->getCarrierModule();
        $response      = $event->getResponse();

        if (!$response instanceof Varien_Object) {
            return;
        }

        try {
            Mage::helper('xfe_labelprint')
                ->recordFromResponse($order, $carrierModule, $response);
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }
}
