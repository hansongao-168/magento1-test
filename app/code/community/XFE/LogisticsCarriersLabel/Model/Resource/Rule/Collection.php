<?php
/**
 * XFE LogisticsCarriersLabel Rule Collection
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_Model_Resource_Rule_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xcarrierslabel/rule');
    }

    /**
     * Add active filter
     *
     * @return $this
     */
    public function addActiveFilter()
    {
        $this->addFieldToFilter('status', 1);
        return $this;
    }

    /**
     * Filter by country code
     *
     * @param string $countryCode
     * @return $this
     */
    public function addCountryFilter($countryCode)
    {
        $this->addFieldToFilter('country_code', $countryCode);
        return $this;
    }

    /**
     * Get all rules indexed by country code for fast lookup
     *
     * @return array country_code => rule data
     */
    public function toCountryIndexedArray()
    {
        $result = array();
        foreach ($this as $item) {
            $result[$item->getCountryCode()] = $item;
        }
        return $result;
    }
}
