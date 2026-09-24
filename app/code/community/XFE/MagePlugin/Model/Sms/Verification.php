<?php
/**
 * XFE_MagePlugin 短信验证码会话模型。
 */
class XFE_MagePlugin_Model_Sms_Verification extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_mageplugin/sms_verification');
    }
}
