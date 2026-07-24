<?php
/**
 * Social Login Buttons Block
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Block_Social_Buttons extends Mage_Core_Block_Template
{
    /**
     * Get enabled social login providers
     *
     * @return array
     */
    public function getProviders()
    {
        $providers = array();
        $helper = Mage::helper('xfeoauth2');

        if ($helper->getConfig('google/enabled')) {
            $providers['google'] = array(
                'label' => $this->__('Google'),
                'url'   => Mage::getUrl('oauth2/login/google'),
                'icon'  => $this->getSkinUrl('xfe_oauth2/images/btn-google.svg'),
            );
        }

        if ($helper->getConfig('wechat/enabled')) {
            $providers['wechat'] = array(
                'label' => $this->__('WeChat'),
                'url'   => Mage::getUrl('oauth2/login/wechat'),
                'icon'  => $this->getSkinUrl('xfe_oauth2/images/btn-wechat.svg'),
            );
        }

        return $providers;
    }
}
