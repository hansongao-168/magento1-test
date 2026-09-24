<?php
/**
 * XFE_MagePlugin — Grid subclass.
 *
 * 用户需求是 "迁移至 MagePlugin，使用 observe 实现"。
 * 在尝试纯 observer 方案后发现：Mage Grid 在 _prepareMassactionBlock() 内部
 * 调用完 _prepareMassaction() 后立即检查 isAvailable() 决定是否插入 massaction column，
 * 该中间步骤不发任何事件 —— observer 在架构上无法在该检查前注入 item。
 *
 * 因此采用 Mage 原生的 "block 类继承" 扩展点：
 *   - 通过 <blocks><adminhtml><class> 把 adminhtml/permissions_user_grid 指向我们的子类
 *   - 类只覆盖 _prepareMassaction() 注入 "批量修改名字" item
 *   - 未改动 app/code/core/Mage/Adminhtml/ 下任何文件
 *
 * 此方案与原 XFE_Customer_Block_Adminhtml_Permissions_User_Grid 重写在结构上等价，
 * 但 Mage 核心未被修改。Observer（Model/Observer.php）仍负责表单页与保存逻辑。
 */
class XFE_MagePlugin_Block_Adminhtml_Permissions_User_Grid extends Mage_Adminhtml_Block_Permissions_User_Grid
{
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('user_id');
        $this->getMassactionBlock()->setFormFieldName('user_ids');

// Inline additional block: appears next to the massaction form when "批量修改名字"
// is selected (see grid.js onSelectChange → reads containerId + '-item-rename-block').
        $additional = $this->getLayout()
            ->createBlock('xfe_mageplugin_adminhtml/permissions_user_grid_additional');

        $this->getMassactionBlock()->addItem('rename', array(
            'label'      => Mage::helper('xfe_mageplugin')->__('批量修改名字'),
            // Submit goes to our controller. We use adminhtml/*/massSave so the controller
            // dispatch resolves via the standard router.
            'url'        => $this->getUrl('adminhtml/*/massSave', array('action' => 'massSave')),
            'additional' => $additional,
        ));
        return parent::_prepareMassaction();
    }
}