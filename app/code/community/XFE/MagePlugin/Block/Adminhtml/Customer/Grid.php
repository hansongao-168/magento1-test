<?php
/**
 * XFE_MagePlugin 客户 Grid（地址权限脱敏）。
 *
 * 扩展 Mage_Adminhtml_Block_Customer_Grid：
 *   - 账号 1/2（完整权限）：正常显示姓名、邮箱、电话等。
 *   - 其他账号：隐藏姓名、邮箱、电话、省份等敏感信息；
 *     并移除 CSV / Excel 导出功能。
 */
class XFE_MagePlugin_Block_Adminhtml_Customer_Grid extends Mage_Adminhtml_Block_Customer_Grid
{
    /**
     * 当前用户是否完整权限。
     *
     * @var bool|null
     */
    protected $_fullAccess = null;

    /**
     * 获取当前用户是否完整权限（缓存）。
     *
     * @return bool
     */
    protected function _isFullAccess()
    {
        if ($this->_fullAccess === null) {
            $this->_fullAccess = Mage::helper('xfe_mageplugin')->isCurrentUserFullAccess();
        }
        return $this->_fullAccess;
    }

    /**
     * 定义显示列。
     *
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareColumns()
    {
        parent::_prepareColumns();

        if ($this->_isFullAccess()) {
            return $this;
        }

        // 非完整权限账号：移除敏感列（姓名、邮箱、电话、省份）
        foreach (array('name', 'email', 'Telephone', 'billing_region') as $columnId) {
            if (isset($this->_columns[$columnId])) {
                unset($this->_columns[$columnId]);
            }
        }

        // 移除导出按钮（CSV / Excel XML）
        $this->_exportTypes = array();

        return $this;
    }
}
