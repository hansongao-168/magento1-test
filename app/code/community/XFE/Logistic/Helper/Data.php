<?php

/**
 * XFE_Logistic 数据 Helper。
 *
 * 负责读取 GLS 相关 system config，并组装 Basic Auth 认证头。
 * 属于工具层，供 Gateway / Service 复用，不承载业务判断。
 *
 * @category   Community
 * @package    XFE_Logistic
 */
class XFE_Logistic_Helper_Data extends Mage_Core_Helper_Abstract
{
    /** @var string 配置路径前缀 */
    const CONFIG_PATH_PREFIX = 'xfe_logistic/gls_api';

    /**
     * 读取 GLS system config 配置。
     *
     * @param string $field
     * @return string
     */
    public function getConfig($field)
    {
        return (string) Mage::getStoreConfig(self::CONFIG_PATH_PREFIX . '/' . $field);
    }

    /**
     * GLS Web API Test Base URL。
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->getConfig('base_url');
    }

    /**
     * POD 资源名（parcelpod）。
     *
     * @return string
     */
    public function getParcelPodResource()
    {
        return $this->getConfig('parcelpod_resource');
    }

    /**
     * 组装 POD 完整端点 URL。
     *
     * @return string
     */
    public function getParcelPodUrl()
    {
        return rtrim($this->getBaseUrl(), '/') . '/' . ltrim($this->getParcelPodResource(), '/');
    }

    /**
     * 读取并解密密码（后台配置经 encrypted backend 加密存储）。
     *
     * @return string
     */
    protected function _getDecryptedPassword()
    {
        $password = $this->getConfig('password');
        if ($password === '') {
            return '';
        }

        try {
            return (string) Mage::getModel('core/encryption')->decrypt($password);
        } catch (Exception $e) {
            // 若明文未加密（例如脚本直写配置），则原样返回
            return $password;
        }
    }

    /**
     * Basic Auth 认证头值，格式：Basic <Base64(username:password)>。
     *
     * @return string
     */
    public function getBasicAuthHeaderValue()
    {
        $username = $this->getConfig('username');
        $password = $this->_getDecryptedPassword();

        return XFE_Logistic_Domain_Constant_GlsApiConfig::AUTH_SCHEME_BASIC
            . ' ' . base64_encode($username . ':' . $password);
    }
}
