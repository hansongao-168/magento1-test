<?php

class XFE_LabelPrint_Model_Extraction_Print
{

    protected $_printTypeData;

    /**
     * @param Mage_Sales_Model_Order $order
     * @param array $data
     * @return array
     */
    public function getReplaceFiles($order, $data)
    {
        $printFiles = $data['print_files'] ?? [];
        $trackingNumbers = $data['tracking_numbers'] ?? [];

        /** @var XFE_LabelPrint_Helper_Data $ $helper */
        $helper = Mage::helper('xfe_labelprint');

        $saveData = [];
        foreach ($printFiles as $printFile) {
            /** @var XFE_LabelPrint_Model_Print $labelPrintModel */
            $labelPrintModel = Mage::getModel('xfe_labelprint/print');
            $trackingNumberRow = $this->_getTrackingNumberId($trackingNumbers, $printFile);
            $row = [
                'order_id' => $order->getId(),
                'tracking_number_id' => $trackingNumberRow['id'] ?? 0,
                'old_path_file' => $printFile,
                'path_file' => '',
            ];
            $_trackingNumber = $trackingNumberRow['tracking_number'] ?? '';;
            try {
                if (is_file($printFile)) {
                    $row['path_file'] = $this->_getNewPrintFile($printFile);
                    $labelPrintModel->loadByOldPathFile(
                        $row['order_id'], $row['tracking_number_id'], $row['old_path_file']
                    );

                    if (
                        !$labelPrintModel->getData('path_file')
                        || $labelPrintModel->getData('path_file') != $row['path_file']
                    ) {
                        $row['old_path_file'] = $helper->normalizePath($row['old_path_file']);
                        $row['path_file'] = $helper->normalizePath($row['path_file']);
                        $labelPrintModel->addData($row);
                        $printType = $this->_getPrintType($order, $printFile, $_trackingNumber);
                        $labelPrintModel->setAdditionalDataArray([
                            'print_type' => $printType,
                        ]);
                        $labelPrintModel->save();
                        $row['note'] = '保存成功';
                    } else {
                        $row['note'] = '文件路径未改变';
                    }
                } else {
                    $row['note'] = '文件不存在';
                }

                $row['tracking_number'] = $_trackingNumber;
                $row['created_at'] = $labelPrintModel->getData('created_at');

                $saveData[] = $row;
            } catch (Exception $e) {
                Mage::logException($e);
                $row['note'] = '发送错误';
                $saveData[] = $row;
            }
        }
        return $saveData;
    }

    protected function _getPrintTypeData()
    {
        if (is_null($this->_printTypeData)) {
            $this->_printTypeData = [
                '_merge' => 'merge',
                '_CN23' => 'cn23',
                '_z_custom_invoice' => 'custom_invoice',
                'customer_invoice_' => 'customer_invoice',
                '_customer_invoice' => 'customer_invoice',
                '_exit_country' => 'exit_country',
                '_form' => 'form',
                '_receipt' => 'receipt',
                '_cn_receipt' => 'cn_receipt',
                '_note' => 'note',
                '_auxiliary_delivery_note' => 'auxiliary_delivery_note',
                'label' => 'label'
            ];
        }
        return $this->_printTypeData;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param string $printFile
     * @param string $_trackingNumber
     * @return string
     */
    protected function _getPrintType($order, $printFile, $_trackingNumber)
    {
        foreach ($this->_getPrintTypeData() as $pattern => $type) {
            if (stripos($printFile, $pattern) !== false) {
                return $type;
            }
            if (
                stripos($printFile, $order->getIncrementId()) !== false
                || stripos($printFile, $_trackingNumber) !== false
            ) {
                return 'label';
            }
        }
        return '';
    }

    /**
     * @param XFE_LabelPrint_Model_Print $labelPrintModel
     * @param string $printFile
     * @return string
     */
    protected function _getNewPrintFile($printFile)
    {
        /** @var XFE_LabelPrint_Helper_Data $helper */
        $helper = Mage::helper('xfe_labelprint');
        return $helper->replacePathFileName($printFile);
    }

    /**
     * @param array $trackingNumbers
     * @param string $printFile
     * @return array
     */
    protected function _getTrackingNumberId($trackingNumbers, $printFile)
    {
        foreach ($trackingNumbers as $trackingNumber => $id) {
            if (strpos($printFile, $trackingNumber) !== false) {
                return ['id' => $id, 'tracking_number' => $trackingNumber];
            }
        }
        return [];
    }

}