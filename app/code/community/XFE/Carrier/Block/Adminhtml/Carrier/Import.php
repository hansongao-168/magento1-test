<?php

/**
 * Carrier bulk-import landing page.
 *
 * Renders the upload form (template: xfe_carrier/carrier/import/form.phtml).
 * Controller action: CarrierController::importAction().
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Import extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId   = 'import_id';
        $this->_blockGroup = 'xfe_carrier';
        $this->_controller = 'adminhtml_carrier';
        $this->_mode       = 'import';

        parent::__construct();

        // We do not want the default Save / Delete buttons the container ships
        // with. They are meaningless for an upload-only page; the form has
        // its own "Upload" button rendered from the template.
        $this->_removeButton('save');
        $this->_removeButton('delete');
        $this->_removeButton('reset');
        $this->_removeButton('back');

        $this->_addButton('back', array(
            'label'   => Mage::helper('xfe_carrier')->__('返回列表'),
            'onclick' => 'setLocation(\'' . $this->getUrl('*/carrier/') . '\')',
            'class'   => 'back',
        ));

        $this->_addButton('download_template', array(
            'label'   => Mage::helper('xfe_carrier')->__('下载 CSV 模板'),
            'onclick' => 'setLocation(\'' . $this->getUrl('*/*/downloadTemplate') . '\')',
            'class'   => 'scalable',
        ));

        // Use our custom container template so we control the form HTML
        // (the default Form_Container template would try to render a child
        // form block through getFormHtml() which we don't need).
        $this->setTemplate('xfe_carrier/carrier/import/container.phtml');
    }

    public function getHeaderText()
    {
        return Mage::helper('xfe_carrier')->__('批量导入承运商');
    }

    /**
     * Where the upload form posts to.
     *
     * @return string
     */
    public function getFormActionUrl()
    {
        return $this->getUrl('*/*/importPost');
    }
}