<?php

class XFE_LabelPrint_Model_Resource_Print extends Mage_Core_Model_Mysql4_Abstract
{

    protected function _construct()
    {
        $this->_init('xfe_labelprint/print', 'id');
    }

    /**
     * @param Mage_Core_Model_Abstract $object
     * @return XFE_LabelPrint_Model_Resource_Print
     */
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        $now = gmdate('Y-m-d H:i:s');
        if ($object->isObjectNew()) {
            $object->setCreatedAt($now);
        }
        $object->setUpdatedAt($now);

        return parent::_beforeSave($object);
    }

    public function loadByOldPathFile($object, $orderId, $trackingNumberId, $oldPathFile)
    {
        $read = $this->_getReadAdapter();
        $select = $read->select()->from($this->getMainTable());

        /** @var XFE_LabelPrint_Helper_Data $ $helper */
        $helper = Mage::helper('xfe_labelprint');
        $oldPathFile = $helper->normalizePath($oldPathFile);

        $select->where('order_id=?', (int)$orderId)
            ->where('tracking_number_id=?', (int)$trackingNumberId)
            ->where('old_path_file = ?', $oldPathFile)
            ->limit(1, 0);

        $data = $read->fetchRow($select);
        if ($data) $object->addData($data);
        $this->_afterLoad($object);
        return $this;
    }

}