<?php
/**
 * XFE_MagePlugin 登录短信验证表单 Block。
 *
 * 当登录被 IP 守卫拦截（需短信验证）时，在登录页展示验证码输入表单。
 */
class XFE_MagePlugin_Block_Adminhtml_Login_Sms extends Mage_Core_Block_Template
{
    /**
     * 是否处于「待短信验证」状态。
     *
     * @return bool
     */
    public function isPendingSms()
    {
        $pending = Mage::getSingleton('admin/session')
            ->getData(XFE_MagePlugin_Model_Observer::SESSION_SMS_PENDING_KEY);
        return is_array($pending) && !empty($pending);
    }

    /**
     * 获取待验证用户 id。
     *
     * @return int
     */
    public function getPendingUserId()
    {
        $pending = Mage::getSingleton('admin/session')
            ->getData(XFE_MagePlugin_Model_Observer::SESSION_SMS_PENDING_KEY);
        return isset($pending['user_id']) ? (int)$pending['user_id'] : 0;
    }

    /**
     * 获取待验证 IP。
     *
     * @return string
     */
    public function getPendingIp()
    {
        $pending = Mage::getSingleton('admin/session')
            ->getData(XFE_MagePlugin_Model_Observer::SESSION_SMS_PENDING_KEY);
        return isset($pending['ip']) ? $pending['ip'] : '';
    }

    /**
     * 获取发送验证码的 URL。
     *
     * @return string
     */
    public function getSendSmsUrl()
    {
        return $this->getUrl('adminhtml/plugin/sendSms', array('_nosecret' => true));
    }

    /**
     * 获取验证验证码的 URL。
     *
     * @return string
     */
    public function getVerifySmsUrl()
    {
        return $this->getUrl('adminhtml/plugin/verifySms', array('_nosecret' => true));
    }
}
