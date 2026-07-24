<?php
/**
 * Token Truncated Renderer
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Block_Adminhtml_Token_Grid_Renderer_Truncated
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $value = $this->_getValue($row);
        if (strlen($value) > 20) {
            return '<span title="' . $this->escapeHtml($value) . '">'
                . $this->escapeHtml(substr($value, 0, 10) . '...' . substr($value, -7))
                . '</span>';
        }
        return $this->escapeHtml($value);
    }
}
