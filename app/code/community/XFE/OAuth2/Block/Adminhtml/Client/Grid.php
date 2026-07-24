<?php
/**
 * Admin Client Grid
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Block_Adminhtml_Client_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('xfeoauth2_client_grid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    /**
     * @return XFE_OAuth2_Block_Adminhtml_Client_Grid
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('xfeoauth2/client')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * @return XFE_OAuth2_Block_Adminhtml_Client_Grid
     */
    protected function _prepareColumns()
    {
        $helper = Mage::helper('xfeoauth2');

        $this->addColumn('client_id', array(
            'header' => $helper->__('Client ID'),
            'index'  => 'client_id',
            'type'   => 'text',
            'width'  => '300px',
        ));

        $this->addColumn('name', array(
            'header' => $helper->__('Name'),
            'index'  => 'name',
            'type'   => 'text',
        ));

        $this->addColumn('grant_types', array(
            'header' => $helper->__('Grant Types'),
            'index'  => 'grant_types',
            'type'   => 'text',
        ));

        $this->addColumn('status', array(
            'header'  => $helper->__('Status'),
            'index'   => 'status',
            'type'    => 'options',
            'options' => array(
                0 => $helper->__('Disabled'),
                1 => $helper->__('Active'),
            ),
            'width'   => '100px',
        ));

        $this->addColumn('created_at', array(
            'header' => $helper->__('Created'),
            'index'  => 'created_at',
            'type'   => 'datetime',
            'width'  => '150px',
        ));

        return parent::_prepareColumns();
    }

    /**
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }

    /**
     * @param XFE_OAuth2_Model_Client $row
     * @return string
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', array('id' => $row->getClientId()));
    }
}
