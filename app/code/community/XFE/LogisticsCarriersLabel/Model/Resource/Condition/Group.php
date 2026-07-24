<?php
/**
 * XFE LogisticsCarriersLabel ConditionGroup Resource Model
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_Model_Resource_Condition_Group extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('xcarrierslabel/condition_group', 'group_id');
    }
}
