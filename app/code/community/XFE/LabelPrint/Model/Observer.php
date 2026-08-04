<?php

class XFE_LabelPrint_Model_Observer
{

    /**
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

    /**
     * @param Varien_Event_Observer $observer
     * @return XFE_LabelPrint_Model_Observer
     */
    public function onResponseReplaceFiles($observer)
    {
        $event = $observer->getEvent();
        if ($event === null) {
            return;
        }

        $order         = $event->getOrder();
        $data = $event->getData('data');
        $response      = $event->getResponse();

        if (!$response instanceof Varien_Object) {
            return $this;
        }

        /** @var XFE_LabelPrint_Model_Extraction_Print $model */
        $model = Mage::getModel('xfe_labelprint/extraction_print');
        $replaceFiles = $model->getReplaceFiles($order, $data);
        $response->setData($replaceFiles);
        return $this;
    }

}