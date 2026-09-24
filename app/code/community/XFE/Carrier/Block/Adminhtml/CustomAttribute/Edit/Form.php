<?php

/**
 * 自定义属性编辑表单
 *
 * 字段: entity_type / field_key / label / field_type / options_csv /
 *       default_value / is_required / is_active / sort_order / description
 *
 * 关联文档: docs/architecture/carrier-global-custom-field-defs.md
 */
class XFE_Carrier_Block_Adminhtml_CustomAttribute_Edit_Form
    extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $helper = Mage::helper('xfe_carrier');
        $model  = Mage::registry('xfe_carrier_custom_attribute_data');
        if (!$model) {
            $model = Mage::getModel('xfe_carrier/custom_attribute');
        }

        $form = new Varien_Data_Form(array(
            'id'      => 'edit_form',
            'action'  => $this->getUrl('*/carrier_customAttribute/save',
                $model->getId() ? array('id' => $model->getId()) : array()),
            'method'  => 'post',
            'enctype' => 'multipart/form-data',
        ));
        $form->setUseContainer(true);
        $this->setForm($form);

        $fieldset = $form->addFieldset('base_fieldset', array(
            'legend' => $helper->__('基础信息'),
        ));

        $isEdit = (bool) $model->getId();

        $fieldset->addField('entity_type', 'select', array(
            'name'     => 'entity_type',
            'label'    => $helper->__('分类'),
            'title'    => $helper->__('分类'),
            'required' => true,
            'disabled' => $isEdit,
            'values'   => array(
                XFE_Carrier_Domain_CustomAttribute::ENTITY_TYPE_CARRIER     => $helper->__('承运商信息'),
                XFE_Carrier_Domain_CustomAttribute::ENTITY_TYPE_ACCOUNT     => $helper->__('账号'),
                XFE_Carrier_Domain_CustomAttribute::ENTITY_TYPE_FTP_ACCOUNT => $helper->__('FTP'),
                XFE_Carrier_Domain_CustomAttribute::ENTITY_TYPE_LOGO        => $helper->__('LOGO'),
            ),
        ));

        $fieldset->addField('field_key', 'text', array(
            'name'     => 'field_key',
            'label'    => $helper->__('Key(英文/数字/下划线)'),
            'title'    => $helper->__('Key'),
            'required' => true,
            'disabled' => $isEdit,
            'note'     => $helper->__('仅允许小写字母 / 数字 / 下划线,1~64 字符。创建后不可改。'),
        ));

        $fieldset->addField('label', 'text', array(
            'name'     => 'label',
            'label'    => $helper->__('展示名'),
            'title'    => $helper->__('展示名'),
            'required' => true,
            'note'     => $helper->__('1~64 字符,显示在编辑页标签。'),
        ));

        $fieldset->addField('field_type', 'select', array(
            'name'     => 'field_type',
            'label'    => $helper->__('类型'),
            'title'    => $helper->__('类型'),
            'required' => true,
            'values'   => array(
                'text'        => $helper->__('文本'),
                'number'      => $helper->__('数字'),
                'select'      => $helper->__('下拉(单选)'),
                'multiselect' => $helper->__('多选'),
                'boolean'     => $helper->__('布尔'),
            ),
        ));

        $fieldset->addField('options_csv', 'text', array(
            'name'  => 'options_csv',
            'label' => $helper->__('候选项(逗号分隔)'),
            'title' => $helper->__('候选项'),
            'note'  => $helper->__('仅 select / multiselect 需填。空 = multiselect 自由标签。'),
        ));

        $fieldset->addField('default_value', 'text', array(
            'name'  => 'default_value',
            'label' => $helper->__('默认值'),
            'title' => $helper->__('默认值'),
            'note'  => $helper->__('multiselect 用 | 分隔(如 a|b|c);其他类型按字面。'),
        ));

        $fieldset->addField('is_required', 'checkbox', array(
            'name'  => 'is_required',
            'label' => $helper->__('必填'),
            'title' => $helper->__('必填'),
            'value' => 1,
            'note'  => $helper->__('勾选后,该分类下每个实体编辑时若留空则保存失败。'),
        ));

        $fieldset->addField('is_active', 'checkbox', array(
            'name'  => 'is_active',
            'label' => $helper->__('启用'),
            'title' => $helper->__('启用'),
            'value' => 1,
            'note'  => $helper->__('取消勾选 = 软删除(旧数据保留,下拉里不再出现)。'),
        ));

        $fieldset->addField('sort_order', 'text', array(
            'name'  => 'sort_order',
            'label' => $helper->__('排序'),
            'title' => $helper->__('排序'),
            'class' => 'validate-number',
        ));

        $fieldset->addField('description', 'textarea', array(
            'name'  => 'description',
            'label' => $helper->__('说明'),
            'title' => $helper->__('说明'),
            'note'  => $helper->__('显示在编辑页字段下方的提示文字。'),
        ));

        // 反序列化 default_value: JSON → 原始(给用户看);保存时由 Service 重新 JSON 化
        if ($model->getData('default_value')) {
            $decoded = json_decode($model->getData('default_value'), true);
            if (is_array($decoded)) {
                $model->setData('default_value', implode('|', $decoded));
            }
        }

        $form->setValues($model->getData());
        return parent::_prepareForm();
    }
}
