<?php

/**
 * Renderer for dimensions column (width x height px)
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Logo_Grid_Renderer_Dimensions
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        $width = $row->getData('width');
        $height = $row->getData('height');

        if ($width && $height) {
            return (int)$width . ' x ' . (int)$height . ' px';
        }

        return '-';
    }
}
