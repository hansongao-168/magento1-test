<?php
/**
 * XFE_MagePlugin observer.
 *
 * 现有：
 *   - 批量修改后台用户登录名（由 Block 子类 + PluginController 完成）。
 *
 * 新增：
 *   - beforeAuthenticateIpGuard()：监听 admin_user_authenticate_before，
 *     在后台用户认证前做登录 IP 白名单校验。
 *
 * 说明：Mage Grid 的 massaction 注入由 Block 子类完成（详见 Block 注释）。
 */
class XFE_MagePlugin_Model_Observer
{
    /** Session 中「待短信验证」的键 */
    const SESSION_SMS_PENDING_KEY = 'xfe_mageplugin_sms_pending';

    /**
     * 后台用户认证前 IP 校验。
     *
     * 事件：admin_user_authenticate_before（在 Mage_Admin_Model_User::authenticate() 中，密码验证之前）。
     *
     * @param Varien_Event_Observer $observer
     * @return void
     * @throws Mage_Core_Exception 当 IP 不允许登录时
     */
    public function beforeAuthenticateIpGuard(Varien_Event_Observer $observer)
    {
        // 总开关：xfe_mageplugin/login/enabled，默认关闭（不拦截任何登录）
        if (!(int)Mage::getStoreConfig('xfe_mageplugin/login/enabled')) {
            return;
        }

        try {
            $this->_doIpGuardCheck($observer);
        } catch (Exception $e) {
            // 数据库表未建立 / 配置异常等：不因 IP 过滤阻塞登录，记录日志
            Mage::log(
                '[XFE_MagePlugin][IpGuard] 跳过 IP 校验（异常）：' . $e->getMessage(),
                null,
                'xfe_mageplugin_ipguard.log',
                true
            );
        }
    }

    /**
     * 执行 IP 白名单校验。
     *
     * @param Varien_Event_Observer $observer
     * @return void
     * @throws Mage_Core_Exception
     */
    protected function _doIpGuardCheck(Varien_Event_Observer $observer)
    {
        /** @var Mage_Admin_Model_User $user */
        $user = $observer->getEvent()->getUser();
        $username = $observer->getEvent()->getUsername();

        // 先按用户名加载用户以获取 user_id
        $user->loadByUsername($username);
        if (!$user->getId()) {
            return; // 用户不存在，交给后续认证处理
        }

        $ip = Mage::getModel('xfe_mageplugin/service_clientIp')->getClientIp();
        if (!$ip) {
            return;
        }

        /** @var XFE_MagePlugin_Model_Service_IpGuard $guard */
        $guard = Mage::getModel('xfe_mageplugin/service_ipGuard');
        $result = $guard->check((int)$user->getId(), $ip);

        if ($result === XFE_MagePlugin_Model_Service_IpGuard::RESULT_ALLOWED) {
            $this->_clearSmsPending();
            return;
        }

        if ($result === XFE_MagePlugin_Model_Service_IpGuard::RESULT_NEED_SMS) {
            // 记录待短信验证状态，抛异常让登录失败，登录页将显示验证码输入框
            $this->_setSmsPending((int)$user->getId(), $ip);
            Mage::throwException(
                Mage::helper('xfe_mageplugin')->__('当前 IP 不在白名单，需短信验证后才能登录。')
            );
        }

        // RESULT_DENIED：拒绝登录
        Mage::throwException(
            Mage::helper('xfe_mageplugin')->__('当前 IP 不在该账号的白名单中，禁止登录。')
        );
    }

    /**
     * 记录待短信验证状态到 admin session。
     *
     * @param int    $userId
     * @param string $ip
     * @return void
     */
    protected function _setSmsPending($userId, $ip)
    {
        $session = Mage::getSingleton('admin/session');
        $session->setData(self::SESSION_SMS_PENDING_KEY, array(
            'user_id' => (int)$userId,
            'ip'      => $ip,
        ));
    }

    /**
     * 清除待短信验证状态。
     *
     * @return void
     */
    protected function _clearSmsPending()
    {
        Mage::getSingleton('admin/session')->unsetData(self::SESSION_SMS_PENDING_KEY);
    }
}
