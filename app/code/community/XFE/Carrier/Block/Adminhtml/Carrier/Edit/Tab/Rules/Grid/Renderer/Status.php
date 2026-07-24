<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Rules_Grid_Renderer_Status
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Options
{
    /**
     * Render status column with colored severity badge
     *
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
