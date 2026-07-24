<?php
/**
 * SocialAccount Collection
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Model_Resource_Social_Account_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeoauth2/social_account');
    }

    /**
     * Filter by customer ID
     *
     * @param int $customerId
     * @return $this
     */
    public function addCustomerFilter($customerId)
    {
        $this->addFieldToFilter('customer_id', $customerId);
        return $this;
    }
}
