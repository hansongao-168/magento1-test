<?php

/**
 * FTP账号 Grid - 状态列颜色徽章渲染器。
 *
 * 平行于 Account 同名 Renderer,根据 status 值输出 notice/critical 徽章。
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_FtpAccount_Grid_Renderer_Status
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Options
{
    /**
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $value = $row->getData($this->getColumn()->getIndex());
        $options = $this->getColumn()->getOptions();

        if (is_array($options) && isset($options[$value])) {
            $text = $options[$value];
        } else {
            $text = $value;
        }

        $class = $value ? 'grid-severity-notice' : 'grid-severity-critical';

        return '<span class="' . $class . '"><span>' . $this->escapeHtml($text) . '</span></span>';
    }
}