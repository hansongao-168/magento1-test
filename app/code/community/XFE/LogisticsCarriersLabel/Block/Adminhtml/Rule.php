<?php
/**
 * Rule Grid Container
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_Block_Adminhtml_Rule extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_rule';
        $this->_blockGroup = 'xcarrierslabel';
        $this->_headerText = Mage::helper('xcarrierslabel')->__('Logistic Carriers Label Rules');
        $this->_addButtonLabel = Mage::helper('xcarrierslabel')->__('Add Rule');
        parent::__construct();
    }
}
