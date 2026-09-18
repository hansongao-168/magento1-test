<?php
/**
 * XFE_MagePlugin IP 白名单模型。
 */
class XFE_MagePlugin_Model_Admin_Whitelist extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_mageplugin/admin_whitelist');
    }
}
