<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('carrier_edit_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('xfe_carrier')->__('承运商信息'));
        // Magento 标准左侧标签布局
        $this->setTemplate('widget/tabs.phtml');
    }

    protected function _beforeToHtml()
    {
        // 基础信息 Tab 固定为第一个
        $this->addTab('general', array(
            'label'   => Mage::helper('xfe_carrier')->__('基础信息'),
            'title'   => Mage::helper('xfe_carrier')->__('基础信息'),
            'content' => $this->getLayout()->createBlock(
                'xfe_carrier/adminhtml_carrier_edit_tab_general'
            )->toHtml(),
            'active'  => true,
        ));

        // 从 XML 配置动态读取模块
        $carrier = Mage::registry('xfe_carrier_data');
        $isEdit  = $carrier && $carrier->getId();
        $helper  = Mage::helper('xfe_carrier');
        $groups  = $helper->getCarrierModules();

        foreach ($groups as $groupCode => $group) {
            foreach ($group['modules'] as $code => $module) {
                // show_new = 0 时仅编辑态显示
                if (!$isEdit && !$module['show_new']) {
                    continue;
                }

                $tabId = $groupCode . '_' . $code;
                $this->addTab($tabId, array(
                    'label'   => $helper->__($module['label']),
                    'title'   => $helper->__($module['label']),
                    'content' => $this->getLayout()->createBlock(
                        $module['block']
                    )->toHtml(),
                ));
            }
        }

        return parent::_beforeToHtml();
    }
}
