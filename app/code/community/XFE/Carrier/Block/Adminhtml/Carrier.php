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

        // Rule bulk-import / export buttons.
        $this->_addButton('rule_import', array(
            'label'   => Mage::helper('xfe_carrier')->__('规则批量导入'),
            'onclick' => 'setLocation(\'' . $this->getUrl('*/carrier/ruleImport') . '\')',
            'class'   => 'scalable',
        ), -110);

        $this->_addButton('rule_export', array(
            'label'   => Mage::helper('xfe_carrier')->__('规则批量导出'),
            'onclick' => 'setLocation(\'' . $this->getUrl('*/carrier/ruleExport') . '\')',
            'class'   => 'scalable',
        ), -120);

        // Account bulk-import / export buttons.
        $this->_addButton('account_import', array(
            'label'   => Mage::helper('xfe_carrier')->__('账号批量导入'),
            'onclick' => 'setLocation(\'' . $this->getUrl('*/carrier/accountImport') . '\')',
            'class'   => 'scalable',
        ), -130);

        $this->_addButton('account_export', array(
            'label'   => Mage::helper('xfe_carrier')->__('账号批量导出'),
            'onclick' => 'setLocation(\'' . $this->getUrl('*/carrier/accountExport') . '\')',
            'class'   => 'scalable',
        ), -140);

        // FtpAccount bulk-import / export buttons.
        $this->_addButton('ftp_account_import', array(
            'label'   => Mage::helper('xfe_carrier')->__('FTP账号批量导入'),
            'onclick' => 'setLocation(\'' . $this->getUrl('*/carrier/ftpAccountImport') . '\')',
            'class'   => 'scalable',
        ), -150);

        $this->_addButton('ftp_account_export', array(
            'label'   => Mage::helper('xfe_carrier')->__('FTP账号批量导出'),
            'onclick' => 'setLocation(\'' . $this->getUrl('*/carrier/ftpAccountExport') . '\')',
            'class'   => 'scalable',
        ), -160);
    }
}
