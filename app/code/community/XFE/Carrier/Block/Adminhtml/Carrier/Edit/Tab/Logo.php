<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Logo extends Mage_Adminhtml_Block_Widget
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Render tab content: grid, JS
     *
     * @return string
     */
    protected function _toHtml()
    {
        $model = Mage::registry('xfe_carrier_data');
        $helper = Mage::helper('xfe_carrier');

        $html = '';

        if ($model && $model->getId()) {
            // ========== 1. Logo Grid ==========
            $html .= $this->getLayout()->createBlock(
                'xfe_carrier/adminhtml_carrier_edit_tab_logo_grid'
            )->toHtml();

            // ========== 2. JavaScript ==========
            $html .= $this->_renderLogoJs($model, $helper);
        } else {
            $html .= '<div class="entry-edit">';
            $html .= '<div class="entry-edit-head"><h4 class="icon-head head-edit-form fieldset-legend">'
                   . $helper->__('提示') . '</h4></div>';
            $html .= '<div class="fieldset"><p style="color:#999;">'
                   . $helper->__('保存承运商基本信息后即可上传Logo。') . '</p></div>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Render JavaScript for logo delete
     *
     * @param XFE_Carrier_Model_Carrier $model
     * @param Mage_Core_Helper_Abstract $helper
     * @return string
     */
    protected function _renderLogoJs($model, $helper)
    {
        $deleteUrl = $this->getUrl('adminhtml/carrier/deleteLogo');
        $carrierId = (int)$model->getId();

        $confirmMsg = $helper->jsQuoteEscape($helper->__('确定要删除该Logo吗？'));
        $deleteFail = $helper->jsQuoteEscape($helper->__('删除失败'));
        $reqFail    = $helper->jsQuoteEscape($helper->__('请求失败，请稍后重试。'));

        $js = <<<JS
<script type="text/javascript">
//<![CDATA[
function deleteLogo(logoId) {
    if (!confirm("{$confirmMsg}")) return;
    new Ajax.Request("{$deleteUrl}", {
        method: "post",
        parameters: { carrier_id: {$carrierId}, logo_id: logoId },
        onSuccess: function(transport) {
            var resp = transport.responseText.evalJSON();
            if (resp.success) {
                var grid = $("carrier_logo_grid");
                if (grid && grid.reload) {
                    grid.reload();
                }
            } else {
                alert(resp.message || "{$deleteFail}");
            }
        },
        onFailure: function() {
            alert("{$reqFail}");
        }
    });
}
//]]>
</script>
JS;
        return $js;
    }

    /**
     * @return string
     */
    public function getTabLabel()
    {
        return Mage::helper('xfe_carrier')->__('Logo');
    }

    /**
     * @return string
     */
    public function getTabTitle()
    {
        return Mage::helper('xfe_carrier')->__('Logo');
    }

    /**
     * @return bool
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isHidden()
    {
        return false;
    }
}
