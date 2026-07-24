<?php
/**
 * Carrier Offer Block
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_Block_Carrier_Offre extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('xcarriers_label/carrier/offre.phtml');
    }

    /**
     * Get all enabled partner rules
     *
     * @return XFE_LogisticsCarriersLabel_Model_Resource_Rule_Collection
     */
    public function getPartners()
    {
        return Mage::getResourceModel('xcarrierslabel/rule_collection')
            ->addActiveFilter()
            ->setOrder('country_name', 'ASC');
    }

    /**
     * Get default display title
     *
     * @return string
     */
    public function getDefaultTitle()
    {
        return 'Chrono Classic';
    }
}
