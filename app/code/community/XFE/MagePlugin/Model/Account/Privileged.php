<?php
/**
 * XFE_MagePlugin 账号特权判定（L1 Domain，纯逻辑，无外部依赖）。
 *
 * 集中管理「特殊账号」规则，供各 Block / Observer / Service 复用。
 */
class XFE_MagePlugin_Model_Account_Privileged
{
    /** 完整权限账号 user_id 集合（账号 1 和 2） */
    const FULL_USER_IDS = array(1, 2);

    /**
     * 判断某后台用户是否为「完整权限」账号（user_id 1 / 2）。
     *
     * @param int|string|null $userId
     * @return bool
     */
    public function isFullAccess($userId)
    {
        return in_array((int)$userId, self::FULL_USER_IDS, true);
    }

    /**
     * 判断某用户是否允许通过短信验证码自动添加新 IP（用户 1~5）。
     *
     * @param int|string|null $userId
     * @return bool
     */
    public function canAutoAddIp($userId)
    {
        return in_array((int)$userId, array(1, 2, 3, 4, 5), true);
    }
}
