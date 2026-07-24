<?php
/**
 * Color Grid Column Renderer
 *
 * Renders a color preview swatch for the grid column
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_Block_Adminhtml_Rule_Grid_Renderer_Color
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Render color swatch
     *
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $color = $row->getData($this->getColumn()->getIndex());
        if (empty($color)) {
            return '<span style="color:#999;">' . Mage::helper('xcarrierslabel')->__('N/A') . '</span>';
        }

        return '<span style="display:inline-block;width:20px;height:20px;background:' . $this->escapeHtml($color) . ';border:1px solid #ccc;vertical-align:middle;margin-right:5px;border-radius:3px;"></span>'
             . '<span>' . $this->escapeHtml($color) . '</span>';
    }
}
