<?php
/**
 * XFE_MagePlugin helper.
 */
class XFE_MagePlugin_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * 当前登录后台用户是否为「完整权限」账号（user_id 1 / 2）。
     *
     * @return bool
     */
    public function isCurrentUserFullAccess()
    {
        $userId = Mage::getSingleton('admin/session')->getUser()
            ? (int)Mage::getSingleton('admin/session')->getUser()->getId()
            : 0;
        return (new XFE_MagePlugin_Model_Account_Privileged())->isFullAccess($userId);
    }
}
