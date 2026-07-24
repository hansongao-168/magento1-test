<?php
/**
 * Invoice Adjustment Admin Controller
 *
 * @category   Community
 * @package    XFE_InvoiceAdjustment
 */
class XFE_InvoiceAdjustment_Adminhtml_Invoiceadjustment_AdjustmentController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Check ACL
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed('sales/invoiceadjustment');
    }

    /**
     * Index action - list adjustments
     */
    public function indexAction()
    {
        $this->_title($this->__('Sales'))
             ->_title($this->__('Invoice Adjustments'));

        $this->loadLayout();
        $this->_setActiveMenu('sales/invoiceadjustment');
        $this->renderLayout();
    }

    /**
     * Grid action (AJAX)
     */
    public function gridAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('invoiceadjustment/adminhtml_adjustment_grid')->toHtml()
        );
    }

    /**
     * New action
     */
    public function newAction()
    {
        $this->_forward('edit');
    }

    /**
     * Edit action
     */
    public function editAction()
    {
        $id    = $this->getRequest()->getParam('id');
        $model = Mage::getModel('invoiceadjustment/adjustment');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('invoiceadjustment')->__('Adjustment does not exist.')
                );
                $this->_redirect('*/*/');
                return;
            }
        }

        Mage::register('current_adjustment', $model);

        $this->_title($model->getId()
            ? $this->__('Edit Adjustment #%s', $model->getId())
            : $this->__('New Adjustment')
        );

        $this->loadLayout();
        $this->_setActiveMenu('sales/invoiceadjustment');
        $this->renderLayout();
    }

    /**
     * Save action
     */
    public function saveAction()
    {
        if ($data = $this->getRequest()->getPost()) {
            $id      = $this->getRequest()->getParam('id');
            $model   = Mage::getModel('invoiceadjustment/adjustment');

            if ($id) {
                $model->load($id);
                if (!$model->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('invoiceadjustment')->__('Adjustment does not exist.')
                    );
                    $this->_redirect('*/*/');
                    return;
                }

                if (!$model->canEdit()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('invoiceadjustment')->__('Cannot edit a processed adjustment.')
                    );
                    $this->_redirect('*/*/edit', array('id' => $id));
                    return;
                }
            }

            try {
                // Process orders_data from form
                $ordersData = array();
                if (isset($data['orders_data']) && is_array($data['orders_data'])) {
                    foreach ($data['orders_data'] as $orderData) {
                        if (!empty($orderData['order_id']) || !empty($orderData['order_number'])) {
                            $ordersData[] = $orderData;
                        }
                    }
                }

                // Process new order if submitted
                if (!empty($data['new_order_number'])) {
                    $order = Mage::getModel('sales/order')
                        ->loadByIncrementId($data['new_order_number']);

                    if ($order->getId()) {
                        $ordersData[] = array(
                            'order_id'     => $order->getId(),
                            'order_number' => $order->getIncrementId(),
                            'original_ht'  => $order->getSubtotal(),
                            'original_tva' => $order->getTaxAmount(),
                            'original_ttc' => $order->getGrandTotal(),
                        );
                    }
                }

                $model->addData(array(
                    'adjustment_name' => $data['adjustment_name'],
                    'status'          => isset($data['status']) ? $data['status'] : 'pending',
                    'reason'          => isset($data['reason']) ? $data['reason'] : '',
                    'orders_data'     => $ordersData,
                ));

                $model->save();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('invoiceadjustment')->__('Adjustment has been saved.')
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

    /**
     * Delete action
     */
    public function deleteAction()
    {
        $id    = $this->getRequest()->getParam('id');
        $model = Mage::getModel('invoiceadjustment/adjustment');

        if ($id) {
            try {
                $model->load($id);
                if (!$model->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('invoiceadjustment')->__('Adjustment does not exist.')
                    );
                    $this->_redirect('*/*/');
                    return;
                }

                $model->delete();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('invoiceadjustment')->__('Adjustment has been deleted.')
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/');
    }

    /**
     * Mass delete action
     */
    public function massDeleteAction()
    {
        $ids = $this->getRequest()->getParam('adjustment');

        if (!is_array($ids)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('invoiceadjustment')->__('Please select adjustment(s).')
            );
        } else {
            try {
                $deleted = 0;
                foreach ($ids as $id) {
                    $model = Mage::getModel('invoiceadjustment/adjustment')->load($id);
                    if ($model->getId() && $model->canDelete()) {
                        $model->delete();
                        $deleted++;
                    }
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('invoiceadjustment')->__('%d adjustment(s) have been deleted.', $deleted)
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/');
    }

    /**
     * Mass status change action
     */
    public function massStatusAction()
    {
        $ids    = $this->getRequest()->getParam('adjustment');
        $status = $this->getRequest()->getParam('status');

        if (!is_array($ids)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('invoiceadjustment')->__('Please select adjustment(s).')
            );
        } else {
            try {
                $updated = 0;
                foreach ($ids as $id) {
                    $model = Mage::getModel('invoiceadjustment/adjustment')->load($id);
                    if ($model->getId()) {
                        $model->setStatus($status)->save();
                        $updated++;
                    }
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('invoiceadjustment')->__('%d adjustment(s) status have been updated.', $updated)
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/');
    }

    /**
     * Load order data (AJAX)
     */
    public function loadOrderAction()
    {
        $orderNumber = $this->getRequest()->getParam('order_number');
        $adjustmentId = $this->getRequest()->getParam('adjustment_id', 0);

        $result = array();

        try {
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderNumber);

            if (!$order->getId()) {
                $result['error'] = Mage::helper('invoiceadjustment')->__('Order not found.');
            } else {
                // Check if order already in this adjustment
                if ($adjustmentId) {
                    $existing = Mage::getResourceModel('invoiceadjustment/adjustment_order_collection')
                        ->addFieldToFilter('adjustment_id', $adjustmentId)
                        ->addFieldToFilter('order_id', $order->getId());
                    if ($existing->getSize() > 0) {
                        $result['error'] = Mage::helper('invoiceadjustment')->__('Order already in this adjustment.');
                        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                        return;
                    }
                }

                $result['order_id']     = $order->getId();
                $result['order_number'] = $order->getIncrementId();
                $result['original_ht']  = $order->getSubtotal();
                $result['original_tva'] = $order->getTaxAmount();
                $result['original_ttc'] = $order->getGrandTotal();
            }
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    /**
     * Export CSV action
     */
    public function exportCsvAction()
    {
        $fileName = 'invoice_adjustments.csv';
        $grid     = $this->getLayout()->createBlock('invoiceadjustment/adminhtml_adjustment_grid');
        $this->_prepareDownloadResponse($fileName, $grid->getCsvFile());
    }
}
