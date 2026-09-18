<?php
/**
 * 自定义属性表单 field_type 联动脚本(L4 Block)
 *
 * 仅输出 <script>,负责切换 options_csv 行的显隐与 default_value 字段的类型。
 * 不修改 XFE_Carrier_Block_Adminhtml_CustomAttribute_Edit_Form(字段定义)。
 * 不接触 Service / Domain / Resource。
 *
 * 关联文档:
 *   docs/architecture/carrier-custom-attribute-form-types.md
 *   docs/architecture/decisions/0008-custom-attribute-form-ux.md
 *
 * @category   XFE
 * @package    XFE_Carrier
 */
class XFE_Carrier_Block_Adminhtml_CustomAttribute_Edit_Form_TypeSwitcher
    extends Mage_Core_Block_Template
{
    /**
     * 模板路径
     *
     * @var string
     */
    protected $_template = 'xfe_carrier/custom_attribute/edit/form/type_switcher.phtml';
}
