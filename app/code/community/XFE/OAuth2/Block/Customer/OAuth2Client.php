<?php
/**
 * OAuth2 Customer Account Block
 *
 * Provides data for the storefront "My API Clients" pages: list, new, and the
 * one-time secret success view.
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Block_Customer_OAuth2Client extends Mage_Core_Block_Template
{
    /**
     * @var string
     */
    protected $_template;

    /**
     * Get the current logged-in customer id (always defined - preDispatch guarantees it)
     *
     * @return int
     */
    public function getCustomerId()
    {
        return (int)Mage::getSingleton('customer/session')->getCustomerId();
    }

    /**
     * Get the client's own OAuth2 clients (filtered by user_id == customer id)
     *
     * @return XFE_OAuth2_Model_Resource_Client_Collection
     */
    public function getClients()
    {
        $collection = Mage::getResourceModel('xfeoauth2/client_collection');
        $collection->addFieldToFilter('user_id', $this->getCustomerId())
            ->setOrder('created_at', 'DESC');
        return $collection;
    }

    /**
     * URL helper - new form
     *
     * @return string
     */
    public function getNewUrl()
    {
        return Mage::getUrl('oauth2/client/new');
    }

    /**
     * URL helper - save form posts here
     *
     * @return string
     */
    public function getSaveUrl()
    {
        return Mage::getUrl('oauth2/client/save');
    }

    /**
     * URL helper - delete action
     *
     * @param string $clientId
     * @return string
     */
    public function getDeleteUrl($clientId)
    {
        return Mage::getUrl('oauth2/client/delete', array('id' => $clientId));
    }

    /**
     * URL helper - back to list
     *
     * @return string
     */
    public function getListUrl()
    {
        return Mage::getUrl('oauth2/client/index');
    }

    /**
     * URL helper - reveal (view) client secret, returns JSON
     *
     * @param string $clientId
     * @return string
     */
    public function getRevealUrl($clientId)
    {
        return Mage::getUrl('oauth2/client/reveal', array('id' => $clientId));
    }

    /**
     * Read the one-time secret for a client (only valid in the same request
     * after creation). Returns null after the secret has been read.
     *
     * @param string $clientId
     * @return string|null
     */
    public function getNewSecret($clientId)
    {
        $key = 'xfeoauth2_new_secret_' . $clientId;
        $core = Mage::getSingleton('core/session');
        $secret = $core->getData($key);
        if ($secret) {
            // consume so it is only shown once
            $core->unsetData($key);
        }
        return $secret;
    }

    /**
     * True if the controller is in "show this newly created client + its secret" mode
     *
     * @return string|null
     */
    public function getShowSecretClientId()
    {
        $id = $this->getRequest()->getParam('show_secret');
        return $id ? $id : null;
    }

    /**
     * Format a client secret for display (32-char hex, no masking - shown once)
     *
     * @param string $secret
     * @return string
     */
    public function formatSecret($secret)
    {
        return trim((string)$secret);
    }
}