<?php
/**
 * Admin Token Grid
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Block_Adminhtml_Token_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('xfeoauth2_token_grid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    /**
     * @return XFE_OAuth2_Block_Adminhtml_Token_Grid
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('xfeoauth2/accessToken')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * @return XFE_OAuth2_Block_Adminhtml_Token_Grid
     */
    protected function _prepareColumns()
    {
        $helper = Mage::helper('xfeoauth2');

        $this->addColumn('access_token', array(
            'header'   => $helper->__('Access Token'),
            'index'    => 'access_token',
            'type'     => 'text',
            'width'    => '200px',
            'renderer' => 'xfeoauth2/adminhtml_token_grid_renderer_truncated',
        ));

        $this->addColumn('client_id', array(
            'header' => $helper->__('Client ID'),
            'index'  => 'client_id',
            'type'   => 'text',
            'width'  => '200px',
        ));

        $this->addColumn('user_id', array(
            'header' => $helper->__('User ID'),
            'index'  => 'user_id',
            'type'   => 'text',
            'width'  => '80px',
        ));

        $this->addColumn('user_type', array(
            'header' => $helper->__('User Type'),
            'index'  => 'user_type',
            'type'   => 'text',
            'width'  => '100px',
        ));

        $this->addColumn('scope', array(
            'header' => $helper->__('Scope'),
            'index'  => 'scope',
            'type'   => 'text',
            'width'  => '150px',
        ));

        $this->addColumn('expires', array(
            'header' => $helper->__('Expires'),
            'index'  => 'expires',
            'type'   => 'number',
            'width'  => '120px',
            'frame_callback' => array($this, 'expiresRenderer'),
        ));

        $this->addColumn('created_at', array(
            'header' => $helper->__('Created'),
            'index'  => 'created_at',
            'type'   => 'datetime',
            'width'  => '150px',
        ));

        $this->addColumn('action', array(
            'header'   => $helper->__('Action'),
            'width'    => '80px',
            'type'     => 'action',
            'getter'   => 'getAccessToken',
            'actions'  => array(
                array(
                    'caption' => $helper->__('Revoke'),
                    'url'     => array('base' => '*/*/revoke'),
                    'field'   => 'token',
                    'confirm' => $helper->__('Are you sure you want to revoke this token?'),
                ),
            ),
            'filter'   => false,
            'sortable' => false,
        ));

        return parent::_prepareColumns();
    }

    /**
     * @param string $value
     * @return string
     */
    public function expiresRenderer($value)
    {
        if (!$value) {
            return '';
        }
        $expired = ($value < time());
        $class = $expired ? 'grid-severity-critical' : 'grid-severity-notice';
        return '<span class="' . $class . '"><span>' . Mage::helper('core')->formatDate(
            date('Y-m-d H:i:s', $value), 'medium', true
        ) . '</span></span>';
    }

    /**
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }

    /**
     * @param Varien_Object $row
     * @return string|null
     */
    public function getRowUrl($row)
    {
        return null;
    }
}
