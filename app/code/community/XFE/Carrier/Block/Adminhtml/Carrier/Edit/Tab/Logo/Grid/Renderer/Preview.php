<?php

/**
 * Renderer for logo preview column
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Logo_Grid_Renderer_Preview
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        $path = $row->getData('path');
        if (!$path) {
            return '-';
        }

        $mediaUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
        $imgUrl = $mediaUrl . $path;
        $label = $this->escapeHtml($row->getData('label') ?: 'Logo');

        return '<img src="' . $this->escapeUrl($imgUrl) . '" alt="' . $label
             . '" style="max-width:100px; max-height:60px;" />';
    }
}
