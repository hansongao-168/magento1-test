<?php
/**
 * XFE_MagePlugin 短信验证码业务服务（L3 Service）。
 *
 * 负责验证码生成、发送、校验、以及验证通过后加入 IP 白名单。
 */
class XFE_MagePlugin_Model_Service_Sms
{
    /**
     * 生成并发送验证码，返回验证码会话 id。
     *
     * @param int    $userId
     * @param string $phone
     * @param string $ip
     * @return int 会话 entity_id
     * @throws Mage_Core_Exception
     */
    public function issueVerificationCode($userId, $phone, $ip)
    {
        if (!$phone) {
            Mage::throwException(Mage::helper('xfe_mageplugin')->__('该用户未配置手机号，无法发送短信验证码。'));
        }

        $code = $this->_generateCode();
        $ttl  = (int)Mage::getStoreConfig('xfe_mageplugin/login/sms_code_ttl');
        if ($ttl <= 0) {
            $ttl = 600;
        }

        /** @var XFE_MagePlugin_Model_Sms_Verification $session */
        $session = Mage::getModel('xfe_mageplugin/sms_verification');
        $session->setUserId($userId)
            ->setPhone($phone)
            ->setIp($ip)
            ->setCodeHash($this->_hashCode($code))
            ->setVerified(0)
            ->setExpiresAt(date('Y-m-d H:i:s', time() + $ttl))
            ->save();

        // 清理过期会话（顺带）
        Mage::getResourceModel('xfe_mageplugin/sms_verification')->deleteExpired();

        $sent = Mage::getModel('xfe_mageplugin/sms_manager')->sendVerificationCode($phone, $code);
        if (!$sent) {
            Mage::throwException(Mage::helper('xfe_mageplugin')->__('短信发送失败，请稍后重试。'));
        }

        return (int)$session->getId();
    }

    /**
     * 校验验证码。
     *
     * @param int    $verificationId
     * @param string $submittedCode
     * @param string $ip
     * @return bool
     */
    public function verifyCode($verificationId, $submittedCode, $ip)
    {
        /** @var XFE_MagePlugin_Model_Sms_Verification $session */
        $session = Mage::getModel('xfe_mageplugin/sms_verification')->load($verificationId);
        if (!$session->getId()) {
            return false;
        }
        if ((int)$session->getVerified() === 1) {
            return false; // 已使用
        }
        if ($session->getIp() !== $ip) {
            return false; // IP 不一致
        }
        if (strtotime($session->getExpiresAt()) < time()) {
            return false; // 过期
        }
        if (!Mage::helper('core')->validateHash($submittedCode, $session->getCodeHash())) {
            return false;
        }

        $session->setVerified(1)->save();

        // 验证通过：把该 IP 加入用户白名单
        Mage::getResourceModel('xfe_mageplugin/admin_whitelist')
            ->addIp((int)$session->getUserId(), $ip, 1);

        return true;
    }

    /**
     * 生成 6 位数字验证码。
     *
     * @return string
     */
    protected function _generateCode()
    {
        return (string)mt_rand(100000, 999999);
    }

    /**
     * 哈希验证码。
     *
     * @param string $code
     * @return string
     */
    protected function _hashCode($code)
    {
        return Mage::helper('core')->getHash($code);
    }
}
