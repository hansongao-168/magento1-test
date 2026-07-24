<?php
/**
 * XFE ShippingRule ConditionGroup Resource Model
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Model_Resource_ConditionGroup extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeshippingrule/condition_group', 'group_id');
    }
}
