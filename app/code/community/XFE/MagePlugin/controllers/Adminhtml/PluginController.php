<?php
/**
 * XFE_MagePlugin admin controller.
 *
 * 后台 - 系统 - 权限 - 用户：批量修改登录名（username）
 *   massSaveAction : 接收 Grid 上 textarea 提交，逐行更新 username
 *
 * Grid 上的 textarea 由 Grid 子类通过 additional 块直接渲染（不需要新页面）。
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
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/acl/users');
    }
}