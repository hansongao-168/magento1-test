<?php
/**
 * SocialAccount Resource Model
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Resource_Social_Account extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeoauth2/social_account', 'id');
    }

    /**
     * Load by provider + provider_user_id
     *
     * @param Mage_Core_Model_Abstract $object
     * @param string $provider
     * @param string $providerUserId
     * @return $this
     */
    public function loadByProvider($object, $provider, $providerUserId)
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable())
            ->where('provider = :provider')
            ->where('provider_user_id = :provider_user_id');

        $bind = array(':provider' => $provider, ':provider_user_id' => $providerUserId);
        $data = $adapter->fetchRow($select, $bind);

        if ($data) {
            $object->setData($data);
            $this->_afterLoad($object);
        }

        return $this;
    }
}
