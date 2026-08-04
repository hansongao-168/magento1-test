<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_General extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    protected function _prepareForm()
    {
        $model = Mage::registry('xfe_carrier_data');
        $helper = Mage::helper('xfe_carrier');

        $form = new Varien_Data_Form();
        $form->setUseContainer(false); // Tab content moved into edit_form; avoid nested <form>
        $this->setForm($form);

        $fieldset = $form->addFieldset('base_fieldset', array(
            'legend' => $helper->__('基本信息'),
        ));

        $fieldset->addField('store_id', 'hidden', array(
            'name'  => 'store',
            'value' => (int)$this->getRequest()->getParam('store', Mage_Core_Model_App::ADMIN_STORE_ID),
        ));

        $fieldset->addField('name', 'text', array(
            'name'     => 'name',
            'label'    => $helper->__('名称'),
            'title'    => $helper->__('名称'),
            'required' => true,
        ));

        $fieldset->addField('code', 'text', array(
            'name'  => 'code',
            'label' => $helper->__('标识代码'),
            'title' => $helper->__('标识代码'),
            'note'  => $helper->__('用于程序调用的唯一标识'),
        ));

        $fieldset->addField('shipping_company_id', 'note', array(
            'name'  => 'shipping_company_id',
            'label' => $helper->__('线路公司'),
            'text'  => $this->_getShippingCompanySearchHtml(),
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

        if ($model && $model->getId()) {
            // Surface per-store translations (set by Controller editAction
            // via setStoreId()) so the General tab edits the current store\'s
            // name / note rather than the admin-scope defaults.
            $values = $model->getData();
            $values['name'] = $model->getStoreName();
            $values['note'] = $model->getStoreNote();
            $form->setValues($values);
        }

        return parent::_prepareForm();
    }

    /**
     * Build the searchable select HTML for shipping_company_id.
     */
    protected function _getShippingCompanySearchHtml()
    {
        $searchUrl = $this->getUrl('*/*/shippingCompany');
        $model = Mage::registry('xfe_carrier_data');
        $currentId = ($model && $model->getId() && $model->getShippingCompanyId())
            ? (int)$model->getShippingCompanyId() : 0;
        $helper = Mage::helper('xfe_carrier');

        $html = '<div class="shipping-company-search" style="position:relative;width:280px;">';
        $html .= '<input type="hidden" name="shipping_company_id" id="shipping_company_id" value="' . $currentId . '" />';
        $html .= '<input type="text" id="shipping_company_search" class="input-text" '
            . 'placeholder="' . $helper->__('输入关键词搜索...') . '" '
            . 'style="width:100%;" autocomplete="off" />';
        $html .= '<div id="shipping_company_dropdown" style="display:none;position:absolute;top:100%;left:0;'
            . 'width:100%;max-height:200px;overflow-y:auto;border:1px solid #adadad;'
            . 'background:#fff;z-index:9999;box-shadow:0 2px 6px rgba(0,0,0,0.15);"></div>';
        $html .= '</div>';

        $html .= '<script type="text/javascript">
        //<![CDATA[
        (function() {
            var searchInput, hiddenInput, dropdown, searchUrl, searchTimer, currentId;

            function init() {
                searchInput = $("shipping_company_search");
                hiddenInput = $("shipping_company_id");
                dropdown    = $("shipping_company_dropdown");
                searchUrl   = "' . $searchUrl . '";
                currentId   = ' . $currentId . ';

                if (currentId > 0) {
                    loadCompanyById(currentId);
                }

                searchInput.observe("focus", function() {
                    doSearch(searchInput.value.strip());
                });
                searchInput.observe("click", function(evt) {
                    evt.stop();
                    doSearch(searchInput.value.strip());
                });

                searchInput.observe("keyup", function(evt) {
                    var val = searchInput.value.strip();
                    if (val.length < 1) {
                        doSearch("");
                        return;
                    }
                    if (searchTimer) clearTimeout(searchTimer);
                    searchTimer = setTimeout(function() { doSearch(val); }, 300);
                });

                document.observe("click", function(evt) {
                    if (!evt.findElement(".shipping-company-search")) {
                        dropdown.hide();
                    }
                });
            }

            function selectCompany(id, name) {
                hiddenInput.value = id;
                searchInput.value = name;
                dropdown.hide();
            }

            function loadCompanyById(id) {
                new Ajax.Request(searchUrl, {
                    parameters: { q: id },
                    onSuccess: function(response) {
                        var data = response.responseText.evalJSON();
                        if (data.length > 0) {
                            searchInput.value = data[0].name;
                            hiddenInput.value = data[0].id;
                        }
                    }
                });
            }

            function doSearch(keyword) {
                new Ajax.Request(searchUrl, {
                    parameters: { q: keyword },
                    onSuccess: function(response) {
                        var data = response.responseText.evalJSON();
                        dropdown.innerHTML = "";
                        if (data.length === 0) {
                            dropdown.innerHTML = \'<div style="padding:8px;color:#999;">' . $helper->__('无匹配结果') . '</div>\';
                        } else {
                            data.each(function(item) {
                                var opt = new Element("div", {"class": "sc-option"}).update(item.name);
                                opt.setStyle({
                                    padding: "8px 10px", cursor: "pointer",
                                    borderBottom: "1px solid #f0f0f0"
                                });
                                opt.observe("mouseover", function() { this.setStyle({background: "#ebf4fb"}); });
                                opt.observe("mouseout",  function() { this.setStyle({background: "#fff"}); });
                                opt.observe("click", function(evt) {
                                    evt.stop();
                                    selectCompany(item.id, item.name);
                                });
                                dropdown.appendChild(opt);
                            });
                        }
                        dropdown.show();
                    }
                });
            }

            document.observe("dom:loaded", init);
        })();
        //]]>
        </script>';

        return $html;
    }

    /**
     * @return string
     */
    public function getTabLabel()
    {
        return Mage::helper('xfe_carrier')->__('基础信息');
    }

    /**
     * @return string
     */
    public function getTabTitle()
    {
        return Mage::helper('xfe_carrier')->__('基础信息');
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
