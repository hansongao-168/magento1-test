<?php

/**
 * Carrier Model
 *
 * Responsibility (and ONLY this):
 *   - map xfe_carrier row <-> business object
 *   - keep basic fields sane (timestamps, before/after save)
 *   - fire domain events that the rest of the system can react to
 *
 * The model is intentionally child-agnostic. Logo / account / rule management
 * lives in dedicated services:
 *
 *     XFE_Carrier_Model_Service_Logo     XFE_Carrier_Model_Service_Account
 *     XFE_Carrier_Model_Service_Rule
 *
 * Callers (Controller, Blocks) reach those services directly via
 * {@link XFE_Carrier_Model_Service_Registry}. The Carrier model never
 * imports them and never queries their tables itself.
 */
class XFE_Carrier_Model_Carrier extends Mage_Core_Model_Abstract
{
    /** @var string */
    protected $_eventPrefix = 'xfe_carrier_carrier';

    /** @var string */
    protected $_eventObject = 'carrier';

    protected function _construct()
    {
        $this->_init('xfe_carrier/carrier');
    }

    /**
     * Stub kept for backward compatibility. Read-only access by id.
     * Removed: setAccountsData / setRulesData / setLogoFile / setLogoLabel /
     *          setLogoType. Callers MUST go through the services instead.
     *
     * @return XFE_Carrier_Model_Resource_Carrier_Logo_Collection
     */
    public function getLogos()
    {
        if (!$this->hasData('logos')) {
            $logos = Mage::getModel('xfe_carrier/carrier_logo')->getCollection()
                ->addFieldToFilter('carrier_id', $this->getId());
            $this->setData('logos', $logos);
        }
        return $this->getData('logos');
    }

    /**
     * Read-only: pick the best-matching logo and return its public URL.
     * Delegates to LogoService so that URL/IO concerns stay out of the model.
     *
     * @param string|null $logoType
     * @param string|null $sizeType  Legacy field, still honoured.
     * @return string|null
     */
    public function getLogoUrl($logoType = null, $sizeType = null)
    {
        if (!$this->getId()) {
            return null;
        }

        foreach ($this->getLogos() as $logo) {
            $match = true;
            if ($logoType !== null) {
                if ($logo->getLogoType() !== $logoType) {
                    $match = false;
                }
            } elseif ($sizeType !== null) {
                if ((string)$logo->getSizeType() !== (string)$sizeType) {
                    $match = false;
                }
            }
            if ($match) {
                return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)
                    . $logo->getPath();
            }
        }
        return null;
    }

    /**
     * Fires `xfe_carrier_carrier_delete_before` so that any owner of a
     * related table can clean up. The model knows nothing concrete about
     * those tables; subscribers (controllers / services) do.
     */
    protected function _beforeDelete()
    {
        parent::_beforeDelete();
        Mage::dispatchEvent('xfe_carrier_carrier_delete_before', array(
            'carrier' => $this,
        ));
        return $this;
    }

    protected function _beforeSave()
    {
        parent::_beforeSave();

        $now = Varien_Date::now();
        if ($this->isObjectNew()) {
            $this->setCreatedAt($now);
        }
        $this->setUpdatedAt($now);

        return $this;
    }
}
