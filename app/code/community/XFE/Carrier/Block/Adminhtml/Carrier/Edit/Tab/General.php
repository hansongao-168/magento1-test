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
     *
     * 数据源改为纯客户端:所有线路公司由 PHP 端 `_getShippingCompanyList()` 渲染到
     * JS 数组,搜索/匹配全部在浏览器内完成(compatible with Magento 1.9
     * Prototype.js + 不依赖任何后端 action)。
     */
    protected function _getShippingCompanySearchHtml()
    {
        $model = Mage::registry('xfe_carrier_data');
        $currentId = ($model && $model->getId() && $model->getShippingCompanyId())
            ? (int)$model->getShippingCompanyId() : 0;
        $helper = Mage::helper('xfe_carrier');

        // 渲染 JS 数组:所有可选线路公司 [{id, name}, ...]
        $companies = $this->_getShippingCompanyList();
        $companiesJson = json_encode(array_values($companies));

        $html = '<div class="shipping-company-search" style="position:relative;width:280px;">';
        $html .= '<input type="hidden" name="shipping_company_id" id="shipping_company_id" value="' . $currentId . '" />';
        $html .= '<input type="text" id="shipping_company_search" class="input-text" '
            . 'placeholder="' . $helper->__('输入关键词模糊搜索线路公司...') . '" '
            . 'style="width:100%;" autocomplete="off" />';
        $html .= '<div id="shipping_company_dropdown" style="display:none;position:absolute;top:100%;left:0;'
            . 'width:100%;max-height:220px;overflow-y:auto;border:1px solid #adadad;'
            . 'background:#fff;z-index:9999;box-shadow:0 2px 6px rgba(0,0,0,0.15);"></div>';
        $html .= '</div>';

        $html .= '<script type="text/javascript">
        //<![CDATA[
        (function() {
            var searchInput, hiddenInput, dropdown;
            var allCompanies = ' . $companiesJson . ';   // [{id, name}, ...]
            var currentId = ' . $currentId . ';

            function init() {
                searchInput = $("shipping_company_search");
                hiddenInput = $("shipping_company_id");
                dropdown    = $("shipping_company_dropdown");

                // 编辑模式:已经有 shipping_company_id 时,反查名字显示
                if (currentId > 0) {
                    var hit = findById(currentId);
                    if (hit) {
                        searchInput.value = hit.name;
                    } else {
                        // 找不到(可能是历史数据不在列表里),直接显示 ID
                        searchInput.value = "#" + currentId;
                    }
                }

                // 点击输入框 → 立即显示下拉(空关键词 = 显示全部)
                searchInput.observe("click", function(evt) {
                    evt.stop();
                    renderList("");
                });
                searchInput.observe("focus", function() {
                    renderList(searchInput.value.strip());
                });

                // 输入框键入 → 实时模糊搜索
                searchInput.observe("keyup", function(evt) {
                    var val = searchInput.value.strip();
                    // ESC 关闭下拉
                    if (evt.keyCode === Event.KEY_ESC) {
                        dropdown.hide();
                        return;
                    }
                    renderList(val);
                });

                // 点击页面其他位置 → 关闭下拉
                document.observe("click", function(evt) {
                    if (!evt.findElement(".shipping-company-search")) {
                        dropdown.hide();
                    }
                });
            }

            function findById(id) {
                for (var i = 0; i < allCompanies.length; i++) {
                    if (allCompanies[i].id === id) {
                        return allCompanies[i];
                    }
                }
                return null;
            }

            // 模糊匹配:支持 不区分大小写 + 包含匹配,关键词按空格切分多个 AND
            function match(item, keyword) {
                if (!keyword) {
                    return true;
                }
                var name = (item.name || "").toLowerCase();
                var id   = String(item.id);
                var kw   = keyword.toLowerCase();
                // 多关键词(空格分隔):每个都必须命中
                var parts = kw.split(/\s+/);
                var nonempty = [];
                for (var pi = 0; pi < parts.length; pi++) {
                    if (parts[pi].length > 0) {
                        nonempty.push(parts[pi]);
                    }
                }
                for (var i = 0; i < nonempty.length; i++) {
                    var p = nonempty[i];
                    if (name.indexOf(p) === -1 && id.indexOf(p) === -1) {
                        return false;
                    }
                }
                return true;
            }

            function renderList(keyword) {
                dropdown.innerHTML = "";
                var hits = [];
                for (var ai = 0; ai < allCompanies.length; ai++) {
                    if (match(allCompanies[ai], keyword)) {
                        hits.push(allCompanies[ai]);
                    }
                }

                if (hits.length === 0) {
                    dropdown.innerHTML = \'<div style="padding:8px;color:#999;">' . $helper->__('无匹配结果') . '</div>\';
                } else {
                    for (var hi = 0; hi < hits.length; hi++) {
                        // IIFE 锁定 item,避免闭包陷阱导致所有 option 选中最后一项
                        (function(item) {
                            var opt = new Element("div", {"class": "sc-option"});
                            opt.update(item.name + " <span style=\\"color:#999;font-size:11px;\\">#" + item.id + "</span>");
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
                        })(hits[hi]);
                    }
                }
                dropdown.show();
            }

            function selectCompany(id, name) {
                hiddenInput.value = id;
                searchInput.value = name;
                dropdown.hide();
            }

            document.observe("dom:loaded", init);
        })();
        //]]>
        </script>';

        return $html;
    }

    /**
     * 线路公司候选项数据源。
     *
     * 优先级:
     *   1. 硬编码列表(见 $hardcoded)—— 含常用线路公司,占位 ID 用大负数
     *   2. 真实数据:从 xfe_carrier_carrier_account 表读取已存在的
     *      (carrier_id, account_name) 组合,作为"曾经被某承运商用过的线路"
     *
     * 返回结构: [{id: int, name: string}, ...]
     * 注意:同一 id 多次出现时去重,保留 name 最长的一条。
     *
     * @return array
     */
    protected function _getShippingCompanyList()
    {
        $list = array();

        // 1) 硬编码线路公司(占位 ID,大负数避免与真实 ID 冲突)
        $hardcoded = array(
            -1001 => '顺丰国际标快',
            -1002 => '顺丰国际特惠',
            -1003 => 'FedEx-IE(国际经济)',
            -1004 => 'FedEx-IP(国际优先)',
            -1005 => 'DHL-Express',
            -1006 => 'UPS Worldwide Express',
            -1007 => 'EMS 国际',
            -1008 => 'ePacket',
            -1009 => '顺丰国内标快',
            -1010 => '顺丰国内特惠',
        );
        foreach ($hardcoded as $id => $name) {
            $list[$id] = $name;
        }

        // 2) 真实数据:从数据库读取已存在的 carrier_account 行(account_id 视为 id, account_name 视为 name)
        try {
            /** @var XFE_Carrier_Model_Resource_Carrier_Account_Collection $coll */
            $coll = Mage::getResourceModel('xfe_carrier/carrier_account_collection');
            $coll->addFieldToFilter('status', 1);
            foreach ($coll as $account) {
                $id = (int)$account->getId();
                $name = (string)$account->getAccountName();
                if ($id <= 0 || $name === '') {
                    continue;
                }
                // 去重:同一 id 已存在时,保留更长的 name
                if (!isset($list[$id]) || mb_strlen($name, 'UTF-8') > mb_strlen($list[$id], 'UTF-8')) {
                    $list[$id] = $name;
                }
            }
        } catch (Exception $e) {
            // 静默失败:DB 异常时仍可使用硬编码列表
        }

        // 转换成 [{id, name}, ...] 数组
        $result = array();
        foreach ($list as $id => $name) {
            $result[] = array('id' => $id, 'name' => $name);
        }
        // 按 name 排序,展示更友好
        usort($result, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $result;
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
