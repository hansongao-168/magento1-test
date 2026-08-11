<?php
/**
 * OrderChannel Model
 *
 * Records which channel an order was placed through (web / api_oauth2 / admin).
 * One row per order; sales_flat_order is NOT modified.
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_OrderChannel extends Mage_Core_Model_Abstract
{
    /** @var string */
    const CHANNEL_WEB        = 'web';

    /** @var string */
    const CHANNEL_API_OAUTH2 = 'api_oauth2';

    /** @var string */
    const CHANNEL_ADMIN      = 'admin';

    /** @var string */
    const CHANNEL_SYSTEM     = 'system';

    /**
     * Convenience: insert / update a channel record for a given order.
     *
     * Idempotent — re-running on the same order_id updates the existing row
     * instead of throwing on the UNIQUE index.
     *
     * @param int    $orderId
     * @param string $channel        One of the CHANNEL_* constants
     * @param string|null $channelSource  e.g. OAuth2 client_id
     * @param string|null $clientId       FK to xfe_oauth2_client.client_id
     * @param int|null    $createdBy
     * @param string|null $createdByType  'customer' | 'admin' | 'system'
     * @param string|null $notes
     * @return XFE_OAuth2_Model_OrderChannel
     */
    public function recordChannel(
        $orderId,
        $channel,
        $channelSource = null,
        $clientId = null,
        $createdBy = null,
        $createdByType = null,
        $notes = null
    ) {
        $record = $this->loadByOrderId($orderId);
        if (!$record->getId()) {
            $record->setData('order_id', (int)$orderId);
        }
        $record->setData('channel', $channel)
            ->setData('channel_source', $channelSource)
            ->setData('client_id', $clientId)
            ->setData('created_by', $createdBy ? (int)$createdBy : null)
            ->setData('created_by_type', $createdByType)
            ->setData('notes', $notes)
            ->setData('updated_at', now());
        if (!$record->getCreatedAt()) {
            $record->setData('created_at', now());
        }
        $record->save();
        return $record;
    }

    /**
     * Load by order_id
     *
     * @param int $orderId
     * @return XFE_OAuth2_Model_OrderChannel
     */
    public function loadByOrderId($orderId)
    {
        return $this->load((int)$orderId, 'order_id');
    }

    /**
     * Init resource model
     */
    protected function _construct()
    {
        $this->_init('xfeoauth2/order_channel');
    }
}