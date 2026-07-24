<?php

class XFE_Carrier_Model_Source_Status
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 1, 'label' => Mage::helper('xfe_carrier')->__('启用')),
            array('value' => 0, 'label' => Mage::helper('xfe_carrier')->__('禁用')),
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            1 => Mage::helper('xfe_carrier')->__('启用'),
            0 => Mage::helper('xfe_carrier')->__('禁用'),
        );
    }
}
