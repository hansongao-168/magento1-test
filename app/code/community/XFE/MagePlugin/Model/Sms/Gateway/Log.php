<?php
/**
 * 日志/测试短信网关。
 *
 * 不真正发送短信，仅把验证码写入系统日志，用于开发和联调。
 * 生产环境应替换为真实服务商实现。
 */
class XFE_MagePlugin_Model_Sms_Gateway_Log implements XFE_MagePlugin_Model_Sms_Gateway_SmsGatewayInterface
{
    /**
     * 发送短信验证码（写入日志）。
     *
     * @param string $phone
     * @param string $code
     * @return bool
     */
    public function sendVerificationCode($phone, $code)
    {
        Mage::log(sprintf('[XFE_MagePlugin][SMS][%s] 验证码：%s', $phone, $code), null, 'xfe_mageplugin_sms.log', true);
        return true;
    }
}
