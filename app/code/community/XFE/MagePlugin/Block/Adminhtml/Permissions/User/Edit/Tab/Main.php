<?php
/**
 * XFE_MagePlugin 后台用户编辑表单（手机号 + IP 白名单）。
 *
 * 在 Mage_Adminhtml_Block_Permissions_User_Edit_Tab_Main 基础上，
 * 追加两个字段：
 *   - 手机号：用于登录短信验证。
 *   - IP 白名单：限制该用户只能在指定 IP 登录（按需配置，留空则不限制）。
 */
class XFE_MagePlugin_Block_Adminhtml_Permissions_User_Edit_Tab_Main extends Mage_Adminhtml_Block_Permissions_User_Edit_Tab_Main
{
    /**
     * 配置表单。
     *
     * @return Mage_Adminhtml_Block_Permissions_User_Edit_Tab_Main
     */
    protected function _prepareForm()
    {
        parent::_prepareForm();

        /** @var Mage_Admin_Model_User $model */
        $model = Mage::registry('permissions_user');
        $form = $this->getForm();
        if (!$form) {
            return $this;
        }

        $fieldset = $form->addFieldset('xfe_mageplugin_fieldset', array(
            'legend' => Mage::helper('xfe_mageplugin')->__('登录 IP 白名单 / 短信验证'),
        ));

        $fieldset->addField('xfe_phone', 'text', array(
            'name'  => 'xfe_phone',
            'label' => Mage::helper('xfe_mageplugin')->__('手机号'),
            'title' => Mage::helper('xfe_mageplugin')->__('用于登录短信验证的手机号'),
            'class' => 'input-text',
        ));

        $fieldset->addField('xfe_ip_whitelist', 'textarea', array(
            'name'  => 'xfe_ip_whitelist',
            'label' => Mage::helper('xfe_mageplugin')->__('IP 白名单'),
            'title' => Mage::helper('xfe_mageplugin')->__('每行一个 IP，留空表示不限制登录 IP'),
            'style' => 'height: 100px;',
            'note'  => Mage::helper('xfe_mageplugin')->__('每行一个 IP。仅配置了白名单的用户会受登录 IP 限制；用户 1~5 可用短信验证添加新 IP。'),
        ));

        // 将模型上的扩展字段写入表单值
        $form->setValues(array(
            'xfe_phone'        => $model->getData('xfe_phone'),
            'xfe_ip_whitelist' => $model->getData('xfe_ip_whitelist'),
        ));

        return $this;
    }
}
