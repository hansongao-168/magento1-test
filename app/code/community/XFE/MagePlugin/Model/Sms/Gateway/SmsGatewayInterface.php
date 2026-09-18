<?php
/**
 * 短信网关接口（可扩展）。
 *
 * 后续接入真实短信服务商（阿里云 / 腾讯云 / Twilio 等）时，
 * 只需新增实现此接口的 Gateway 类，并在 config.xml 中切换 sms_gateway 配置。
 */
interface XFE_MagePlugin_Model_Sms_Gateway_SmsGatewayInterface
{
    /**
     * 发送短信验证码。
     *
     * @param string $phone 手机号
     * @param string $code  验证码
     * @return bool 发送成功与否
     */
    public function sendVerificationCode($phone, $code);
}
