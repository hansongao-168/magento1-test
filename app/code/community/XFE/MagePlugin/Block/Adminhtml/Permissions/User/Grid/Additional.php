<?php
/**
 * XFE_MagePlugin — Inline additional block for the "批量修改名字" massaction item.
 *
 * 当用户在 Users Grid 的 massaction 下拉中选择 "批量修改名字" 时，Mage 的 grid.js
 * 会自动读取该 item 的 additional_action_block 块并把它注入到 massaction 表单右侧
 * 的 "form-additional" span 中显示。
 *
 * 这里渲染一个 textarea（每行 "原用户名 新用户名"）+ 当前用户名参考。
 * 继承 Widget 而非 Template，避免 Template._toHtml 触发的 adminhtml_block_html_before
 * 事件递归以及 Template 必须通过 toHtml() 渲染的问题。直接 _toHtml() 返回最终 HTML。
 */
class XFE_MagePlugin_Block_Adminhtml_Permissions_User_Grid_Additional extends Mage_Adminhtml_Block_Widget
{
    /**
     * Current selected user names for the "查看当前登录名" reference area.
     * Read from the URL user_ids param.
     *
     * @return array
     */
    public function getCurrentUsernames()
    {
        $ids = Mage::app()->getRequest()->getParam('user_ids', '');
        if (!is_array($ids)) {
            $ids = array_filter(array_map('intval', explode(',', (string)$ids)));
        }
        if (empty($ids)) {
            return array();
        }
        $collection = Mage::getResourceModel('admin/user_collection')
            ->addFieldToFilter('user_id', array('in' => $ids));
        $usernames = array();
        foreach ($collection as $user) {
            $usernames[] = $user->getUsername();
        }
        return $usernames;
    }

    /**
     * Render the inline textarea + reference block directly.
     * Override _toHtml() so child block rendering picks up our HTML
     * without needing a separate template file.
     *
     * @return string
     */
    protected function _toHtml()
    {
        $helper = Mage::helper('xfe_mageplugin');
        $currentNames = $this->getCurrentUsernames();

        $html = '<div class="mage-plugin-massaction-additional" style="margin-top:8px;">';
        $html .= '<label for="names" style="font-weight:bold;display:block;margin-bottom:4px;">'
              . $helper->__('新的登录名') . '</label>';
        $html .= '<textarea name="names" id="names" '
              . 'class="input-textarea required-entry" '
              . 'style="width:100%;height:120px;" '
              . 'placeholder="' . $helper->__('每行：原用户名 新用户名，空格或 Tab 分隔') . '"></textarea>';
        $html .= '<p class="note" style="margin:4px 0;color:#666;font-size:11px;">'
              . $helper->__('每行格式：原用户名 新用户名（空格或 Tab 分隔）。仅处理勾选且用户存在的行。') . '</p>';

        if (!empty($currentNames)) {
            $html .= '<label for="current_names" style="font-weight:bold;display:block;margin:8px 0 4px;">'
                  . $helper->__('当前登录名（仅供参考）') . '</label>';
            $html .= '<textarea id="current_names" readonly disabled '
                  . 'style="height:80px;background:#eee;color:#555;">'
                  . htmlspecialchars(implode("\n", $currentNames), ENT_QUOTES, 'UTF-8') . '</textarea>';
        }

        $html .= '</div>';
        return $html;
    }
}