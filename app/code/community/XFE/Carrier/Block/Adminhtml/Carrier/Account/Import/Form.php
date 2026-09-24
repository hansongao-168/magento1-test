<?php

/**
 * 账号上传表单，渲染在账号导入容器内部。
 *
 * 输出自包含的最小化 <form>（文件输入 + 隐藏 form_key + 提交按钮）。
 * 提交到 CarrierController::accountImportPostAction()。
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Account_Import_Form extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('xfe_carrier/carrier/account/import/form.phtml');
    }

    /**
     * @return string
     */
    public function getPostUrl()
    {
        return $this->getUrl('*/carrier/accountImportPost');
    }
}
