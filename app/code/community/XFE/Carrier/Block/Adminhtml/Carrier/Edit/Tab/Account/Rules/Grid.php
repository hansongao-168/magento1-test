<?php

/**
 * Carrier Edit Tab Account Rules Grid
 *
 * Variant of XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Rules_Grid
 * filtered by the currently-edited account_id (taken from the
 * xfe_carrier_account_data registry).
 *
 * Renders a standard admin grid widget with the carrier-level columns
 * so the Account Edit page shows rules in the same look-and-feel as
 * the carrier-level Rules tab.
 *
 * The grid block's _toHtml() prepends the [+ 娣诲姞瑙勫垯] button. The
 * button sits ABOVE the grid in a simple `<p>` toolbar (not a
 * `<div class="content-header">` - that class is for page-level
 * headers and would add unwanted borders/padding inside a fieldset).
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Edit_Tab_Account_Rules_Grid
    extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('carrier_account_rule_grid');
        $this->setDefaultSort('priority');
        $this->setDefaultDir('DESC');
        $this->setUseAjax(false);
        $this->setSaveParametersInSession(false);
    }

    /**
     * Build collection: rules whose account_id matches the registry
     * account. Falls back to an empty collection when there is no
     * account loaded yet (newly created account).
     *
     * @return $this
     */
    protected function _prepareCollection()
    {
        $account = Mage::registry('xfe_carrier_account_data');
        if ($account && $account->getId()) {
            $collection = Mage::getModel('xfe_carrier/carrier_rule')->getCollection()
                ->addFieldToFilter('account_id', (int)$account->getId())
                ->setOrder('priority', 'DESC')
                ->setOrder('updated_at', 'DESC')
                ->setOrder('sort_order', 'ASC');
        } else {
            $collection = new Varien_Data_Collection();
        }
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Grid columns - same shape as the carrier-level Rules tab.
     *
     * @return $this
     */
    protected function _prepareColumns()
    {
        $helper = Mage::helper('xfe_carrier');

        $this->addColumn('module_code', array(
            'header'  => $helper->__('鎵€灞炴ā鍧?),
            'index'   => 'module_code',
            'width'   => '100px',
            'type'    => 'options',
            'options' => $helper->getCarrierModuleSelectOptions(),
        ));

        $this->addColumn('name', array(
            'header' => $helper->__('瑙勫垯鍚嶇О'),
            'index'  => 'name',
        ));

        $this->addColumn('description', array(
            'header' => $helper->__('鎻忚堪'),
            'index'  => 'description',
        ));

        $this->addColumn('status', array(
            'header'   => $helper->__('鐘舵€?),
            'index'    => 'status',
            'type'     => 'options',
            'width'    => '80px',
            'options'  => Mage::getSingleton('xfe_carrier/source_status')->toArray(),
            'renderer' => 'xfe_carrier/adminhtml_carrier_edit_tab_rules_grid_renderer_status',
        ));

        $this->addColumn('priority', array(
            'header' => $helper->__('\xe4\xbc\x98\xe5\x85\x88\xe7\xba\xa7'),
            'index'  => 'priority',
            'type'   => 'number',
            'width'  => '60px',
        ));

        $this->addColumn('sort_order', array(
            'header' => $helper->__('鎺掑簭'),
            'index'  => 'sort_order',
            'type'   => 'number',
            'width'  => '60px',
        ));

        $this->addColumn('action', array(
            'header'  => $helper->__('鎿嶄綔'),
            'width'   => '140px',
            'type'    => 'action',
            'getter'  => 'getId',
            'actions' => array(
                array(
                    'caption' => $helper->__('缂栬緫'),
                    'url'     => array(
                        'base'   => '*/carrier/editRule',
                        'params' => array(
                            'carrier_id' => $this->_getCarrierId(),
                            'account_id' => $this->_getAccountId(),
                        ),
                    ),
                    'field'   => 'rule_id',
                ),
                array(
                    'caption' => $helper->__('鍒犻櫎'),
                    'url'     => array('base' => '*/carrier/deleteRule', 'params' => array()),
                    'field'   => 'rule_id',
                    'confirm' => $helper->__('纭畾瑕佸垹闄よ瑙勫垯鍚楋紵'),
                ),
            ),
            'filter'   => false,
            'sortable' => false,
        ));

        return parent::_prepareColumns();
    }

    /**
     * Carrier id from the registry account.
     *
     * @return int
     */
    protected function _getCarrierId()
    {
        $account = Mage::registry('xfe_carrier_account_data');
        return $account && $account->getId() ? (int)$account->getCarrierId() : 0;
    }

    /**
     * Account id from the registry account.
     *
     * @return int
     */
    protected function _getAccountId()
    {
        $account = Mage::registry('xfe_carrier_account_data');
        return $account && $account->getId() ? (int)$account->getId() : 0;
    }

    /**
     * @return string
     */
    public function getEmptyText()
    {
        return Mage::helper('xfe_carrier')->__('鏆傛棤瑙勫垯锛岃鐐瑰嚮涓婃柟鎸夐挳娣诲姞銆?);
    }

    /**
     * Row URL disabled: action column handles navigation.
     *
     * @param Varien_Object $row
     * @return false
     */
    public function getRowUrl($row)
    {
        return false;
    }

    /**
     * No mass actions on the account-level rules grid.
     *
     * @return $this
     */
    protected function _prepareMassaction()
    {
        return $this;
    }

    /**
     * Prepend the [+ 娣诲姞瑙勫垯] button above the grid.
     *
     * Wrapped in a plain `<p>` toolbar (no content-header class) so the
     * button renders cleanly inside the 瑙勫垯璁剧疆 entry-edit block.
     *
     * @return string
     */
    protected function _toHtml()
    {
        $helper    = Mage::helper('xfe_carrier');
        $carrierId = $this->_getCarrierId();
        $accountId = $this->_getAccountId();
        $addUrl    = $this->getUrl('*/carrier/editRule',
            array('carrier_id' => $carrierId, 'account_id' => $accountId));

        $html  = '<div id="account-rules-list-wrapper">';
        $html .= '<p class="form-buttons" style="margin:0 0 8px 0;">';
        $html .= '<button type="button" class="scalable add" onclick="setLocation(\'' . $addUrl . '\')">';
        $html .= '<span><span><span>' . $helper->__('+ 娣诲姞瑙勫垯') . '</span></span></span>';
        $html .= '</button>';
        $html .= '</p>';
        $html .= parent::_toHtml();
        $html .= '</div>';
        return $html;
    }
}