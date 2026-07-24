<?php
/**
 * XFE ShippingRule Rule Admin Controller
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Adminhtml_Shippingrule_RuleController extends Mage_Adminhtml_Controller_Action
{
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed('sales/xfeshippingrule/rule');
    }

    public function indexAction()
    {
        $this->_title($this->__('Sales'))
             ->_title($this->__('Shipping Rules'));

        $this->loadLayout();
        $this->_setActiveMenu('sales/xfeshippingrule');
        $this->renderLayout();
    }

    public function gridAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('xfeshippingrule/adminhtml_rule_grid')->toHtml()
        );
    }

    public function newAction()
    {
        $this->_forward('edit');
    }

    public function editAction()
    {
        $id    = $this->getRequest()->getParam('id');
        $model = Mage::getModel('xfeshippingrule/rule');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('xfeshippingrule')->__('Rule does not exist.')
                );
                $this->_redirect('*/*/');
                return;
            }
        }

        Mage::register('current_rule', $model);

        $this->_title($model->getId()
            ? $this->__('Edit Rule #%s', $model->getId())
            : $this->__('New Rule')
        );

        $this->loadLayout();
        $this->_setActiveMenu('sales/xfeshippingrule');
        $this->renderLayout();
    }

    public function saveAction()
    {
        if ($data = $this->getRequest()->getPost()) {
            $id    = $this->getRequest()->getParam('id');
            $model = Mage::getModel('xfeshippingrule/rule');

            if ($id) {
                $model->load($id);
                if (!$model->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('xfeshippingrule')->__('Rule does not exist.')
                    );
                    $this->_redirect('*/*/');
                    return;
                }
            }

            try {
                $model->addData(array(
                    'type_id'      => (int)$data['type_id'],
                    'billing_type' => $data['billing_type'],
                    'shipping_fee' => (float)$data['shipping_fee'],
                    'package_min'  => (int)$data['package_min'],
                    'package_max'  => (!empty($data['package_max']) ? (int)$data['package_max'] : null),
                    'description'  => isset($data['description']) ? $data['description'] : '',
                    'status'       => isset($data['status']) ? (int)$data['status'] : 1,
                ));

                // Pass groups_data for _afterSave condition processing
                if (isset($data['groups_data'])) {
                    $model->setGroupsData($data['groups_data']);
                }

                $model->save();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('xfeshippingrule')->__('Rule has been saved.')
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
        $model = Mage::getModel('xfeshippingrule/rule');

        if ($id) {
            try {
                $model->load($id);
                if (!$model->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('xfeshippingrule')->__('Rule does not exist.')
                    );
                    $this->_redirect('*/*/');
                    return;
                }

                $model->delete();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('xfeshippingrule')->__('Rule has been deleted.')
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/');
    }

    public function massDeleteAction()
    {
        $ids = $this->getRequest()->getParam('rule');

        if (!is_array($ids)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('xfeshippingrule')->__('Please select rule(s).')
            );
        } else {
            try {
                $deleted = 0;
                foreach ($ids as $id) {
                    $model = Mage::getModel('xfeshippingrule/rule')->load($id);
                    if ($model->getId()) {
                        $model->delete();
                        $deleted++;
                    }
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('xfeshippingrule')->__('%d rule(s) have been deleted.', $deleted)
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/');
    }

    public function massStatusAction()
    {
        $ids    = $this->getRequest()->getParam('rule');
        $status = $this->getRequest()->getParam('status');

        if (!is_array($ids)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('xfeshippingrule')->__('Please select rule(s).')
            );
        } else {
            try {
                $updated = 0;
                foreach ($ids as $id) {
                    $model = Mage::getModel('xfeshippingrule/rule')->load($id);
                    if ($model->getId()) {
                        $model->setStatus((int)$status)->save();
                        $updated++;
                    }
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('xfeshippingrule')->__('%d rule(s) status have been updated.', $updated)
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/');
    }
}
