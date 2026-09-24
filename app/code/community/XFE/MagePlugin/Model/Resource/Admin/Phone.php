<?php
/**
 * XFE_MagePlugin 后台用户手机号资源模型。
 */
class XFE_MagePlugin_Model_Resource_Admin_Phone extends XFE_MagePlugin_Model_Resource
{
    protected function _construct()
    {
        $this->_init('xfe_mageplugin/admin_phone', 'entity_id');
    }

    /**
     * 按用户 id 读取手机号。
     *
     * @param int $userId
     * @return string|null
     */
    public function getPhoneByUserId($userId)
    {
        $read = $this->_getReadAdapter();
        $select = $read->select()
            ->from($this->getMainTable(), 'phone')
            ->where('user_id = ?', (int)$userId)
            ->limit(1);

        return $read->fetchOne($select);
    }

    /**
     * 保存用户手机号（存在则更新）。
     *
     * @param int    $userId
     * @param string $phone
     * @return void
     */
    public function savePhone($userId, $phone)
    {
        $write = $this->_getWriteAdapter();
        $select = $write->select()
            ->from($this->getMainTable(), 'entity_id')
            ->where('user_id = ?', (int)$userId)
            ->limit(1);

        $id = $write->fetchOne($select);
        if ($id) {
            $write->update($this->getMainTable(), array('phone' => $phone), array('entity_id = ?' => (int)$id));
        } else {
            $write->insert($this->getMainTable(), array(
                'user_id'    => (int)$userId,
                'phone'      => $phone,
            ));
        }
    }
}
