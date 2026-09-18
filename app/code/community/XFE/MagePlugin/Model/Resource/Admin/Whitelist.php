<?php
/**
 * XFE_MagePlugin IP 白名单资源模型。
 */
class XFE_MagePlugin_Model_Resource_Admin_Whitelist extends XFE_MagePlugin_Model_Resource
{
    protected function _construct()
    {
        $this->_init('xfe_mageplugin/admin_whitelist', 'entity_id');
    }

    /**
     * 判断某用户是否在给定 IP 白名单中。
     *
     * @param int    $userId
     * @param string $ip
     * @return bool
     */
    public function isIpAllowed($userId, $ip)
    {
        $read = $this->_getReadAdapter();
        $select = $read->select()
            ->from($this->getMainTable(), 'entity_id')
            ->where('user_id = ?', (int)$userId)
            ->where('ip = ?', $ip)
            ->limit(1);

        return (bool)$read->fetchOne($select);
    }

    /**
     * 将 IP 加入用户白名单。
     *
     * @param int    $userId
     * @param string $ip
     * @param int    $isAutoAdded
     * @return void
     */
    public function addIp($userId, $ip, $isAutoAdded = 0)
    {
        $write = $this->_getWriteAdapter();
        $select = $write->select()
            ->from($this->getMainTable(), 'entity_id')
            ->where('user_id = ?', (int)$userId)
            ->where('ip = ?', $ip)
            ->limit(1);

        if ($write->fetchOne($select)) {
            return; // 已存在
        }

        $write->insert($this->getMainTable(), array(
            'user_id'       => (int)$userId,
            'ip'            => $ip,
            'is_auto_added' => (int)$isAutoAdded,
            'created_at'    => now(),
        ));
    }
}
