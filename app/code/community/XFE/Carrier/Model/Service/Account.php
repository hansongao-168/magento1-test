<?php

/**
 * AccountService
 *
 * Owns the lifecycle of an account row in xfe_carrier_account:
 *   - batch upsert (insert + update + delete-in-diff)
 *   - delete by id
 *   - delete every account for a carrier (used before carrier delete)
 *
 * Caller passes either an array (already decoded) or a JSON string. The
 * service accepts both so the controller does not need to know about JSON.
 *
 * Dependencies (one-way):
 *   AccountService ─▶  Carrier_Account (DB entity)
 */
class XFE_Carrier_Model_Service_Account
{
    /** @var self|null */
    protected static $_instance = null;

    public static function instance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public static function setInstance($instance)
    {
        self::$_instance = $instance;
    }

    /**
     * Persist a batch of accounts for one carrier. Returns the count of
     * submitted rows (inserts + updates). Caller can use that for messaging.
     *
     * @param int        $carrierId
     * @param mixed      $payload  array|json-string|null
     * @return int
     */
    public function saveBatch($carrierId, $payload)
    {
        $carrierId = (int)$carrierId;
        if (!$carrierId) {
            return 0;
        }
        $accounts = $this->_decode($payload);
        if (!is_array($accounts) || empty($accounts)) {
            return $this->_replaceAll($carrierId);
        }

        $write = $this->_getWrite();
        $table = $this->_getTable();

        $existingIds = $write->fetchCol(
            $write->select()->from($table, 'account_id')->where('carrier_id = ?', $carrierId)
        );

        $submittedIds = array();
        foreach ($accounts as $row) {
            $id  = isset($row['account_id']) ? (int)$row['account_id'] : 0;
            $ruleIdRaw = isset($row['rule_id']) ? $row['rule_id'] : null;
            $row = array(
                'carrier_id'   => $carrierId,
                'rule_id'      => ($ruleIdRaw === '' || $ruleIdRaw === null) ? null : (int)$ruleIdRaw,
                'account_name' => isset($row['account_name']) ? $row['account_name'] : '',
                'account_no'   => isset($row['account_no'])   ? $row['account_no']   : '',
                'api_key'      => isset($row['api_key'])      ? $row['api_key']      : '',
                'api_secret'   => isset($row['api_secret'])   ? $row['api_secret']   : '',
                'username'     => isset($row['username'])     ? $row['username']     : '',
                'password'     => isset($row['password'])     ? $row['password']     : '',
                'endpoint_url' => isset($row['endpoint_url']) ? $row['endpoint_url'] : '',
                'status'       => isset($row['status'])       ? (int)$row['status']  : 1,
                'sort_order'   => isset($row['sort_order'])   ? (int)$row['sort_order'] : 0,
                'note'         => isset($row['note'])         ? $row['note']         : '',
                'updated_at'   => Varien_Date::now(),
            );

            if ($id > 0 && in_array($id, $existingIds, true)) {
                $write->update($table, $row, array('account_id = ?' => $id));
                $submittedIds[] = $id;
            } else {
                $row['created_at'] = Varien_Date::now();
                $write->insert($table, $row);
                $submittedIds[] = (int)$write->lastInsertId($table);
            }
        }

        $this->_deleteByIds($carrierId, array_diff($existingIds, $submittedIds));
        return count($submittedIds);
    }

    /**
     * Delete every account for the carrier.
     *
     * @param int $carrierId
     * @return int Number deleted
     */
    public function deleteAllForCarrier($carrierId)
    {
        $carrierId = (int)$carrierId;
        if (!$carrierId) {
            return 0;
        }

        $write = $this->_getWrite();
        $table = $this->_getTable();
        return (int)$write->delete($table, array('carrier_id = ?' => $carrierId));
    }

    /**
     * Decode payload: accepts already-decoded array OR a JSON string.
     *
     * @param mixed $payload
     * @return array|null
     */
    protected function _decode($payload)
    {
        if (is_array($payload)) {
            return $payload;
        }
        if (is_string($payload) && $payload !== '') {
            $decoded = Mage::helper('core')->jsonDecode($payload);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    /**
     * Helper: when an empty payload is submitted, wipe the accounts (keep the diff clean).
     *
     * @param int $carrierId
     * @return int
     */
    protected function _replaceAll($carrierId)
    {
        $deleted = $this->deleteAllForCarrier($carrierId);
        return 0;
    }

    /**
     * Delete specific account ids (after diff).
     *
     * @param int   $carrierId
     * @param array $ids
     * @return void
     */
    protected function _deleteByIds($carrierId, array $ids)
    {
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            return;
        }
        $write = $this->_getWrite();
        $table = $this->_getTable();
        $write->delete($table, array(
            'carrier_id = ?'   => (int)$carrierId,
            'account_id IN (?)' => $ids,
        ));
    }

    /**
     * @return Varien_Db_Adapter_Interface
     */
    protected function _getWrite()
    {
        return Mage::getSingleton('core/resource')->getConnection('core_write');
    }

    /**
     * @return string
     */
    protected function _getTable()
    {
        return Mage::getSingleton('core/resource')->getTableName('xfe_carrier/carrier_account');
    }
}
