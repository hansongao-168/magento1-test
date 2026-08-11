<?php
/**
 * Admin Client Edit Form
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Block_Adminhtml_Client_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $helper = Mage::helper('xfeoauth2');
        $model = Mage::registry('xfeoauth2_client');
        $isNew = !$model || !$model->getId();

        $form = new Varien_Data_Form(array(
            'id'      => 'edit_form',
            'action'  => $this->getUrl('*/*/save'),
            'method'  => 'post',
            'enctype' => 'multipart/form-data',
        ));

        $fieldset = $form->addFieldset('base', array(
            'legend' => $helper->__('Client Details'),
        ));

        if (!$isNew) {
            $fieldset->addField('client_id', 'label', array(
                'label' => $helper->__('Client ID'),
                'title' => $helper->__('Client ID'),
                'value' => $model->getClientId(),
            ));
        }

        $fieldset->addField('name', 'text', array(
            'label'    => $helper->__('Name'),
            'title'    => $helper->__('Name'),
            'name'     => 'name',
            'required' => true,
            'value'    => $model->getName(),
        ));

        $fieldset->addField('description', 'textarea', array(
            'label' => $helper->__('Description'),
            'title' => $helper->__('Description'),
            'name'  => 'description',
            'value' => $model->getDescription(),
        ));

        $fieldset->addField('redirect_uri', 'text', array(
            'label' => $helper->__('Redirect URI'),
            'title' => $helper->__('Redirect URI'),
            'name'  => 'redirect_uri',
            'class' => 'validate-url',
            'value' => $model->getRedirectUri(),
            'note'  => $helper->__('Required for authorization_code grant type'),
        ));

        $fieldset->addField('grant_types', 'text', array(
            'label' => $helper->__('Grant Types'),
            'title' => $helper->__('Grant Types'),
            'name'  => 'grant_types',
            'value' => $model->getGrantTypes(),
            'note'  => $helper->__('Comma separated: authorization_code, client_credentials, refresh_token'),
        ));

        $fieldset->addField('scopes', 'text', array(
            'label' => $helper->__('Allowed Scopes'),
            'title' => $helper->__('Allowed Scopes'),
            'name'  => 'scopes',
            'value' => $model->getScopes(),
            'note'  => $helper->__('Space separated: basic orders customers carriers admin'),
        ));

        $fieldset->addField('status', 'select', array(
            'label'  => $helper->__('Status'),
            'title'  => $helper->__('Status'),
            'name'   => 'status',
            'values' => array(
                array('value' => 1, 'label' => $helper->__('Active')),
                array('value' => 0, 'label' => $helper->__('Disabled')),
            ),
            'value' => $model->getStatus() !== null ? $model->getStatus() : 1,
        ));

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
