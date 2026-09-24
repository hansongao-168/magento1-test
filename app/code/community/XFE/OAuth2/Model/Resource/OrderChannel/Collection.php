<?php
/**
 * OrderChannel Collection
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Resource_OrderChannel_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeoauth2/order_channel');
    }

    /**
     * Filter by channel
     *
     * @param string $channel
     * @return $this
     */
    public function addChannelFilter($channel)
    {
        $this->addFieldToFilter('channel', $channel);
        return $this;
    }

    /**
     * Filter by client_id
     *
     * @param string $clientId
     * @return $this
     */
    public function addClientIdFilter($clientId)
    {
        $this->addFieldToFilter('client_id', $clientId);
        return $this;
    }

    /**
     * Filter by customer / admin creator
     *
     * @param int    $createdBy
     * @param string $type
     * @return $this
     */
    public function addCreatedByFilter($createdBy, $type)
    {
        $this->addFieldToFilter('created_by', (int)$createdBy)
             ->addFieldToFilter('created_by_type', $type);
        return $this;
    }
}