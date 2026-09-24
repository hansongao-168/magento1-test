<?php

/**
 * Resource Grid "添加规则" 按钮列 Renderer (ADR 0030)
 *
 * 在 Logo / Account / FtpAccount 三个 Resource Grid 每行右侧输出一个绿色 "添加规则" 按钮，
 * 点击后调用 XFE_CarrierRuleModal.open() 弹出 iframe 载入 editRule 页面，
 * URL 会带上对应的 carrier_id + resource_id，使新建的 rule 自动绑定到该 resource。
 *
 * 为什么不复用 Magento 原生 action column:
 *   - action column 在 >=2 个 action 时会变成 <select> 下拉。
 *   - 用户期望 "点某行就能加"，不想多点一次下拉。
 *   - 自定义 renderer 输出平铺 <button>，与现有 "编辑/删除" 按钮独立。
 *
 * @see docs/architecture/decisions/0030-resource-level-rule-add-modal.md
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Grid_Column_Renderer_AddRule
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        $column      = $this->getColumn();
        $moduleCode  = (string) $column->getData('module_code');
        $resourceKey = (string) $column->getData('resource_field');
        $resourceId  = (int) $row->getData($resourceKey);

        $carrierId = (int) $column->getData('carrier_id');
        if (!$carrierId) {
            $carrier = Mage::registry('xfe_carrier_data');
            if ($carrier && $carrier->getId()) {
                $carrierId = (int) $carrier->getId();
            }
        }

        if (!$carrierId || !$resourceId) {
            return '&nbsp;';
        }

        $helper = Mage::helper('xfe_carrier');
        $title  = $helper->__('+ 添加规则');

        $params = array(
            'carrier_id' => $carrierId,
            $resourceKey  => $resourceId,
        );
        if ($moduleCode !== '') {
            $params['module_code'] = $moduleCode;
        }
        $url = $this->getUrl('*/carrier/editRule', $params);

        $labelTitle = "【" . $moduleCode . "】规则";
        $escUrl  = addslashes($url);
        $escTit  = addslashes($labelTitle);

        $html  = '<button type="button" class="xfe-carrier-rule-row-btn"';
        $html .= ' style="padding:4px 10px;background:#5cb85c;border:1px solid #4cae4c;color:#fff;border-radius:3px;cursor:pointer;font-size:11px;font-weight:600;line-height:1.4;"';
        $html .= ' onclick="XFE_CarrierRuleModal.open(\'' . $escUrl . '\', \'' . $escTit . '\'); return false;">';
        $html .= $title;
        $html .= '</button>';

        return $html;
    }
}
