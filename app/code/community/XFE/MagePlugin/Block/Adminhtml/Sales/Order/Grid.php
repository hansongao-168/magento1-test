<?php
/**
 * XFE_MagePlugin 订单 Grid（导出脱敏）。
 *
 * 扩展 Mage_Adminhtml_Block_Sales_Order_Grid：
 *   - 新增寄件人/收件人完整地址列（公司、姓名、详细地址、电话、邮箱、邮编、城市）。
 *   - 账号 1/2（完整权限）：导出完整地址列。
 *   - 其他账号：仅保留邮编 + 城市，隐藏姓名及其他敏感地址列。
 */
class XFE_MagePlugin_Block_Adminhtml_Sales_Order_Grid extends Mage_Adminhtml_Block_Sales_Order_Grid
{
    /**
     * 当前用户是否完整权限。
     *
     * @var bool|null
     */
    protected $_fullAccess = null;

    /**
     * 获取当前用户是否完整权限（缓存）。
     *
     * @return bool
     */
    protected function _isFullAccess()
    {
        if ($this->_fullAccess === null) {
            $this->_fullAccess = Mage::helper('xfe_mageplugin')->isCurrentUserFullAccess();
        }
        return $this->_fullAccess;
    }

    /**
     * 扩展 collection：join 订单地址表，获取完整地址字段。
     *
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareCollection()
    {
        parent::_prepareCollection();
        $collection = $this->getCollection();

        if ($collection) {
            $select = $collection->getSelect();
            $adapter = $collection->getConnection();

            $orderTable   = $collection->getTable('sales/order');
            $addressTable = $collection->getTable('sales/order_address');

            // join sales_flat_order 取 billing/shipping address id
            $select->joinLeft(
                array('og_order' => $orderTable),
                'og_order.entity_id = main_table.entity_id',
                array()
            );

            // join billing address
            $select->joinLeft(
                array('ba' => $addressTable),
                'ba.entity_id = og_order.billing_address_id',
                array(
                    'billing_company'    => 'ba.company',
                    'billing_firstname'  => 'ba.firstname',
                    'billing_lastname'   => 'ba.lastname',
                    'billing_street'     => 'ba.street',
                    'billing_telephone'  => 'ba.telephone',
                    'billing_email'      => 'ba.email',
                    'billing_postcode'   => 'ba.postcode',
                    'billing_city'       => 'ba.city',
                )
            );

            // join shipping address
            $select->joinLeft(
                array('sa' => $addressTable),
                'sa.entity_id = og_order.shipping_address_id',
                array(
                    'shipping_company'   => 'sa.company',
                    'shipping_firstname' => 'sa.firstname',
                    'shipping_lastname'  => 'sa.lastname',
                    'shipping_street'    => 'sa.street',
                    'shipping_telephone' => 'sa.telephone',
                    'shipping_email'     => 'sa.email',
                    'shipping_postcode'  => 'sa.postcode',
                    'shipping_city'      => 'sa.city',
                )
            );
        }

        return $this;
    }

    /**
     * 定义导出/显示列。
     *
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareColumns()
    {
        parent::_prepareColumns();

        $full = $this->_isFullAccess();

        if ($full) {
            // 完整权限账号：移除默认姓名列，替换为完整地址列
            $this->_removeColumn('billing_name');
            $this->_removeColumn('shipping_name');

            $this->addColumn('billing_company', array(
                'header' => Mage::helper('sales')->__('Bill to Company'),
                'index'  => 'billing_company',
            ));
            $this->addColumn('billing_name', array(
                'header' => Mage::helper('sales')->__('Bill to Name'),
                'index'  => 'billing_name',
            ));
            $this->addColumn('billing_street', array(
                'header' => Mage::helper('sales')->__('Bill to Street'),
                'index'  => 'billing_street',
            ));
            $this->addColumn('billing_telephone', array(
                'header' => Mage::helper('sales')->__('Bill to Telephone'),
                'index'  => 'billing_telephone',
            ));
            $this->addColumn('billing_email', array(
                'header' => Mage::helper('sales')->__('Bill to Email'),
                'index'  => 'billing_email',
            ));
            $this->addColumn('billing_postcode', array(
                'header' => Mage::helper('sales')->__('Bill to Postcode'),
                'index'  => 'billing_postcode',
            ));
            $this->addColumn('billing_city', array(
                'header' => Mage::helper('sales')->__('Bill to City'),
                'index'  => 'billing_city',
            ));

            $this->addColumn('shipping_company', array(
                'header' => Mage::helper('sales')->__('Ship to Company'),
                'index'  => 'shipping_company',
            ));
            $this->addColumn('shipping_name', array(
                'header' => Mage::helper('sales')->__('Ship to Name'),
                'index'  => 'shipping_name',
            ));
            $this->addColumn('shipping_street', array(
                'header' => Mage::helper('sales')->__('Ship to Street'),
                'index'  => 'shipping_street',
            ));
            $this->addColumn('shipping_telephone', array(
                'header' => Mage::helper('sales')->__('Ship to Telephone'),
                'index'  => 'shipping_telephone',
            ));
            $this->addColumn('shipping_email', array(
                'header' => Mage::helper('sales')->__('Ship to Email'),
                'index'  => 'shipping_email',
            ));
            $this->addColumn('shipping_postcode', array(
                'header' => Mage::helper('sales')->__('Ship to Postcode'),
                'index'  => 'shipping_postcode',
            ));
            $this->addColumn('shipping_city', array(
                'header' => Mage::helper('sales')->__('Ship to City'),
                'index'  => 'shipping_city',
            ));
        } else {
            // 非完整权限账号：隐藏姓名列，仅保留邮编 + 城市
            $this->_removeColumn('billing_name');
            $this->_removeColumn('shipping_name');

            $this->addColumn('billing_postcode', array(
                'header' => Mage::helper('sales')->__('Bill to Postcode'),
                'index'  => 'billing_postcode',
            ));
            $this->addColumn('billing_city', array(
                'header' => Mage::helper('sales')->__('Bill to City'),
                'index'  => 'billing_city',
            ));
            $this->addColumn('shipping_postcode', array(
                'header' => Mage::helper('sales')->__('Ship to Postcode'),
                'index'  => 'shipping_postcode',
            ));
            $this->addColumn('shipping_city', array(
                'header' => Mage::helper('sales')->__('Ship to City'),
                'index'  => 'shipping_city',
            ));
        }

        return $this;
    }

    /**
     * 从 grid 中移除指定列。
     *
     * @param string $columnId
     * @return void
     */
    protected function _removeColumn($columnId)
    {
        if (isset($this->_columns[$columnId])) {
            unset($this->_columns[$columnId]);
        }
    }
}
