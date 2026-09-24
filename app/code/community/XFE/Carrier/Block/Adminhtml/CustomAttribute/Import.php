<?php

/**
 * 自定义属性批量导入 - 容器 Block
 */
class XFE_Carrier_Block_Adminhtml_CustomAttribute_Import
    extends Mage_Adminhtml_Block_Template
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('xfe_carrier/custom_attribute/import/container.phtml');
    }
}
