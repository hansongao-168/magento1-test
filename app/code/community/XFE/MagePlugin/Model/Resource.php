<?php
/**
 * XFE_MagePlugin resource setup base.
 */
class XFE_MagePlugin_Model_Resource extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * 资源模型基类：子类需覆盖 _construct 定义主表。
     */
    protected function _construct()
    {
        // 子类实现
    }
}
