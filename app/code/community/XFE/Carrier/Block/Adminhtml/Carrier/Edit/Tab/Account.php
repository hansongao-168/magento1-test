<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Account
    extends Mage_Adminhtml_Block_Widget
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Render tab content: account grid only
     *
     * @return string
     */
    protected function _toHtml()
    {
        $model = Mage::registry('xfe_carrier_data');

        if (!$model || !$model->getId()) {
            return '<div class="entry-edit">'
                . '<div class="entry-edit-head"><h4 class="icon-head head-edit-form fieldset-legend">'
                . Mage::helper('xfe_carrier')->__('提示') . '</h4></div>'
                . '<div class="fieldset"><p style="color:#999;">'
                . Mage::helper('xfe_carrier')->__('保存承运商基本信息后即可管理账号。') . '</p></div>'
                . '</div>';
        }

        $html = '<div id="account-tab-wrapper">';
        $html .= $this->getLayout()->createBlock(
            'xfe_carrier/adminhtml_carrier_edit_tab_account_grid'
        )->toHtml();
        $html .= '</div>';

        return $html;
    }

    public function getTabLabel()
    {
        return Mage::helper('xfe_carrier')->__('账号管理');
    }

    public function getTabTitle()
    {
        return Mage::helper('xfe_carrier')->__('账号管理');
    }

    public function canShowTab()
    {
        return true;
    }

    public function isHidden()
    {
        return false;
    }
}
