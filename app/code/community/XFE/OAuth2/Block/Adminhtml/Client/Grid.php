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

        $this->addColumn('reveal', array(
            'header'   => $helper->__('Secret'),
            'index'    => 'client_id',
            'renderer' => 'xfeoauth2/adminhtml_client_grid_renderer_secret',
            'filter'   => false,
            'sortable' => false,
            'width'    => '120px',
        ));

        // Explicit Edit button. We deliberately keep this separate from the
        // "Show Secret" button so that prompting for the secret does not
        // accidentally navigate the user away from the list, and the user
        // has a clear, single-purpose affordance for entering the edit page.
        // The row-level click is disabled in getRowUrl() below so cells
        // without their own buttons (e.g. name, client_id) act as plain text
        // rather than disguised navigation.
        $this->addColumn('actions', array(
            'header'   => $helper->__('Actions'),
            'type'     => 'action',
            'index'    => 'client_id',
            'getter'   => 'getClientId',
            'filter'   => false,
            'sortable' => false,
            'width'    => '70px',
            'actions'  => array(
                array(
                    'caption' => $helper->__('Edit'),
                    'url'     => array('base' => '*/*/edit'),
                    'field'   => 'id',
                ),
            ),
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
     * Disabled: rows used to be a single big hit-target for the edit page,
     * but that made per-row buttons (Show Secret / Edit) awkward to use -
     * clicking through to edit should now be opt-in via the explicit Edit
     * action column. Returning an empty string skips the row-level
     * onclick callback that Mage_Adminhtml_Block_Widget_Grid would
     * otherwise emit based on a non-empty URL.
     *
     * @param XFE_OAuth2_Model_Client $row
     * @return string
     */
    public function getRowUrl($row)
    {
        return '';
    }
}
