<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'id';
        $this->_controller = 'adminhtml_carrier';
        $this->_blockGroup = 'xfe_carrier';
        $this->_mode = 'edit';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('xfe_carrier')->__('保存'));
        $this->_updateButton('delete', 'label', Mage::helper('xfe_carrier')->__('删除'));

        $this->_addButton('save_and_continue', array(
            'label'   => Mage::helper('xfe_carrier')->__('保存并继续编辑'),
            'onclick' => 'saveAndContinueEdit()',
            'class'   => 'save',
        ), -100);

        $this->_formScripts[] = "
            function saveAndContinueEdit() {
                syncAllHiddenFields();
                editForm.submit(\$('edit_form').action + 'back/edit/');
            }

            function syncAllHiddenFields() {
                if (typeof updateRulesHiddenField === 'function') updateRulesHiddenField();
                if (typeof updateAccountsHiddenField === 'function') updateAccountsHiddenField();
            }

            // Auto-sync account/rule data before any form submit
            document.observe('dom:loaded', function() {
                var ef = \$('edit_form');
                if (ef) {
                    Event.observe(ef, 'submit', function() {
                        syncAllHiddenFields();
                    });
                }
            });
        ";

        // Magento standard left-side tabs template (tabs rendered in left column via layout XML)
        $this->setTemplate('xfe_carrier/carrier/edit/container.phtml');
    }

    /**
     * Get header text
     *
     * @return string
     */
    public function getHeaderText()
    {
        $model = Mage::registry('xfe_carrier_data');
        if ($model && $model->getId()) {
            return Mage::helper('xfe_carrier')->__("编辑承运商 '%s'", $this->escapeHtml($model->getName()));
        }
        return Mage::helper('xfe_carrier')->__('新增承运商');
    }

    public function getFormActionUrl()
    {
        return $this->getUrl('*/*/save');
    }
}
