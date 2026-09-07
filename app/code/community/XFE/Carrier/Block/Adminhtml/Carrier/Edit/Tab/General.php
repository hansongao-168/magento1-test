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

        // 1.0.15+ 自定义属性 strict editor
        $this->_addCustomAttributesFieldset($form, $model);

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
        // Legacy placeholder IDs used -1001..-1010 before 1.0.13. Migrate them
        // to the new positive range (1001..1010) at render time so an
        // unchanged re-save stops writing a negative number back to the DB.
        $rawId = ($model && $model->getId() && $model->getShippingCompanyId())
            ? (int)$model->getShippingCompanyId() : 0;
        $currentId = $this->_migrateLegacyShippingCompanyId($rawId);
        $helper = Mage::helper('xfe_carrier');

        // 渲染 JS 数组:所有可选线路公司 [{id, name}, ...]
        $companies = $this->_getShippingCompanyList();
        $companiesJson = json_encode(array_values($companies));

        // ✕ 按钮 + 下拉 + 隐藏 input:全部放进外层容器
        $html = '<div class="shipping-company-search" style="position:relative;width:280px;">';
        $html .= '<input type="hidden" name="shipping_company_id" id="shipping_company_id" value="' . $currentId . '" />';

        // 搜索框 + ✕ 按钮同行(右浮 ✕)
        $html .= '<div style="position:relative;">';
        $html .= '<input type="text" id="shipping_company_search" class="input-text" '
            . 'placeholder="' . $helper->__('输入名称或 ID 模糊搜索线路公司...') . '" '
            . 'style="width:100%;padding-right:24px;" autocomplete="off" />';
        $html .= '<span id="shipping_company_clear" '
            . 'style="display:' . ($currentId !== 0 ? 'inline' : 'none') . ';'
            . 'position:absolute;right:6px;top:50%;transform:translateY(-50%);'
            . 'cursor:pointer;color:#999;font-size:14px;line-height:1;'
            . 'padding:2px 6px;border-radius:3px;" '
            . 'title="' . $helper->__('清除选择') . '">✕</span>';
        $html .= '</div>';

        $html .= '<div id="shipping_company_dropdown" style="display:none;position:absolute;top:100%;left:0;'
            . 'width:100%;max-height:220px;overflow-y:auto;border:1px solid #adadad;'
            . 'background:#fff;z-index:9999;box-shadow:0 2px 6px rgba(0,0,0,0.15);"></div>';
        $html .= '</div>';

        $html .= '<script type="text/javascript">
        //<![CDATA[
        (function() {
            var searchInput, hiddenInput, dropdown, clearBtn;
            var allCompanies = ' . $companiesJson . ';   // [{id, name}, ...]
            var currentId = ' . $currentId . ';

            function init() {
                searchInput = $("shipping_company_search");
                hiddenInput = $("shipping_company_id");
                dropdown    = $("shipping_company_dropdown");
                clearBtn    = $("shipping_company_clear");

                // 编辑模式:已经有 shipping_company_id 时,反查名字显示
                // 注:PHP 端已把 -1001..-1010 迁移到 1001..1010,所以这里 currentId 不会再是负数
                if (currentId !== 0) {
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

                // ✕ 清除按钮:清空 hidden input 和显示
                clearBtn.observe("click", function(evt) {
                    evt.stop();
                    clearSelection();
                });
                clearBtn.observe("mouseover", function() { this.setStyle({background: "#eee", color: "#c00"}); });
                clearBtn.observe("mouseout",  function() { this.setStyle({background: "transparent", color: "#999"}); });

                // 点击页面其他位置 → 关闭下拉
                document.observe("click", function(evt) {
                    if (!evt.findElement(".shipping-company-search")) {
                        dropdown.hide();
                    }
                });

                // form submit 时同步:确保 hidden input 与显示一致
                // (避免用户输入了非列表文字就保存导致数据看起来"没生效")
                var ef = $("edit_form");
                if (ef) {
                    ef.observe("submit", function() {
                        syncBeforeSubmit();
                    });
                }
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
            // 纯数字查询时优先按 ID 前缀匹配(更直观)
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
                // 纯数字查询:按 ID 前缀精确匹配
                if (nonempty.length === 1 && /^\d+$/.test(nonempty[0])) {
                    return id.indexOf(nonempty[0]) !== -1;
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
                            // ID 左侧、名称右侧,符合"左侧显示 ID"的要求
                            opt.update(
                                "<span style=\\"color:#999;font-size:11px;margin-right:8px;\\">[#"
                                + item.id + "]</span>"
                                + "<span>" + item.name + "</span>"
                            );
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
                currentId = id;
                clearBtn.show();
                dropdown.hide();
            }

            function clearSelection() {
                hiddenInput.value = "";
                searchInput.value = "";
                currentId = 0;
                clearBtn.hide();
                dropdown.hide();
            }

            // 提交前同步:如果当前显示的文字恰好命中一个候选项,但用户没点击下拉,
            // 自动按命中的项设置 hidden input。如果显示文字不匹配任何项,保留原
            // hiddenInput.value(保证幂等,不会覆盖旧数据)。
            function syncBeforeSubmit() {
                // Prototype.js may be unavailable (graceful degradation)
                var raw = (searchInput.value || "");
                var txt = (typeof raw.strip === "function") ? raw.strip() : (String(raw).replace(/^\s+|\s+$/g, ""));
                if (txt === "") {
                    // 文字为空 → 视为清空,DB 列接收 NULL(注意 DB 是 INT UNSIGNED NULL,
                    // 由 Varien_Object 跳过空值不写)
                    hiddenInput.value = "";
                    currentId = 0;
                    return;
                }
                // 尝试在 allCompanies 里精确匹配(按名字)
                var matchItem = null;
                for (var i = 0; i < allCompanies.length; i++) {
                    if (allCompanies[i].name === txt) {
                        matchItem = allCompanies[i];
                        break;
                    }
                }
                if (matchItem) {
                    hiddenInput.value = matchItem.id;
                    currentId = matchItem.id;
                    return;
                }
                // 文字看起来是 "#[id]"(历史数据未命中):直接解析
                // 历史数据允许负数(如 #-1001),这里也兼容
                var m = txt.match(/^#(-?\d+)$/);
                if (m && m[1]) {
                    var parsedId = parseInt(m[1], 10);
                    if (!isNaN(parsedId) && findById(parsedId)) {
                        hiddenInput.value = parsedId;
                        currentId = parsedId;
                        return;
                    }
                }
                // 找不到 → 保留 hiddenInput.value(幂等)
            }

            document.observe("dom:loaded", init);
        })();
        //]]>
        </script>';

        return $html;
    }

    /**
     * Map a legacy negative placeholder ID (-1001..-1010) to its current
     * positive equivalent (1001..1010). Any other ID is returned unchanged.
     *
     * Background: prior to 1.0.13 the picker used -1001..-1010 to namespace
     * built-in shipping companies. The DB column was switched to SIGNED INT
     * to hold them, but a "save as-is" of an existing carrier on the new
     * picker code would persist the old negative number. Mapping at render
     * time (and on submit) means a no-touch re-save writes the new positive
     * ID, so the value naturally self-heals on the next edit.
     *
     * @param int $id
     * @return int
     */
    protected function _migrateLegacyShippingCompanyId($id)
    {
        $id = (int)$id;
        if ($id >= -1010 && $id <= -1001) {
            return abs($id); // -1001 -> 1001, ..., -1010 -> 1010
        }
        return $id;
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
            1001 => '顺丰国际标快',
            1002 => '顺丰国际特惠',
            1003 => 'FedEx-IE(国际经济)',
            1004 => 'FedEx-IP(国际优先)',
            1005 => 'DHL-Express',
            1006 => 'UPS Worldwide Express',
            1007 => 'EMS 国际',
            1008 => 'ePacket',
            1009 => '顺丰国内标快',
            1010 => '顺丰国内特惠',
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

    /**
     * 1.0.15+ 自定义属性 strict editor 注入。
     *
     * @param Varien_Data_Form $form
     * @param XFE_Carrier_Model_Carrier|null $model
     * @return void
     */
    protected function _addCustomAttributesFieldset(Varien_Data_Form $form, $model)
    {
        $helper = Mage::helper('xfe_carrier');
        $defs   = XFE_Carrier_Model_Service_Registry::customAttributeService()
            ->getActiveDefs(XFE_Carrier_Domain_CustomAttribute::ENTITY_TYPE_CARRIER);
        $rawJson = $model ? (string) $model->getCustomFieldsJson() : '';
        $hasDefs = $defs->count() > 0;

        $note = $hasDefs
            ? $helper->__(
                '1.0.15 严格模式:仅显示在"自定义属性"菜单中已登记的字段。标有 * 的为必填,留空将无法保存。'
            )
            : $helper->__(
                '尚未在"自定义属性"菜单登记任何字段。先去登记后再回来填写。'
            );

        $fieldset = $form->addFieldset('custom_attributes_fieldset', array(
            'legend' => $helper->__('自定义属性'),
            'note'   => $note,
        ));

        $template = $hasDefs
            ? 'xfe_carrier/custom_attribute/strict_editor.phtml'
            : 'xfe_carrier/carrier/account/custom_fields.phtml';

        $fieldset->addField('custom_fields', 'note', array(
            'label' => $helper->__('键值对列表'),
            'text'  => $this->getLayout()->createBlock('core/template')
                ->setTemplate($template)
                ->setData('raw_json', $rawJson)
                ->setData('entity_type', XFE_Carrier_Domain_CustomAttribute::ENTITY_TYPE_CARRIER)
                ->setData('defs', $defs)
                ->toHtml(),
        ));
    }
}
