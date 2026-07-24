<?php

class XFE_Carrier_Model_Carrier_Logo extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_carrier/carrier_logo');
    }

    protected function _beforeSave()
    {
        parent::_beforeSave();

        if ($this->isObjectNew()) {
            $this->setCreatedAt(Varien_Date::now());
        }

        return $this;
    }

    /**
     * @return string|null
     */
    public function getLabel()
    {
        return $this->getData('label');
    }

    /**
     * @param string $label
     * @return $this
     */
    public function setLabel($label)
    {
        return $this->setData('label', $label);
    }

    /**
     * @return string|null
     */
    public function getLogoType()
    {
        return $this->getData('logo_type');
    }

    /**
     * @param string $logoType
     * @return $this
     */
    public function setLogoType($logoType)
    {
        return $this->setData('logo_type', $logoType);
    }

    /**
     * @return int|null
     */
    public function getSortOrder()
    {
        return $this->getData('sort_order');
    }

    /**
     * @param int $sortOrder
     * @return $this
     */
    public function setSortOrder($sortOrder)
    {
        return $this->setData('sort_order', $sortOrder);
    }
}
