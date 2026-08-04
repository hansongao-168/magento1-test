<?php

/**
 * Resource model for xfe_labelprint/print.
 *
 * Touches created_at / updated_at around save so callers only need to
 * set the data fields and the timestamps stay accurate.
 */
class XFE_LabelPrint_Model_Resource_Print extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('xfe_labelprint/print', 'id');
    }

    /**
     * Touch timestamps before save. created_at is set only for new rows;
     * updated_at is bumped on every save so a re-print that overwrites
     * path_file stays traceable.
     *
     * @param Mage_Core_Model_Abstract $object
     * @return XFE_LabelPrint_Model_Resource_Print
     */
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        $now = Varien_Date::now();
        if ($object->isObjectNew()) {
            $object->setCreatedAt($now);
        }
        $object->setUpdatedAt($now);

        return parent::_beforeSave($object);
    }
}
