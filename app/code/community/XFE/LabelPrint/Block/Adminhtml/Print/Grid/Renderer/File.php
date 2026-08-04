<?php

class XFE_LabelPrint_Block_Adminhtml_Print_Grid_Renderer_File
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{

    /**
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $column = $this->getColumn();
        $index  = $column ? $column->getIndex() : 'path_file';

        $path = (string)$row->getData($index);

        if ($path === '') {
            return '<span style="color:#a00;">'
                . Mage::helper('xfe_labelprint')->__('(no file)')
                . '</span>';
        }

        $display = basename($path);
        $url     = Mage::helper('adminhtml')->getUrl('*/*/download', array(
            'id'   => (int)$row->getId(),
            'kind' => $index === 'old_path_file' ? 'old' : 'current',
        ));

        return sprintf(
            '<a href="%s" target="_blank" title="%s">%s</a>',
            $this->escapeUrl($url),
            $this->escapeHtml($path),
            $this->escapeHtml($display)
        );
    }

}