<?php
/**
 * XFE ShippingRule Type Resource Model
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Model_Resource_Type extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeshippingrule/type', 'type_id');
    }
}
