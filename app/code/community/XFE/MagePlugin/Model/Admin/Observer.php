<?php
/**
 * XFE_MagePlugin 重写 Mage_Admin_Model_Observer。
 *
 * 在 actionPreDispatchAdmin 中把「短信验证」相关 action 视为 open action，
 * 使未登录用户在登录页发起短信验证 AJAX 请求时不被强制转发到登录页。
 *
 * 其余逻辑与核心完全一致，未修改核心文件。
 */
class XFE_MagePlugin_Model_Admin_Observer extends Mage_Admin_Model_Observer
{
    /**
     * 短信验证相关 action（controller_action 名称）。
     *
     * @return array
     */
    protected function _getXfeSmsOpenActions()
    {
        return array(
            'plugin/sendSms',
            'plugin/verifySms',
        );
    }

    /**
     * Handler for controller_action_predispatch event.
     *
     * @param Varien_Event_Observer $observer
     * @return boolean
     */
    public function actionPreDispatchAdmin($observer)
    {
        /** @var $session Mage_Admin_Model_Session */
        $session = Mage::getSingleton('admin/session');

        /** @var $request Mage_Core_Controller_Request_Http */
        $request = Mage::app()->getRequest();
        $user = $session->getUser();

        $requestedActionName = strtolower($request->getActionName());
        $controllerAction = strtolower($request->getControllerName() . '/' . $request->getActionName());

        $openActions = array(
            'forgotpassword',
            'resetpassword',
            'resetpasswordpost',
            'logout',
            'refresh' // captcha refresh
        );

        // 追加短信验证相关 action（未登录可访问）
        foreach ($this->_getXfeSmsOpenActions() as $smsAction) {
            if (strtolower($smsAction) === $controllerAction) {
                $request->setDispatched(true);
                $session->refreshAcl();
                return false;
            }
        }

        if (in_array($requestedActionName, $openActions)) {
            $request->setDispatched(true);
        } else {
            if ($user) {
                $user->reload();
            }
            if (!$user || !$user->getId()) {
                if ($request->getPost('login')) {

                    /** @var Mage_Core_Model_Session $coreSession */
                    $coreSession = Mage::getSingleton('core/session');

                    if ($coreSession->validateFormKey($request->getPost("form_key"))) {
                        $postLogin = $request->getPost('login');
                        $username = isset($postLogin['username']) ? $postLogin['username'] : '';
                        $password = isset($postLogin['password']) ? $postLogin['password'] : '';
                        $session->login($username, $password, $request);
                        $request->setPost('login', null);
                    } else {
                        if ($request && !$request->getParam('messageSent')) {
                            Mage::getSingleton('adminhtml/session')->addError(
                                Mage::helper('adminhtml')->__('Invalid Form Key. Please refresh the page.')
                            );
                            $request->setParam('messageSent', true);
                        }
                    }

                    $coreSession->renewFormKey();
                }
                if (!$request->getInternallyForwarded()) {
                    $request->setInternallyForwarded();
                    if ($request->getParam('isIframe')) {
                        $request->setParam('forwarded', true)
                            ->setControllerName('index')
                            ->setActionName('deniedIframe')
                            ->setDispatched(false);
                    } elseif ($request->getParam('isAjax')) {
                        $request->setParam('forwarded', true)
                            ->setControllerName('index')
                            ->setActionName('deniedJson')
                            ->setDispatched(false);
                    } else {
                        $request->setParam('forwarded', true)
                            ->setRouteName('adminhtml')
                            ->setControllerName('index')
                            ->setActionName('login')
                            ->setDispatched(false);
                    }
                    return false;
                }
            }
        }

        $session->refreshAcl();
        return false;
    }
}
