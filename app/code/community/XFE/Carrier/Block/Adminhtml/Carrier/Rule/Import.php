<?php

/**
 * 承运商规则批量导入落地页（容器）。
 *
 * 渲染上传表单（模板：xfe_carrier/carrier/rule/import/container.phtml）。
 * 对应 Controller 动作：CarrierController::ruleImportAction()。
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Rule_Import extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId   = 'import_id';
        $this->_blockGroup = 'xfe_carrier';
        $this->_controller = 'adminhtml_carrier';
        $this->_mode       = 'import';

        parent::__construct();

        // 上传页不需要默认的保存/删除按钮；表单自带"上传并导入"按钮。
        $this->_removeButton('save');
        $this->_removeButton('delete');
        $this->_removeButton('reset');
        $this->_removeButton('back');

        $this->_addButton('back', array(
            'label'   => Mage::helper('xfe_carrier')->__('返回列表'),
            'onclick' => 'setLocation(\'' . $this->getUrl('*/carrier/') . '\')',
            'class'   => 'back',
        ));

        $this->_addButton('download_template', array(
            'label'   => Mage::helper('xfe_carrier')->__('下载规则导入模板'),
            'onclick' => 'setLocation(\'' . $this->getUrl('*/*/ruleDownloadTemplate') . '\')',
            'class'   => 'scalable',
        ));

        $this->setTemplate('xfe_carrier/carrier/rule/import/container.phtml');
    }

    /**
     * 阻止父类（Mage_Adminhtml_Block_Widget_Form_Container）在 _prepareLayout()
     * 中自动用 _controller/_mode 拼接出的 form 类覆盖布局 XML 里定义的
     * form 子块。本容器继承 Form_Container 只是复用其按钮/模板机制，
     * 上传表单由布局 XML 中的 <block as="form">（规则导入 Form）提供。
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        Mage_Adminhtml_Block_Widget_Container::_prepareLayout();
        return $this;
    }

    public function getHeaderText()
    {
        return Mage::helper('xfe_carrier')->__('批量导入承运商规则');
    }

    /**
     * 上传表单的提交地址。
     *
     * @return string
     */
    public function getFormActionUrl()
    {
        return $this->getUrl('*/*/ruleImportPost');
    }
}
