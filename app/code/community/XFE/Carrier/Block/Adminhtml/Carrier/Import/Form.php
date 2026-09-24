<?php

/**
 * Upload form rendered inside XFE_Carrier_Block_Adminhtml_Carrier_Import.
 *
 * Instead of fighting the Varien form renderer (which needs a registered
 * action for URLs and generates its own form wrapper), this block emits a
 * minimal, self-contained <form> with a file input + hidden form_key and
 * the submit button. Posts to CarrierController::importPostAction().
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Import_Form extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('xfe_carrier/carrier/import/form.phtml');
    }

    /**
     * @return string
     */
    public function getPostUrl()
    {
        return $this->getUrl('*/carrier/importPost');
    }
}