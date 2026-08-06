<?php

/**
 * FTP账号 Service
 *
 * 与 XFE_Carrier_Model_Service_Account 同款形态,负责 xfe_carrier_ftp_account
 * 表的批量 upsert + delete-by-diff,在 saveAction() 与 _purgeCarrierChildren()
 * 中被调用。
 *
 * 每条记录的写入列严格限定为 DB 真实存在的列,避免 Controller addData()
 * 误把不存在的字段落到表里。
 */
class XFE_Carrier_Model_Service_FtpAccount
{
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
     * 批量保存某承运商下的所有 FTP账号。先 diff 出新增/更新/删除,然后写库。
     *
     * @param int   $carrierId
     * @param array|string $payload 形如 [[ftp_account_id,account_name,host,...]]
     * @return int 保留的行数
     */
    public function saveBatch($carrierId, $payload)
    {
        $carrierId = (int)$carrierId;
        if (!$carrierId) {
            return 0;
        }
        $rows = $this->_decode($payload);
        if (!is_array($rows) || empty($rows)) {
            return $this->_replaceAll($carrierId);
        }

        $write = $this->_getWrite();
        $table = $this->_getTable();

        $existingIds = $write->fetchCol(
            $write->select()->from($table, 'ftp_account_id')->where('carrier_id = ?', $carrierId)
        );

        $submittedIds = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = isset($row['ftp_account_id']) ? (int)$row['ftp_account_id'] : 0;
            $data = array(
                'carrier_id'   => $carrierId,
                'account_name' => isset($row['account_name']) ? $row['account_name'] : '',
                'account_no'   => isset($row['account_no'])   ? $row['account_no']   : '',
                'protocol'     => isset($row['protocol'])     ? $row['protocol']     : 'ftp',
                'host'         => isset($row['host'])         ? $row['host']         : '',
                'port'         => isset($row['port'])         ? (int)$row['port']    : 21,
                'username'     => isset($row['username'])     ? $row['username']     : '',
                'password'     => isset($row['password'])     ? $row['password']     : '',
                'remote_path'  => isset($row['remote_path'])  ? $row['remote_path']  : '',
                'mode'         => isset($row['mode'])         ? $row['mode']         : 'passive',
                'encoding'     => isset($row['encoding'])     ? $row['encoding']     : 'UTF-8',
                'status'       => isset($row['status'])       ? (int)$row['status']  : 1,
                'sort_order'   => isset($row['sort_order'])   ? (int)$row['sort_order'] : 0,
                'note'         => isset($row['note'])         ? $row['note']         : '',
                'updated_at'   => Varien_Date::now(),
            );

            if ($id > 0 && in_array($id, $existingIds, true)) {
                $write->update($table, $data, array('ftp_account_id = ?' => $id));
                $submittedIds[] = $id;
            } else {
                $data['created_at'] = Varien_Date::now();
                $write->insert($table, $data);
                $submittedIds[] = (int)$write->lastInsertId($table);
            }
        }

        $this->_deleteByIds($carrierId, array_diff($existingIds, $submittedIds));
        return count($submittedIds);
    }

    /**
     * 删除一个承运商下全部 FTP账号(在删除承运商前调用)。
     *
     * @param int $carrierId
     * @return int 受影响行数
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

    protected function _replaceAll($carrierId)
    {
        $deleted = $this->deleteAllForCarrier($carrierId);
        return 0;
    }

    protected function _deleteByIds($carrierId, array $ids)
    {
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            return;
        }
        $write = $this->_getWrite();
        $table = $this->_getTable();
        $write->delete($table, array(
            'carrier_id = ?'        => (int)$carrierId,
            'ftp_account_id IN (?)' => $ids,
        ));
    }

    protected function _getWrite()
    {
        return Mage::getSingleton('core/resource')->getConnection('core_write');
    }

    protected function _getTable()
    {
        return Mage::getSingleton('core/resource')->getTableName('xfe_carrier/carrier_ftp_account');
    }
}