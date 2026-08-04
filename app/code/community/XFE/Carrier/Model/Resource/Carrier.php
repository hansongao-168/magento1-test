<?php

class XFE_Carrier_Model_Resource_Carrier extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Store-scope translatable attributes on the carrier entity.
     * Anything declared here is mirrored into xfe_carrier_translation
     * per (carrier_id, store_id); the base row keeps the admin-scope value.
     */
    const TRANSLATABLE_ATTRIBUTES = 'name,note';

    protected function _construct()
    {
        $this->_init('xfe_carrier/carrier', 'entity_id');
    }

    /**
     * Persist per-store translations after the base carrier row is saved.
     *
     * Expected payload on the model (set by the controller / edit form):
     *   store_translations => [
     *       'name' => [store_id => value, ...],
     *       'note' => [store_id => value, ...],
     *   ]
     *
     * Empty / null per-store values drop the row so admin/default wins.
     * store_id = 0 (admin) is ignored - the base row IS the admin value.
     */
    protected function _afterSave(Mage_Core_Model_Abstract $object)
    {
        parent::_afterSave($object);
        $this->_saveStoreTranslations($object);
        return $this;
    }

    /**
     * Upsert / prune per-store translation rows.
     *
     * @param XFE_Carrier_Model_Carrier $object
     */
    protected function _saveStoreTranslations(XFE_Carrier_Model_Carrier $object)
    {
        $carrierId = (int)$object->getId();
        if (!$carrierId) {
            return;
        }

        $payload = $object->getData('store_translations');
        if (!is_array($payload)) {
            return;
        }

        $allowed = array('name', 'note');
        $table   = $this->getTable('xfe_carrier/carrier_translation');
        $conn    = $this->_getWriteAdapter();

        $existing = $conn->fetchCol(
            $conn->select()
                ->from($table, array('store_id'))
                ->where('carrier_id = ?', $carrierId)
        );
        $existing = array_map('intval', $existing);

        $byStore = array();
        foreach ($allowed as $attr) {
            if (!isset($payload[$attr]) || !is_array($payload[$attr])) {
                continue;
            }
            foreach ($payload[$attr] as $storeId => $value) {
                $storeId = (int)$storeId;
                if ($storeId === 0) {
                    continue;
                }
                $byStore[$storeId][$attr] = (string)$value;
            }
        }

        $touchedStores = array_unique(array_merge(array_keys($byStore), $existing));

        foreach ($touchedStores as $storeId) {
            $storeId = (int)$storeId;
            $row     = isset($byStore[$storeId]) ? $byStore[$storeId] : array();

            $name = isset($row['name']) ? trim((string)$row['name']) : '';
            $note = isset($row['note']) ? (string)$row['note'] : '';

            $isEmpty = ($name === '') && ($note === '');

            if ($isEmpty) {
                $conn->delete($table, array(
                    'carrier_id = ?' => $carrierId,
                    'store_id   = ?' => $storeId,
                ));
                continue;
            }

            $bind = array(
                'name' => $name,
                'note' => $note,
            );

            if (in_array($storeId, $existing, true)) {
                $conn->update($table, $bind, array(
                    'carrier_id = ?' => $carrierId,
                    'store_id   = ?' => $storeId,
                ));
            } else {
                $bind['carrier_id'] = $carrierId;
                $bind['store_id']   = $storeId;
                $conn->insert($table, $bind);
            }
        }
    }

    /**
     * Load per-store name/note values onto the carrier for the given store.
     *
     * Mutates the model in place: sets 'store_name' and 'store_note' data
     * keys. Callers can read those via $carrier->getStoreName() / getStoreNote().
     *
     * @param XFE_Carrier_Model_Carrier $carrier
     * @param int $storeId
     * @return XFE_Carrier_Model_Resource_Carrier
     */
    public function loadStoreTranslations(XFE_Carrier_Model_Carrier $carrier, $storeId)
    {
        $carrierId = (int)$carrier->getId();
        if (!$carrierId) {
            return $this;
        }

        $storeId = (int)$storeId;
        if ($storeId === 0) {
            $carrier->setData('store_name', $carrier->getData('name'));
            $carrier->setData('store_note', $carrier->getData('note'));
            return $this;
        }

        $table = $this->getTable('xfe_carrier/carrier_translation');
        $row   = $this->_getReadAdapter()->fetchRow(
            $this->_getReadAdapter()->select()
                ->from($table, array('name', 'note'))
                ->where('carrier_id = ?', $carrierId)
                ->where('store_id   = ?', $storeId)
        );

        $name = ($row && $row['name'] !== null && $row['name'] !== '')
            ? $row['name']
            : $carrier->getData('name');
        $note = ($row && $row['note'] !== null && $row['note'] !== '')
            ? $row['note']
            : $carrier->getData('note');

        $carrier->setData('store_name', $name);
        $carrier->setData('store_note', $note);
        return $this;
    }
}