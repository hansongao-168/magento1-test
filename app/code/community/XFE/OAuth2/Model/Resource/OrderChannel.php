<?php
/**
 * OrderChannel Resource Model
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Resource_OrderChannel extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * @var string
     */
    protected $_serializableFields = array();

    /**
     * Init
     */
    protected function _construct()
    {
        $this->_init('xfeoauth2/order_channel', 'id');
    }

    /**
     * Unique load by order_id (override of the default load-by-PK)
     */
    protected function _getLoadByUniqueField($field, $value)
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->getMainTable())
            ->where($field . ' = ?', $value)
            ->limit(1);
        $id = $this->_getReadAdapter()->fetchOne($select);
        return $id ? (int)$id : false;
    }
}