<?php
/**
 * XFE_MagePlugin observer.
 *
 * 注：Mage Grid 的 _prepareMassactionBlock() 在 _prepareMassaction() 之后
 * 立即检查 isAvailable() 决定是否插入 massaction column，该过程不发任何事件，
 * 因此 observer 无法在 isAvailable() 检查前注入 item。
 *
 * Grid 上的 massaction 注入实际由 XFE_MagePlugin_Block_Adminhtml_Permissions_User_Grid
 * 子类覆盖 _prepareMassaction() 完成（通过 <rewrite> 扩展 Mage 原生支持的扩展点）。
 *
 * 本 Observer 文件保留作为扩展点，未来需要补充其他 observer 行为时可在此添加。
 * Mage 核心未做修改。
 */
class XFE_MagePlugin_Model_Observer
{
}