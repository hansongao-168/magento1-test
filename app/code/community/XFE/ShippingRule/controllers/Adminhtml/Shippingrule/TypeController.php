<?php
/**
 * XFE ShippingRule Type Admin Controller
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Adminhtml_Shippingrule_TypeController extends Mage_Adminhtml_Controller_Action
{
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed('sales/xfeshippingrule/type');
    }

    public function indexAction()
    {
        $this->_title($this->__('Sales'))
             ->_title($this->__('Shipping Rule Types'));

        $this->loadLayout();
        $this->_setActiveMenu('sales/xfeshippingrule_type');
        $this->renderLayout();
    }

    public function gridAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('xfeshippingrule/adminhtml_type_grid')->toHtml()
        );
    }

    public function newAction()
    {
        $this->_forward('edit');
    }

    public function editAction()
    {
        $id    = $this->getRequest()->getParam('id');
        $model = Mage::getModel('xfeshippingrule/type');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('xfeshippingrule')->__('Type does not exist.')
                );
                $this->_redirect('*/*/');
                return;
            }
        }

        Mage::register('current_type', $model);

        $this->_title($model->getId()
            ? $this->__('Edit Type #%s', $model->getId())
            : $this->__('New Type')
        );

        $this->loadLayout();
        $this->_setActiveMenu('sales/xfeshippingrule_type');
        $this->renderLayout();
    }

    public function saveAction()
    {
        if ($data = $this->getRequest()->getPost()) {
            $id    = $this->getRequest()->getParam('id');
            $model = Mage::getModel('xfeshippingrule/type');

            if ($id) {
                $model->load($id);
                if (!$model->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('xfeshippingrule')->__('Type does not exist.')
                    );
                    $this->_redirect('*/*/');
                    return;
                }
            }

            try {
                $model->addData(array(
                    'calculation_type' => $data['calculation_type'],
                    'nature'           => $data['nature'],
                    'description'      => isset($data['description']) ? $data['description'] : '',
                    'status'           => isset($data['status']) ? (int)$data['status'] : 1,
                ));

                $model->save();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('xfeshippingrule')->__('Type has been saved.')
                );
                Mage::getSingleton('adminhtml/session')->setFormData(false);

                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('*/*/edit', array('id' => $model->getId()));
                    return;
                }

                $this->_redirect('*/*/');
                return;

            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::getSingleton('adminhtml/session')->setFormData($data);
                $this->_redirect('*/*/edit', array('id' => $id));
                return;
            }
        }

        $this->_redirect('*/*/');
    }

    public function deleteAction()
    {
        $id    = $this->getRequest()->getParam('id');
        $model = Mage::getModel('xfeshippingrule/type');

        if ($id) {
            try {
                $model->load($id);
                if (!$model->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('xfeshippingrule')->__('Type does not exist.')
                    );
                    $this->_redirect('*/*/');
                    return;
                }

                $model->delete();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('xfeshippingrule')->__('Type has been deleted.')
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/');
    }

    public function massDeleteAction()
    {
        $ids = $this->getRequest()->getParam('type');

        if (!is_array($ids)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('xfeshippingrule')->__('Please select type(s).')
            );
        } else {
            try {
                $deleted = 0;
                foreach ($ids as $id) {
                    $model = Mage::getModel('xfeshippingrule/type')->load($id);
                    if ($model->getId()) {
                        $model->delete();
                        $deleted++;
                    }
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('xfeshippingrule')->__('%d type(s) have been deleted.', $deleted)
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/');
    }

    public function massStatusAction()
    {
        $ids    = $this->getRequest()->getParam('type');
        $status = $this->getRequest()->getParam('status');

        if (!is_array($ids)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('xfeshippingrule')->__('Please select type(s).')
            );
        } else {
            try {
                $updated = 0;
                foreach ($ids as $id) {
                    $model = Mage::getModel('xfeshippingrule/type')->load($id);
                    if ($model->getId()) {
                        $model->setStatus((int)$status)->save();
                        $updated++;
                    }
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('xfeshippingrule')->__('%d type(s) status have been updated.', $updated)
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/');
    }
}
