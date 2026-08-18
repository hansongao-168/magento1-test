<?php

class XFE_Carrier_Block_Adminhtml_Carrier extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_carrier';
        $this->_blockGroup = 'xfe_carrier';
        $this->_headerText = Mage::helper('xfe_carrier')->__('承运商管理');
        $this->_addButtonLabel = Mage::helper('xfe_carrier')->__('新增承运商');
        parent::__construct();

        // Bulk-import button (1.0.13+). Sits alongside the default "新增承运商"
        // button rendered by the grid container.
        $this->_addButton('import', array(
            'label'   => Mage::helper('xfe_carrier')->__('批量导入'),
            'onclick' => 'setLocation(\'' . $this->getUrl('*/carrier/import') . '\')',
            'class'   => 'scalable',
        ), -100);
    }
}
