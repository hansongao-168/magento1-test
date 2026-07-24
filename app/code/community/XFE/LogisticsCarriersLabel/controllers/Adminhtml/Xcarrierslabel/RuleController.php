<?php
/**
 * XFE LogisticsCarriersLabel Rule Admin Controller
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_Adminhtml_Logisticscarrierslabel_RuleController extends Mage_Adminhtml_Controller_Action
{
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed('sales/xcarrierslabel/rule');
    }

    public function indexAction()
    {
        $this->_title($this->__('Sales'))
             ->_title($this->__('Logistic Carriers Label'));

        $this->loadLayout();
        $this->_setActiveMenu('sales/xcarrierslabel');
        $this->renderLayout();
    }

    public function gridAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('xcarrierslabel/adminhtml_rule_grid')->toHtml()
        );
    }

    public function newAction()
    {
        $this->_forward('edit');
    }

    public function editAction()
    {
        $id    = $this->getRequest()->getParam('id');
        $model = Mage::getModel('xcarrierslabel/rule');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('xcarrierslabel')->__('Rule does not exist.')
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
        $this->_setActiveMenu('sales/xcarrierslabel');
        $this->renderLayout();
    }

    public function saveAction()
    {
        if ($data = $this->getRequest()->getPost()) {
            $id    = $this->getRequest()->getParam('id');
            $model = Mage::getModel('xcarrierslabel/rule');

            if ($id) {
                $model->load($id);
                if (!$model->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('xcarrierslabel')->__('Rule does not exist.')
                    );
                    $this->_redirect('*/*/');
                    return;
                }
            }

            try {
                $model->addData(array(
                    'country_code'          => $data['country_code'],
                    'country_name'          => $data['country_name'],
                    'partner_name'          => $data['partner_name'],
                    'shipping_company_id'   => isset($data['shipping_company_id']) ? (int)$data['shipping_company_id'] : 0,
                    'tracking_url_template' => isset($data['tracking_url_template']) ? $data['tracking_url_template'] : '',
                    'display_title'         => isset($data['display_title']) ? $data['display_title'] : 'Chrono Classic',
                    'background_color'      => isset($data['background_color']) ? $data['background_color'] : '',
                    'text_color'            => isset($data['text_color']) ? $data['text_color'] : '',
                    'status'                => isset($data['status']) ? (int)$data['status'] : 1,
                ));

                // Pass groups_data for _afterSave condition processing
                if (isset($data['groups_data'])) {
                    $model->setGroupsData($data['groups_data']);
                }

                $model->save();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('xcarrierslabel')->__('Rule has been saved.')
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
        $model = Mage::getModel('xcarrierslabel/rule');

        if ($id) {
            try {
                $model->load($id);
                if (!$model->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('xcarrierslabel')->__('Rule does not exist.')
                    );
                    $this->_redirect('*/*/');
                    return;
                }

                $model->delete();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('xcarrierslabel')->__('Rule has been deleted.')
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
                Mage::helper('xcarrierslabel')->__('Please select rule(s).')
            );
        } else {
            try {
                $deleted = 0;
                foreach ($ids as $id) {
                    $model = Mage::getModel('xcarrierslabel/rule')->load($id);
                    if ($model->getId()) {
                        $model->delete();
                        $deleted++;
                    }
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('xcarrierslabel')->__('%d rule(s) have been deleted.', $deleted)
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
                Mage::helper('xcarrierslabel')->__('Please select rule(s).')
            );
        } else {
            try {
                $updated = 0;
                foreach ($ids as $id) {
                    $model = Mage::getModel('xcarrierslabel/rule')->load($id);
                    if ($model->getId()) {
                        $model->setStatus((int)$status)->save();
                        $updated++;
                    }
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('xcarrierslabel')->__('%d rule(s) status have been updated.', $updated)
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/');
    }
}
