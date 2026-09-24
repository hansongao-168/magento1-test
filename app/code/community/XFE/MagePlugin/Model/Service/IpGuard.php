<?php
/**
 * XFE_MagePlugin 登录 IP 守卫（L3 Service）。
 *
 * 在后台用户认证前判断当前 IP 是否允许登录。
 */
class XFE_MagePlugin_Model_Service_IpGuard
{
    /** 允许登录 */
    const RESULT_ALLOWED   = 'allowed';
    /** 需短信验证（新 IP，用户可添加） */
    const RESULT_NEED_SMS  = 'need_sms';
    /** 拒绝登录（有白名单但不含此 IP，且不可短信添加） */
    const RESULT_DENIED    = 'denied';

    /**
     * 判断某用户从给定 IP 登录是否允许。
     *
     * @param int $userId
     * @param string $ip
     * @return string 见 RESULT_* 常量
     */
    public function check($userId, $ip)
    {
        if (!$ip) {
            return self::RESULT_ALLOWED; // 取不到 IP 时不拦截
        }

        $whitelist = Mage::getResourceModel('xfe_mageplugin/admin_whitelist');
        $allowed = $whitelist->isIpAllowed($userId, $ip);

        if ($allowed) {
            return self::RESULT_ALLOWED;
        }

        // 不在白名单：判定该用户是否有白名单配置（是否开启 IP 限制）
        if (!$this->_hasWhitelistConfigured($userId)) {
            return self::RESULT_ALLOWED;
        }

        // 有白名单但不含此 IP
        $privileged = new XFE_MagePlugin_Model_Account_Privileged();
        if ($privileged->canAutoAddIp($userId)) {
            return self::RESULT_NEED_SMS;
        }
        return self::RESULT_DENIED;
    }

    /**
     * 判断用户是否配置了 IP 白名单（非空）。
     *
     * @param int $userId
     * @return bool
     */
    protected function _hasWhitelistConfigured($userId)
    {
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $table = Mage::getSingleton('core/resource')->getTableName('xfe_mageplugin/admin_whitelist');
        $select = $read->select()
            ->from($table, 'COUNT(*)')
            ->where('user_id = ?', (int)$userId);
        return (int)$read->fetchOne($select) > 0;
    }
}
