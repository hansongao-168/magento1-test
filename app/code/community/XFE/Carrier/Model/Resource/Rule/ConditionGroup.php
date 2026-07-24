<?php

class XFE_Carrier_Model_Resource_Rule_ConditionGroup extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_carrier/rule_condition_group', 'group_id');
    }
}
