<?php

/**
 * Carrier FTP账号编辑 - Form
 *
 * 平行于 XFE_Carrier_Block_Adminhtml_Carrier_Account_Edit_Form,
 * 字段替换为 FTP 连接信息(host/port/protocol/username/password/
 * remote_path/mode/encoding),并在已存在 ftp_account 时展示
 * 「规则设置」区块(渲染 xfe_carrier_ftpaccount_rules_grid)。
 */
class XFE_Carrier_Block_Adminhtml_Carrier_FtpAccount_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $helper = Mage::helper('xfe_carrier');
        $ftp    = Mage::registry('xfe_carrier_ftp_account_data');

        $form = new Varien_Data_Form(array(
            'id'     => 'edit_form',
            'action' => $this->getUrl('*/*/saveFtpAccount'),
            'method' => 'post',
        ));

        $form->setUseContainer(true);

        $fieldset = $form->addFieldset('base_fieldset', array(
            'legend' => $helper->__('FTP账号信息'),
        ));

        if ($ftp && $ftp->getId()) {
            $fieldset->addField('ftp_account_id', 'hidden', array(
                'name' => 'ftp_account_id',
            ));
        }

        $fieldset->addField('carrier_id', 'hidden', array(
            'name' => 'carrier_id',
        ));

        $fieldset->addField('account_name', 'text', array(
            'name'     => 'account_name',
            'label'    => $helper->__('账号名称'),
            'title'    => $helper->__('账号名称'),
            'required' => true,
        ));

        $fieldset->addField('account_no', 'text', array(
            'name'  => 'account_no',
            'label' => $helper->__('账号编号'),
            'title' => $helper->__('账号编号'),
        ));

        $fieldset->addField('protocol', 'select', array(
            'name'   => 'protocol',
            'label'  => $helper->__('协议'),
            'title'  => $helper->__('协议'),
            'values' => array(
                'ftp'  => array('value' => 'ftp',  'label' => $helper->__('FTP')),
                'sftp' => array('value' => 'sftp', 'label' => $helper->__('SFTP')),
                'ftps' => array('value' => 'ftps', 'label' => $helper->__('FTPS')),
            ),
            'note'   => $helper->__('支持 FTP / SFTP / FTPS'),
        ));

        $fieldset->addField('host', 'text', array(
            'name'     => 'host',
            'label'    => $helper->__('主机'),
            'title'    => $helper->__('主机'),
            'required' => true,
        ));

        $fieldset->addField('port', 'text', array(
            'name'  => 'port',
            'label' => $helper->__('端口'),
            'title' => $helper->__('端口'),
            'class' => 'validate-number',
            'note'  => $helper->__('FTP/FTPS 默认 21,SFTP 默认 22'),
        ));

        $fieldset->addField('username', 'text', array(
            'name'  => 'username',
            'label' => $helper->__('用户名'),
            'title' => $helper->__('用户名'),
        ));

        $fieldset->addField('password', 'text', array(
            'name'  => 'password',
            'label' => $helper->__('密码'),
            'title' => $helper->__('密码'),
            'note'  => $helper->__('建议使用专门的 FTP 账号密码,不要与登录密码相同'),
        ));

        $fieldset->addField('remote_path', 'text', array(
            'name'  => 'remote_path',
            'label' => $helper->__('远程路径'),
            'title' => $helper->__('远程路径'),
            'note'  => $helper->__('如 /upload 或 ./inbox,根目录可填 /'),
        ));

        $fieldset->addField('mode', 'select', array(
            'name'   => 'mode',
            'label'  => $helper->__('传输模式'),
            'title'  => $helper->__('传输模式'),
            'values' => array(
                'passive' => array('value' => 'passive', 'label' => $helper->__('被动模式')),
                'active'  => array('value' => 'active',  'label' => $helper->__('主动模式')),
            ),
            'note'   => $helper->__('仅适用于 FTP/FTPS,SFTP 可忽略'),
        ));

        $fieldset->addField('encoding', 'text', array(
            'name'  => 'encoding',
            'label' => $helper->__('字符编码'),
            'title' => $helper->__('字符编码'),
            'note'  => $helper->__('默认 UTF-8'),
        ));

        $fieldset->addField('status', 'select', array(
            'name'   => 'status',
            'label'  => $helper->__('状态'),
            'title'  => $helper->__('状态'),
            'values' => Mage::getSingleton('xfe_carrier/source_status')->toOptionArray(),
        ));

        $fieldset->addField('sort_order', 'text', array(
            'name'  => 'sort_order',
            'label' => $helper->__('排序'),
            'title' => $helper->__('排序'),
            'class' => 'validate-number',
            'note'  => $helper->__('越小越靠前'),
        ));

        $fieldset->addField('note', 'textarea', array(
            'name'  => 'note',
            'label' => $helper->__('备注'),
            'title' => $helper->__('备注'),
        ));

        if ($ftp) {
            $values = $ftp->getData();
            // 没有显式传值时给默认,避免初次编辑显示空
            if (empty($values['protocol'])) {
                $values['protocol'] = 'ftp';
            }
            if (empty($values['port'])) {
                $values['port'] = 21;
            }
            if (empty($values['mode'])) {
                $values['mode'] = 'passive';
            }
            if (empty($values['encoding'])) {
                $values['encoding'] = 'UTF-8';
            }
            $form->setValues($values);
        }

        // ============================================================
        // 自定义字段(键值对编辑器,1.0.14+)
        // ============================================================
        $this->_addCustomFieldsFieldset($form, $ftp);

        $this->setForm($form);
        return parent::_prepareForm();
    }

    /**
     * 与主账号 Edit/Form 共享同一份 phtml 模板(键值对编辑器)。
     * 1.0.15 严格模式: 用 strict_editor.phtml + 已登记属性集合。
     *
     * @param Varien_Data_Form $form
     * @param mixed $ftp
     * @return void
     */
    protected function _addCustomFieldsFieldset(Varien_Data_Form $form, $ftp)
    {
        $helper     = Mage::helper('xfe_carrier');
        $entityType = 'ftp_account';
        $defs       = XFE_Carrier_Model_Service_Registry::customAttributeService()
            ->getActiveDefs($entityType);
        $rawJson    = $ftp ? (string) $ftp->getCustomFieldsJson() : '';
        $hasDefs    = $defs->count() > 0;

        $note = $hasDefs
            ? $helper->__(
                '1.0.15 严格模式:仅显示在"自定义属性"菜单中已登记的字段。标有 * 的为必填,留空将无法保存。'
            )
            : $helper->__(
                '尚未在"自定义属性"菜单登记任何字段。先去登记后再回来填写。'
            );

        $fieldset = $form->addFieldset('custom_fields_fieldset', array(
            'legend' => $helper->__('自定义字段'),
            'note'   => $note,
        ));

        if ($hasDefs) {
            $template = 'xfe_carrier/custom_attribute/strict_editor.phtml';
        } else {
            $template = 'xfe_carrier/carrier/account/custom_fields.phtml';
        }

        $fieldset->addField('custom_fields', 'note', array(
            'label' => $helper->__('键值对列表'),
            'text'  => $this->getLayout()->createBlock('core/template')
                ->setTemplate($template)
                ->setData('raw_json', $rawJson)
                ->setData('entity_type', $entityType)
                ->setData('defs', $defs)
                ->toHtml(),
        ));
    }

    protected function _toHtml()
    {
        $html   = parent::_toHtml();
        $helper = Mage::helper('xfe_carrier');
        $ftp    = Mage::registry('xfe_carrier_ftp_account_data');

        $html .= '<div class="entry-edit xfe-carrier-ftp-account-rules-section" style="margin-top:20px;">';
        $html .= '<div class="entry-edit-head">';
        $html .= '<h4 class="icon-head head-edit-form fieldset-legend">'
              . $helper->__('规则设置') . '</h4>';
        $html .= '</div>';
        $html .= '<div class="fieldset">';
        $html .= '<p class="note" style="margin:0 0 10px 0;">'
              . $helper->__('绑定到本 FTP账号的规则将作为该 FTP账号的优选匹配规则;条件请在规则编辑页(点击列表中的 [编辑] 链接)中维护。')
              . '</p>';
        $html .= $this->_renderFtpAccountRulesSection($ftp);
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    protected function _renderFtpAccountRulesSection($ftp)
    {
        $helper = Mage::helper('xfe_carrier');

        if (!$ftp || !$ftp->getId()) {
            return '<p style="color:#999;font-style:italic;padding:6px 0;">'
                . $helper->__('请先保存 FTP账号,然后再为它绑定规则。')
                . '</p>';
        }

        return $this->getLayout()->createBlock(
            'xfe_carrier/adminhtml_carrier_edit_tab_ftpaccount_rules_grid'
        )->toHtml();
    }
}