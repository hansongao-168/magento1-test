<?php

class XFE_LabelPrint_Block_Adminhtml_Print_Grid_Renderer_Additional
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Pretty-print the JSON stored in additional_data. When the stored
     * value is not valid JSON, fall back to the raw text.
     *
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $raw = (string)$row->getData('additional_data');
        if ($raw === '') {
            return '&nbsp;';
        }

        $decoded = Mage::helper('core')->jsonDecode($raw);
        if (is_array($decoded) && $decoded) {
            $pretty = Mage::helper('core')->jsonEncode($decoded);
            return '<pre style="margin:0; white-space:pre-wrap; word-break:break-all;">'
                . $this->escapeHtml($pretty)
                . '</pre>';
        }

        return $this->escapeHtml($raw);
    }
}
