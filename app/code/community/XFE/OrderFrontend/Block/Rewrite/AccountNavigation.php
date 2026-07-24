<?php
/**
 * XFE OrderFrontend - Rewrite customer account navigation to support removeLink
 */
class XFE_OrderFrontend_Block_Rewrite_AccountNavigation extends Mage_Customer_Block_Account_Navigation
{
    /**
     * Remove a link from the navigation by name
     *
     * @param string $name
     * @return Mage_Customer_Block_Account_Navigation
     */
    public function removeLink($name)
    {
        if (isset($this->_links[$name])) {
            unset($this->_links[$name]);
        }
        return $this;
    }
}
