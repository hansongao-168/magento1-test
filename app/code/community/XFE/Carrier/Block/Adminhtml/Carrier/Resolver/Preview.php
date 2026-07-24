<?php

/**
 * Demo consumer block
 *
 * Renders a small "what would the resolver pick?" preview inside the
 * carrier edit page. Calls Registry::ruleResolver() with a sample
 * context (configurable via setSampleContext()) and shows the matched
 * account + logo with the rule name that fired (or "fallback" if no
 * rule matched).
 *
 * Single responsibility: PRESENT the resolver result. It does NOT
 * decide anything - that is the resolver's job.
 *
 * Dependencies (one-way):
 *   Preview block -> Service\Registry -> Rule\Resolver -> (data models)
 *
 * No service depends on this block.
 */
class XFE_Carrier_Block_Adminhtml_Carrier_Resolver_Preview
    extends Mage_Adminhtml_Block_Widget
{
    /**
     * @var array
     */
    protected $_sampleContext = array(
        'country_code'   => 'US',
        'city'           => 'New York',
        'zip_code'       => '10001',
        'package_weight' => 3.5,
    );

    /**
     * Set the context that will be used for the preview.
     *
     * @param array $ctx
     * @return $this
     */
    public function setSampleContext(array $ctx)
    {
        $this->_sampleContext = $ctx;
        return $this;
    }

    /**
     * @return array
     */
    public function getSampleContext()
    {
        return $this->_sampleContext;
    }

    /**
     * Run the resolver and return the MatchResult DTO.
     * Returns null when the page is rendering a brand-new carrier
     * (no id yet).
     *
     * @return XFE_Carrier_Model_Service_Rule_MatchResult|null
     */
    public function getMatchResult()
    {
        $carrier = Mage::registry('xfe_carrier_data');
        if (!$carrier || !$carrier->getId()) {
            return null;
        }

        $context = XFE_Carrier_Model_Service_Rule_MatchContext::create(
            $this->getSampleContext()
        );

        return XFE_Carrier_Model_Service_Registry::ruleResolver()
            ->resolve((int)$carrier->getId(), $context, true);
    }

    /**
     * @return XFE_Carrier_Model_Carrier_Account|null
     */
    public function getResolvedAccount()
    {
        $r = $this->getMatchResult();
        if (!$r || !$r->getAccountId()) {
            return null;
        }
        $a = Mage::getModel('xfe_carrier/carrier_account')->load($r->getAccountId());
        return $a->getId() ? $a : null;
    }

    /**
     * @return XFE_Carrier_Model_Carrier_Logo|null
     */
    public function getResolvedLogo()
    {
        $r = $this->getMatchResult();
        if (!$r || !$r->getLogoId()) {
            return null;
        }
        $l = Mage::getModel('xfe_carrier/carrier_logo')->load($r->getLogoId());
        return $l->getId() ? $l : null;
    }

    /**
     * @return XFE_Carrier_Model_Carrier_Rule|null
     */
    public function getAccountRule()
    {
        $r = $this->getMatchResult();
        if (!$r || !$r->getAccountRuleId()) {
            return null;
        }
        $rule = Mage::getModel('xfe_carrier/carrier_rule')->load($r->getAccountRuleId());
        return $rule->getId() ? $rule : null;
    }

    /**
     * @return XFE_Carrier_Model_Carrier_Rule|null
     */
    public function getLogoRule()
    {
        $r = $this->getMatchResult();
        if (!$r || !$r->getLogoRuleId()) {
            return null;
        }
        $rule = Mage::getModel('xfe_carrier/carrier_rule')->load($r->getLogoRuleId());
        return $rule->getId() ? $rule : null;
    }

    /**
     * Public URL helper for the resolved logo (if any).
     *
     * @return string|null
     */
    public function getLogoUrl()
    {
        $logo = $this->getResolvedLogo();
        if (!$logo) {
            return null;
        }
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . $logo->getPath();
    }

    /**
     * Render the preview block as HTML.
     *
     * @return string
     */
    protected function _toHtml()
    {
        $helper = Mage::helper('xfe_carrier');
        $result = $this->getMatchResult();

        if (!$result) {
            return '<div class="entry-edit"><div class="fieldset">'
                . '<p style="color:#999;">' . $helper->__('Save the carrier first to preview resolver output.') . '</p>'
                . '</div></div>';
        }

        $account      = $this->getResolvedAccount();
        $logo         = $this->getResolvedLogo();
        $accountRule  = $this->getAccountRule();
        $logoRule     = $this->getLogoRule();

        $html  = '<div class="entry-edit">';
        $html .= '<div class="entry-edit-head"><h4 class="icon-head head-edit-form fieldset-legend">'
              . $helper->__('Resolver preview') . '</h4></div>';
        $html .= '<div class="fieldset">';

        $html .= '<p style="margin:0 0 8px 0;color:#666;">'
              . '<strong>' . $helper->__('Sample context') . ':</strong> '
              . htmlspecialchars(json_encode($this->getSampleContext()))
              . '</p>';

        // Account row
        if ($account) {
            $ruleTag = $accountRule
                ? sprintf(' <em style="color:#999;">(rule: %s)</em>', htmlspecialchars($accountRule->getName()))
                : ' <em style="color:#999;">' . ($result->usedFallback() ? '(fallback)' : '(no rule)') . '</em>';
            $html .= '<p style="margin:4px 0;">'
                  . '<strong>' . $helper->__('Account') . ':</strong> '
                  . htmlspecialchars($account->getAccountName())
                  . ' (#' . (int)$account->getId() . ')'
                  . $ruleTag
                  . '</p>';
        } else {
            $html .= '<p style="margin:4px 0;color:#999;">' . $helper->__('No account resolved.') . '</p>';
        }

        // Logo row
        if ($logo) {
            $logoUrl = $this->getLogoUrl();
            $ruleTag = $logoRule
                ? sprintf(' <em style="color:#999;">(rule: %s)</em>', htmlspecialchars($logoRule->getName()))
                : ' <em style="color:#999;">' . ($result->usedFallback() ? '(fallback)' : '(no rule)') . '</em>';
            $html .= '<p style="margin:4px 0;">'
                  . '<strong>' . $helper->__('Logo') . ':</strong> ';
            if ($logoUrl) {
                $html .= '<img src="' . htmlspecialchars($logoUrl) . '" alt="logo" '
                      . 'style="max-height:36px;vertical-align:middle;margin-right:8px;" />';
            }
            $html .= htmlspecialchars($logo->getLogoType() ?: 'main')
                  . ' (#' . (int)$logo->getId() . ')'
                  . $ruleTag
                  . '</p>';
        } else {
            $html .= '<p style="margin:4px 0;color:#999;">' . $helper->__('No logo resolved.') . '</p>';
        }

        $html .= '</div></div>';
        return $html;
    }
}
