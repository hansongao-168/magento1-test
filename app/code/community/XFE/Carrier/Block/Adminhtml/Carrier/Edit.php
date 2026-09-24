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
                detachGridControls();
                syncAllHiddenFields();
                editForm.submit(\$('edit_form').action + 'back/edit/');
            }

            function syncAllHiddenFields() {
                if (typeof updateRulesHiddenField === 'function') updateRulesHiddenField();
                if (typeof updateAccountsHiddenField === 'function') updateAccountsHiddenField();
            }

            // Grid search / pagination inputs (class no-changes) live inside
            // the account / ftp / rule tabs. varienTabs moves those tab bodies
            // INTO the main edit_form, so these UI-only fields (name, page,
            // account_name, sort_order[from], ...) collide with the real form
            // fields and get POSTed twice. Neutralize them right before submit
            // so they never reach the server, then restore after.
            function detachGridControls() {
                if (typeof \$('edit_form') !== 'undefined' && \$('edit_form')) {
                    // Exclude grid search / pagination UI (no-changes filters,
                    // per-grid page inputs, hidden grid state) so they never
                    // collide with the real form fields on POST.
                    \$\$('#edit_form input.no-changes, #edit_form select.no-changes, #edit_form input.page, #edit_form input[name=page]').each(function(el) {
                        if (!el.getAttribute('data-xfe-name')) {
                            el.setAttribute('data-xfe-name', el.name || '');
                        }
                        el.name = '';
                    });
                }
            }

            function restoreGridControls() {
                if (typeof \$('edit_form') !== 'undefined' && \$('edit_form')) {
                    \$\$('#edit_form input.no-changes, #edit_form select.no-changes, #edit_form input.page, #edit_form input[name=page]').each(function(el) {
                        var n = el.getAttribute('data-xfe-name');
                        if (n !== null) {
                            el.name = n;
                            el.removeAttribute('data-xfe-name');
                        }
                    });
                }
            }

            // Auto-sync account/rule data before any form submit
            document.observe('dom:loaded', function() {
                var ef = \$('edit_form');
                if (ef) {
                    Event.observe(ef, 'submit', function() {
                        detachGridControls();
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
        // Include the entity id in the URL so controller saveAction() receives it
        // (the form lacks the default Magento hidden id input because we override
        // the container template to host the tabs).
        $params = array();
        $id = $this->getRequest()->getParam('id');
        if ($id) {
            $params['id'] = (int)$id;
        }
        return $this->getUrl('*/*/save', $params);
    }
}
