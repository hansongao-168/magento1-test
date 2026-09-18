<?php

class XFE_Carrier_Model_Resource_Rule_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_carrier/carrier_rule');
    }

    /**
     * Materialise each loaded rule's nested condition tree.
     *
     * Mage's Db collection _afterLoad() never calls the item's
     * afterLoad(), so the resource model's _afterLoad() (which builds
     * `conditions_data`) does not run for collection rows. Without this,
     * rules grids render the "匹配所有" fallback for every rule regardless
     * of its real conditions (getConditionsDescription() sees an empty
     * conditions_data).
     *
     * This is intentionally opt-in (not hooked into _afterLoad()) so the
     * rule Resolver - which loads the same collection on every resolution
     * and loads each rule's tree itself - is not slowed down by a double
     * load. Only the admin rules grids call it after the grid has loaded.
     *
     * @return $this
     */
    public function loadConditions()
    {
        foreach ($this->_items as $item) {
            if ($item instanceof XFE_Carrier_Model_Carrier_Rule) {
                $item->afterLoad();
            }
        }
        return $this;
    }
}
