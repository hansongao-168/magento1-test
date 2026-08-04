<?php

/**
 * Label Print Admin Controller
 *
 * Read-only browse of xfe_label_print plus a regenerate action that
 * re-prints a label and moves the previous file to old_path_file.
 *
 *   - indexAction     : grid
 *   - gridAction      : ajax reload
 *   - viewAction      : detail page (read-only)
 *   - downloadAction  : stream the stored label file back to the browser
 *   - regenerateAction: replace a row's path_file; the prior file is
 *                       moved to old_path_file. Locate the target row
 *                       by id OR by (order_id + tracking_number_id + ym)
 *   - massDeleteAction: prune selected rows
 *
 * Rows are normally produced by other modules through the helper's
 * record() / dispatchLabelResponse() entry points; regenerateAction is
 * the one place where an admin can manually trigger a re-print.
 */
class XFE_LabelPrint_Adminhtml_PrintController extends Mage_Adminhtml_Controller_Action
{
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('admin/system/xfe_labelprint');
    }

    protected function _initAction()
    {
        $helper = Mage::helper('xfe_labelprint');
        $this->loadLayout()
            ->_setActiveMenu('system/xfe_labelprint')
            ->_addBreadcrumb(
                $helper->__('Label Print'),
                $helper->__('Label Print')
            );
        return $this;
    }

    public function indexAction()
    {
        $this->_initAction()
            ->renderLayout();
    }

    public function gridAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('xfe_labelprint/adminhtml_print_grid')->toHtml()
        );
    }

    /**
     * Detail view for a single row.
     */
    public function viewAction()
    {
        $id     = (int)$this->getRequest()->getParam('id');
        $helper = Mage::helper('xfe_labelprint');

        if (!$id) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Missing row id.')
            );
            return $this->_redirect('*/*/');
        }

        $row = Mage::getModel('xfe_labelprint/print')->load($id);
        if (!$row->getId()) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('This row no longer exists.')
            );
            return $this->_redirect('*/*/');
        }

        Mage::register('xfe_labelprint_row', $row);
        $this->_initAction()
            ->_addBreadcrumb(
                $helper->__('View Entry'),
                $helper->__('View Entry')
            )
            ->renderLayout();
    }

    /**
     * Stream the stored label file as a download.
     *
     * `kind` selects which file to serve:
     *   - "current" (default): path_file
     *   - "old"             : old_path_file
     */
    public function downloadAction()
    {
        $id     = (int)$this->getRequest()->getParam('id');
        $kind   = (string)$this->getRequest()->getParam('kind', 'current');
        $helper = Mage::helper('xfe_labelprint');

        if (!$id) {
            return $this->_redirect('*/*/');
        }

        $row = Mage::getModel('xfe_labelprint/print')->load($id);
        if (!$row->getId()) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('This row no longer exists.')
            );
            return $this->_redirect('*/*/');
        }

        $abs = $kind === 'old'
            ? $row->getOldFileAbsolutePath()
            : $row->getFileAbsolutePath();

        if ($abs === null || !is_file($abs)) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('The file is no longer available on disk.')
            );
            return $this->_redirect('*/*/view', array('id' => $id));
        }

        $response = $this->getResponse();
        $response->setHeader('Content-Type', $this->_guessMime($abs), true);
        $response->setHeader(
            'Content-Disposition',
            'attachment; filename="' . basename($abs) . '"',
            true
        );
        $response->setHeader('Content-Length', (string)filesize($abs), true);
        $response->setBody(file_get_contents($abs));
        return $response;
    }

    /**
     * Replace a row's path_file with a freshly supplied file. The
     * previous path_file is moved to old_path_file so the admin can
     * still download it from the view page.
     *
     * Locate the target row by either:
     *   - id                 (preferred for explicit row-level re-print)
     *   - order_id +
     *     tracking_number_id + ym  ("YYYY-MM" or "YYYY/MM")
     *
     * New file payload accepts either:
     *   - path_file         caller already placed the file under var/
     *   - label_content     raw / base64 / data:URL / absolute path
     *
     * additional_data may be supplied as a JSON string; it is merged
     * into the row's additional_data blob.
     */
    public function regenerateAction()
    {
        $req    = $this->getRequest();
        $helper = Mage::helper('xfe_labelprint');

        if (!$req->isPost()) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Re-print requires a POST request.')
            );
            return $this->_redirect('*/*/');
        }

        $id              = (int)$req->getParam('id');
        $orderId         = (int)$req->getParam('order_id');
        $trackingId      = (int)$req->getParam('tracking_number_id');
        $ym              = (string)$req->getParam('ym');

        $payload = array();
        $newPath = (string)$req->getParam('path_file');
        if ($newPath !== '') {
            $payload['path_file'] = $newPath;
        }
        $labelContent = $req->getParam('label_content');
        if ($labelContent !== null && $labelContent !== '') {
            $payload['label_content'] = $labelContent;
        }
        $payload['label_format']   = (string)$req->getParam('label_format', 'pdf');
        $payload['label_filename'] = (string)$req->getParam('label_filename', '');
        $payload['printed_at']     = (string)$req->getParam('printed_at');

        $additionalJson = (string)$req->getParam('additional_data');
        if ($additionalJson !== '') {
            $decoded = Mage::helper('core')->jsonDecode($additionalJson);
            if (is_array($decoded)) {
                $payload['additional_data'] = $decoded;
            }
        }

        if (empty($payload['path_file']) && empty($payload['label_content'])) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Provide either path_file or label_content for the new file.')
            );
            return $this->_redirect('*/*/');
        }

        // Locate the row.
        if ($id) {
            $row = Mage::getModel('xfe_labelprint/print')->load($id);
            if (!$row->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    $helper->__('Row #%s no longer exists.', $id)
                );
                return $this->_redirect('*/*/');
            }
            $result = $helper->replaceLabel($row, $payload);
        } else {
            if (!$orderId && !$trackingId) {
                Mage::getSingleton('adminhtml/session')->addError(
                    $helper->__('Provide either id or order_id + tracking_number_id + ym.')
                );
                return $this->_redirect('*/*/');
            }
            $result = $helper->replaceByMonth($orderId, $trackingId, $ym, $payload);
        }

        if ($result === false) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('No matching label row was found for the given order / tracking / month.')
            );
            return $this->_redirect('*/*/');
        }

        Mage::getSingleton('adminhtml/session')->addSuccess(
            $helper->__('Label regenerated. Previous file moved to old_path_file.')
        );
        return $this->_redirect('*/*/view', array('id' => $result));
    }

    /**
     * Bulk delete selected rows. Files on disk are intentionally kept so
     * a re-import (or a manual restore) can re-attach them.
     */
    public function massDeleteAction()
    {
        $ids     = (array)$this->getRequest()->getParam('ids');
        $helper  = Mage::helper('xfe_labelprint');
        $deleted = 0;

        if (!$ids) {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('No rows selected.')
            );
            return $this->_redirect('*/*/');
        }

        foreach ($ids as $id) {
            $row = Mage::getModel('xfe_labelprint/print')->load((int)$id);
            if (!$row->getId()) {
                continue;
            }
            try {
                $row->delete();
                $deleted++;
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }

        if ($deleted > 0) {
            Mage::getSingleton('adminhtml/session')->addSuccess(
                $helper->__('Deleted %s row(s).', $deleted)
            );
        } else {
            Mage::getSingleton('adminhtml/session')->addError(
                $helper->__('Nothing was deleted.')
            );
        }

        return $this->_redirect('*/*/');
    }

    /**
     * MIME-type guess from the file extension.
     *
     * @param string $abs
     * @return string
     */
    protected function _guessMime($abs)
    {
        $map = array(
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'tiff' => 'image/tiff',
            'tif'  => 'image/tiff',
            'zpl'  => 'text/plain',
            'epl'  => 'text/plain',
        );
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        return isset($map[$ext]) ? $map[$ext] : 'application/octet-stream';
    }
}
