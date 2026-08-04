<?php

class XFE_Carrier_Model_Resource_Carrier_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Active store id for translation joins
     * @var int|null
     */
    protected $_storeId;

    protected function _construct()
    {
        $this->_init('xfe_carrier/carrier');
        $this->_map['fields']['entity_id'] = 'main_table.entity_id';
    }

    /**
     * Filter / scope carriers to a store view. The store translation
     * columns (name / note) are joined so callers can read them via
     * $item->getStoreName() / $item->getStoreNote(). The base admin-scope
     * columns stay available via $item->getName() / $item->getNote().
     *
     * @param int|Mage_Core_Model_Store|array $store
     * @param bool $withAdmin Include admin scope (store_id = 0) as fallback
     * @return $this
     */
    public function addStoreFilter($store, $withAdmin = true)
    {
        if ($store instanceof Mage_Core_Model_Store) {
            $store = array($store->getId());
        } elseif (!is_array($store)) {
            $store = array((int)$store);
        }

        $store = array_unique(array_map('intval', $store));
        if ($withAdmin && !in_array(Mage_Core_Model_App::ADMIN_STORE_ID, $store, true)) {
            $store[] = Mage_Core_Model_App::ADMIN_STORE_ID;
        }

        $this->_storeId = (int)reset($store);

        $this->getSelect()->joinLeft(
            array('ct' => $this->getTable('xfe_carrier/carrier_translation')),
            'main_table.entity_id = ct.carrier_id AND ct.store_id = ' . $this->_storeId,
            array(
                'store_name' => 'ct.name',
                'store_note' => 'ct.note',
            )
        );

        return $this;
    }

    /**
     * Convenience getter for the current collection store scope.
     *
     * @return int
     */
    public function getStoreId()
    {
        return (int)$this->_storeId;
    }

    /**
     * After collection load, fall back store_name / store_note to the
     * base column when the per-store row is empty.
     *
     * @return $this
     */
    protected function _afterLoad()
    {
        parent::_afterLoad();
        foreach ($this as $item) {
            if ((string)$item->getData('store_name') === '') {
                $item->setData('store_name', $item->getData('name'));
            }
            if ((string)$item->getData('store_note') === '') {
                $item->setData('store_note', $item->getData('note'));
            }
        }
        return $this;
    }
}