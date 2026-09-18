<?php
/**
 * XFE_MagePlugin admin controller.
 *
 * 功能：
 *   - massSaveAction：后台 - 系统 - 权限 - 用户，批量修改登录名（username）。
 *   - sendSmsAction / verifySmsAction：登录 IP 白名单的短信验证码流程
 *     （未登录时可访问，配合重写 Mage_Admin_Model_Observer 放行）。
 *
 * URL： /admin/plugin/massSave/
 * 类名：XFE_MagePlugin_Adminhtml_PluginController（URL 第一段 plugin 映射到这里）。
 */
class XFE_MagePlugin_Adminhtml_PluginController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Process mass rename form submission from the Grid inline additional block.
     */
    public function massSaveAction()
    {
        if (!$this->_isAllowed()) {
            $this->_forward('denied');
            return;
        }

        $ids = $this->_getSelectedUserIds();
        $raw = $this->getRequest()->getParam('names');

        if (empty($ids)) {
            Mage::getSingleton('adminhtml/session')->addError(
                $this->__('请至少选择一个用户后再进行批量修改名字。')
            );
            $this->_redirect('adminhtml/permissions_user/index');
            return;
        }

        $success = 0;
        $skipped = 0;
        $errors = array();

        $allowedIds = array_flip($ids);
        $lines = $this->_parseTextareaLines($raw);

        if (empty($lines)) {
            Mage::getSingleton('adminhtml/session')->addError(
                $this->__('请按格式填写：每行「原用户名 新用户名」，中间用空格或 Tab 分隔。')
            );
            $this->_redirect('adminhtml/permissions_user/index');
            return;
        }

        foreach ($lines as $line) {
            $parts = preg_split('/[\t ]+/', trim($line), 2);
            if (count($parts) < 2) {
                $skipped++;
                continue;
            }

            $oldUsername = trim($parts[0]);
            $newUsername = trim($parts[1]);

            if ($oldUsername === '' || $newUsername === '') {
                $skipped++;
                continue;
            }
            if ($newUsername === $oldUsername) {
                $skipped++;
                continue;
            }
            if (preg_match('/[\s]/', $newUsername)) {
                $errors[] = $this->__('新用户名「%s」不能包含空格', $newUsername);
                continue;
            }

            /** @var Mage_Admin_Model_User $user */
            $user = Mage::getModel('admin/user')->loadByUsername($oldUsername);
            if (!$user->getId() || !isset($allowedIds[$user->getId()])) {
                $skipped++;
                continue;
            }

            // username uniqueness check
            $duplicate = Mage::getModel('admin/user')->loadByUsername($newUsername);
            if ($duplicate->getId() && $duplicate->getId() != $user->getId()) {
                $errors[] = $this->__('新用户名「%s」已被用户「%s」占用', $newUsername, $oldUsername);
                continue;
            }

            try {
                $user->setUsername($newUsername)->save();
                $success++;
            } catch (Exception $e) {
                $errors[] = $this->__('更新「%s」失败：%s', $oldUsername, $e->getMessage());
            }
        }

        if ($success > 0) {
            Mage::getSingleton('adminhtml/session')->addSuccess(
                $this->__('成功修改 %s 个用户的登录名。', $success)
            );
        }
        if ($skipped > 0) {
            Mage::getSingleton('adminhtml/session')->addNotice(
                $this->__('有 %s 行被跳过（格式不正确、用户不存在或未选中、或新旧登录名相同）。', $skipped)
            );
        }
        foreach ($errors as $error) {
            Mage::getSingleton('adminhtml/session')->addError($error);
        }

        $this->_redirect('adminhtml/permissions_user/index');
    }

    /**
     * Parse selected user ids from massaction form field.
     *
     * @return array
     */
    protected function _getSelectedUserIds()
    {
        $ids = $this->getRequest()->getParam('user_ids', '');
        if (is_array($ids)) {
            $ids = implode(',', $ids);
        }
        $ids = array_filter(array_map('intval', explode(',', (string)$ids)));
        return array_values(array_unique($ids));
    }

    /**
     * Split textarea content into non-empty trimmed lines.
     */
    protected function _parseTextareaLines($raw)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string)$raw);
        $result = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $result[] = $line;
            }
        }
        return $result;
    }

    /**
     * Check permission.
     *
     * 短信验证相关 action 允许未登录访问（用于登录 IP 白名单流程），
     * 其余 action 需 system/acl/users 权限。
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        $action = $this->getRequest()->getActionName();
        if (in_array($action, array('sendSms', 'verifySms'))) {
            return true;
        }
        return Mage::getSingleton('admin/session')->isAllowed('system/acl/users');
    }

    /**
     * 发送短信验证码。
     *
     * 要求：session 存在待短信验证状态，且提交的手机号与该用户配置一致。
     *
     * @return void
     */
    public function sendSmsAction()
    {
        $this->getResponse()->setHeader('Content-type', 'application/json');

        $pending = Mage::getSingleton('admin/session')
            ->getData(XFE_MagePlugin_Model_Observer::SESSION_SMS_PENDING_KEY);
        $phone = $this->getRequest()->getParam('phone');

        if (!is_array($pending) || empty($pending['user_id'])) {
            $this->_jsonError('会话已失效，请刷新页面重新登录。');
            return;
        }

        $userId = (int)$pending['user_id'];
        $expectedPhone = Mage::getResourceModel('xfe_mageplugin/admin_phone')->getPhoneByUserId($userId);
        if (!$expectedPhone || $expectedPhone !== $phone) {
            $this->_jsonError('手机号与该账号不匹配。');
            return;
        }

        $ip = Mage::getModel('xfe_mageplugin/service_clientIp')->getClientIp();
        if (!$ip || (isset($pending['ip']) && $pending['ip'] !== $ip)) {
            $this->_jsonError('IP 校验失败，请刷新页面重试。');
            return;
        }

        try {
            Mage::getModel('xfe_mageplugin/service_sms')->issueVerificationCode($userId, $phone, $ip);
        } catch (Mage_Core_Exception $e) {
            $this->_jsonError($e->getMessage());
            return;
        } catch (Exception $e) {
            $this->_jsonError('短信发送失败，请稍后重试。');
            return;
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
            'success' => true,
            'message' => '验证码已发送，请查收短信。',
        )));
    }

    /**
     * 校验短信验证码；验证通过后把当前 IP 加入白名单，返回登录页地址让用户重新登录。
     *
     * @return void
     */
    public function verifySmsAction()
    {
        $this->getResponse()->setHeader('Content-type', 'application/json');

        $pending = Mage::getSingleton('admin/session')
            ->getData(XFE_MagePlugin_Model_Observer::SESSION_SMS_PENDING_KEY);
        $phone = $this->getRequest()->getParam('phone');
        $code  = $this->getRequest()->getParam('code');

        if (!is_array($pending) || empty($pending['user_id'])) {
            $this->_jsonError('会话已失效，请刷新页面重新登录。');
            return;
        }

        $userId = (int)$pending['user_id'];
        $expectedPhone = Mage::getResourceModel('xfe_mageplugin/admin_phone')->getPhoneByUserId($userId);
        if (!$expectedPhone || $expectedPhone !== $phone) {
            $this->_jsonError('手机号与该账号不匹配。');
            return;
        }

        $ip = Mage::getModel('xfe_mageplugin/service_clientIp')->getClientIp();
        if (!$ip || (isset($pending['ip']) && $pending['ip'] !== $ip)) {
            $this->_jsonError('IP 校验失败，请刷新页面重试。');
            return;
        }

        // 找到该用户、该 IP 最近一次未使用的验证码会话
        /** @var XFE_MagePlugin_Model_Sms_Verification $session */
        $verificationId = $this->_getLatestUnverifiedId($userId, $ip);
        if (!$verificationId) {
            $this->_jsonError('请先获取验证码。');
            return;
        }

        if (!Mage::getModel('xfe_mageplugin/service_sms')->verifyCode($verificationId, $code, $ip)) {
            $this->_jsonError('验证码错误或已过期，请重试。');
            return;
        }

        // 验证通过：清除待验证状态，返回登录页地址
        Mage::getSingleton('admin/session')->unsetData(XFE_MagePlugin_Model_Observer::SESSION_SMS_PENDING_KEY);

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
            'success'      => true,
            'message'      => '验证成功，IP 已加入白名单，请重新登录。',
            'redirect_url' => Mage::helper('adminhtml')->getUrl('adminhtml', array('_nosecret' => true)),
        )));
    }

    /**
     * 查找某用户、某 IP 最近一次未使用的验证码会话 id。
     *
     * @param int    $userId
     * @param string $ip
     * @return int
     */
    protected function _getLatestUnverifiedId($userId, $ip)
    {
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $table = Mage::getSingleton('core/resource')->getTableName('xfe_mageplugin/sms_verification');
        $select = $read->select()
            ->from($table, 'entity_id')
            ->where('user_id = ?', (int)$userId)
            ->where('ip = ?', $ip)
            ->where('verified = 0')
            ->where('expires_at > ?', now())
            ->order('entity_id DESC')
            ->limit(1);
        return (int)$read->fetchOne($select);
    }

    /**
     * 输出 JSON 错误响应。
     *
     * @param string $message
     * @return void
     */
    protected function _jsonError($message)
    {
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array(
            'success' => false,
            'message' => $message,
        )));
    }
}