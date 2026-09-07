<?php

/**
 * 自定义属性管理 Controller(1.0.15+)
 *
 * 路由: adminhtml/carrier_customAttribute/*
 * URL : /admin/carrier_customAttribute/{index,new,edit,save,delete,activate}
 *
 * 提供"自定义属性"管理菜单的列表 / 新增 / 编辑 / 删除(软)/ 激活 / Im / Ex 入口。
 *
 * 关联文档: docs/architecture/carrier-global-custom-field-defs.md
 */
class XFE_Carrier_Adminhtml_Carrier_CustomAttributeController
    extends Mage_Adminhtml_Controller_Action
{
    const REGISTRY_KEY_MODEL = 'xfe_carrier_custom_attribute_data';

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('admin/system/xfe_carrier');
    }

    protected function _initAction()
    {
        $helper = Mage::helper('xfe_carrier');
        $this->loadLayout()
            ->_setActiveMenu('system/xfe_carrier/custom_attribute')
            ->_addBreadcrumb(
                $helper->__('Custom Attribute Management'),
                $helper->__('Custom Attribute Management')
            )
            ->_addBreadcrumb(
                $helper->__('Custom Attributes'),
                $helper->__('Custom Attributes')
            );
        return $this;
    }

    /**
     * 列表页。支持 ?entity_type=carrier 过滤。
     */
    public function indexAction()
    {
        $entityType = $this->getRequest()->getParam('entity_type');
        if ($entityType !== null) {
            Mage::register('xfe_carrier_custom_attribute_filter', $entityType);
        }
        $this->_initAction()
            ->_title($this->__('Custom Attributes'))
            ->renderLayout();
    }

    public function newAction()
    {
        $this->_forward('edit');
    }

    /**
     * 编辑页(创建 / 更新)。
     */
    public function editAction()
    {
        $id = (int) $this->getRequest()->getParam('id');
        $model = Mage::getModel('xfe_carrier/custom_attribute');
        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('xfe_carrier')->__('记录不存在。')
                );
                return $this->_redirect('*/*/index');
            }
        }
        Mage::register(self::REGISTRY_KEY_MODEL, $model);
        $this->_initAction()
            ->_title($id ? $this->__('编辑自定义属性') : $this->__('新增自定义属性'))
            ->renderLayout();
    }

    /**
     * 保存(create / update)。
     */
    public function saveAction()
    {
        $helper = Mage::helper('xfe_carrier');
        if (!$this->_validateFormKey()) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Invalid form key, please reload the page.')
            );
            return $this->_redirect('*/*/index');
        }

        $post = $this->getRequest()->getPost();
        $id   = (int) $this->getRequest()->getParam('id');
        try {
            if ($id) {
                XFE_Carrier_Model_Service_Registry::customAttributeService()
                    ->updateDef($id, $post);
            } else {
                XFE_Carrier_Model_Service_Registry::customAttributeService()
                    ->createDef($post);
            }
            Mage::getSingleton('adminhtml/session')->addSuccess(
                $helper->__('自定义属性已保存。')
            );
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            return $this->_redirect('*/*/edit', $id ? array('id' => $id) : array());
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('保存失败: %s', $e->getMessage())
            );
            return $this->_redirect('*/*/edit', $id ? array('id' => $id) : array());
        }
        return $this->_redirect('*/*/index');
    }

    /**
     * 软删除(置 is_active=0)。
     */
    public function deleteAction()
    {
        $helper = Mage::helper('xfe_carrier');
        if (!$this->_validateFormKey()) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Invalid form key, please reload the page.')
            );
            return $this->_redirect('*/*/index');
        }
        $id = (int) $this->getRequest()->getParam('id');
        if ($id) {
            try {
                $ok = XFE_Carrier_Model_Service_Registry::customAttributeService()
                    ->softDeleteDef($id);
                if ($ok) {
                    Mage::getSingleton('adminhtml/session')->addSuccess(
                        $helper->__('已停用自定义属性。')
                    );
                } else {
                    Mage::getSingleton('adminhtml/session')->addError(
                        $helper->__('记录不存在。')
                    );
                }
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        return $this->_redirect('*/*/index');
    }

    /**
     * 重新激活(置 is_active=1)。
     */
    public function activateAction()
    {
        $helper = Mage::helper('xfe_carrier');
        if (!$this->_validateFormKey()) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Invalid form key, please reload the page.')
            );
            return $this->_redirect('*/*/index');
        }
        $id = (int) $this->getRequest()->getParam('id');
        if ($id) {
            try {
                XFE_Carrier_Model_Service_Registry::customAttributeService()
                    ->activateDef($id);
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    $helper->__('已启用自定义属性。')
                );
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        return $this->_redirect('*/*/index');
    }
}
