<?php
/**
 * XFE LogisticsCarriersLabel Condition Resource Model
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_Model_Resource_Condition extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('xcarrierslabel/condition', 'condition_id');
    }
}
