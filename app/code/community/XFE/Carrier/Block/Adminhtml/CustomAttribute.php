<?php

/**
 * 自定义属性管理 Container
 *
 * 渲染列表页头部(标题 / 新增按钮 / 导入 / 导出)。
 * 关联文档: docs/architecture/carrier-global-custom-field-defs.md
 */
class XFE_Carrier_Block_Adminhtml_CustomAttribute
    extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_customAttribute';
        $this->_blockGroup = 'xfe_carrier';
        $this->_headerText = Mage::helper('xfe_carrier')->__('自定义属性管理');
        $this->_addButtonLabel = Mage::helper('xfe_carrier')->__('新增自定义属性');
        $this->setTemplate('xfe_carrier/custom_attribute/grid/container.phtml');
        parent::__construct();

        // 批量导入按钮(commit 9 完整实现 Im/Ex 后即可用)
        $this->_addButton('import', array(
            'label'   => Mage::helper('xfe_carrier')->__('批量导入'),
            'onclick' => 'setLocation(\'' . $this->getUrl('*/carrier_customAttribute/import') . '\')',
            'class'   => 'scalable',
        ), -100);

        $this->_addButton('export', array(
            'label'   => Mage::helper('xfe_carrier')->__('批量导出'),
            'onclick' => 'setLocation(\'' . $this->getUrl('*/carrier_customAttribute/export') . '\')',
            'class'   => 'scalable',
        ), -110);
    }

    /**
     * @return string
     */
    public function getCreateUrl()
    {
        return $this->getUrl('*/carrier_customAttribute/new');
    }
}
