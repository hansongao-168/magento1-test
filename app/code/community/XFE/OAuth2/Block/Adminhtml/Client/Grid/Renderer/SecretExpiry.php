<?php
/**
 * Admin Client Grid - "Secret Expires" column renderer
 *
 * Renders the client_secret_expires_at value with a colour that reflects
 * how soon the secret will stop being valid:
 *
 *   - green  (> 30 days remaining)
 *   - yellow (0..30 days remaining)
 *   - red    (expired)
 *   - grey   (NULL -> legacy client pre-1.0.3)
 *
 * Per ADR 0007 the storage layer does NOT refuse token issuance for an
 * expired secret, so this renderer is purely a visual hint to the
 * operator. The number of days remaining is included next to the date so
 * the operator can act without having to hover over a tooltip.
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Block_Adminhtml_Client_Grid_Renderer_SecretExpiry
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /** Days remaining at or below this threshold switches to yellow. */
    const YELLOW_THRESHOLD_DAYS = 30;

    /**
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $helper = Mage::helper('xfeoauth2');

        // The grid passes the raw row (Mage_Core_Model_Abstract of the
        // Client). We delegate the heavy lifting to the helper so this
        // class stays a thin view layer.
        if (!$row instanceof XFE_OAuth2_Model_Client) {
            // Defensive: should never happen given the Grid collection,
            // but if a future filter swaps the row class, fall back to a
            // plain text dump rather than throwing in a renderer.
            $value = $row->getData('client_secret_expires_at');
            return $value === null ? '' : (string)$value;
        }

        $expiresAt = $row->getClientSecretExpiresAt();
        $daysLeft  = $helper->getSecretDaysUntilExpiry($row);

        // Legacy / unknown rows: grey "Unknown" badge so the operator
        // knows the row has no TTL metadata yet.
        if ($expiresAt === null || $expiresAt === '' || $daysLeft === null) {
            return sprintf(
                '<span style="color:#999;">%s</span>',
                $this->_escape($helper->__('Unknown'))
            );
        }

        // Pick a colour. We avoid inline event handlers; the colour is a
        // pure visual cue and clicking the row opens the edit page via the
        // explicit Edit button.
        if ($daysLeft < 0) {
            $color = '#c0392b';     // red, expired
            $label = $helper->__('Expired');
        } elseif ($daysLeft <= self::YELLOW_THRESHOLD_DAYS) {
            $color = '#d68910';     // amber/yellow, soon
            $label = $helper->__('%d day(s) left', $daysLeft);
        } else {
            $color = '#1e8449';     // green, safe
            $label = $helper->__('%d day(s) left', $daysLeft);
        }

        return sprintf(
            '<span style="color:%s;font-weight:bold;">%s</span><br>'
            . '<span style="color:#666;font-size:11px;">%s</span>',
            $color,
            $this->_escape($label),
            $this->_escape((string)$expiresAt)
        );
    }

    /**
     * Thin escape wrapper so render() reads as a single sprintf per branch.
     *
     * @param string $value
     * @return string
     */
    protected function _escape($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
