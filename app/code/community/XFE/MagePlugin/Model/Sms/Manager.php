<?php
/**
 * XFE_MagePlugin 短信发送管理器。
 *
 * 根据 config.xml 中 xfe_mageplugin/login/sms_gateway 配置选择网关实现。
 */
class XFE_MagePlugin_Model_Sms_Manager
{
    /**
     * 发送短信验证码。
     *
     * @param string $phone
     * @param string $code
     * @return bool
     */
    public function sendVerificationCode($phone, $code)
    {
        $gateway = $this->_getGateway();
        if (!$gateway) {
            Mage::log('[XFE_MagePlugin][SMS] 未配置有效的短信网关，验证码未发送', null, 'xfe_mageplugin_sms.log', true);
            return false;
        }
        return (bool)$gateway->sendVerificationCode($phone, $code);
    }

    /**
     * 获取短信网关实例。
     *
     * @return XFE_MagePlugin_Model_Sms_Gateway_SmsGatewayInterface|null
     */
    protected function _getGateway()
    {
        $type = (string)Mage::getStoreConfig('xfe_mageplugin/login/sms_gateway');
        if (!$type) {
            return null;
        }

        $class = 'xfe_mageplugin/sms_gateway_' . $type;
        $gateway = Mage::getModel($class);
        if ($gateway instanceof XFE_MagePlugin_Model_Sms_Gateway_SmsGatewayInterface) {
            return $gateway;
        }
        return null;
    }
}
