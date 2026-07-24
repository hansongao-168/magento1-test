<?php

/**
 * MatchResult DTO
 *
 * Carries the resolved account + logo a caller should use for a given
 * MatchContext. The fields are intentionally primitive (ids) so the DTO
 * can be safely serialised into JSON / cached.
 *
 * `applied` rule id is non-null only when a rule actually matched. When
 * no rule matched but a fallback was used, the applied rule id stays
 * null and usedFallback() is true.
 */
class XFE_Carrier_Model_Service_Rule_MatchResult
{
    /** @var int|null */
    public $accountId = null;

    /** @var int|null rule id that selected the account, if any */
    public $accountRuleId = null;

    /** @var int|null */
    public $logoId = null;

    /** @var int|null rule id that selected the logo, if any */
    public $logoRuleId = null;

    /** @var bool */
    public $usedFallback = false;

    /**
     * Snake_case keys from the resolver map onto the camelCase properties.
     *
     * @param array<string,mixed> $data
     */
    public function __construct(array $data = array())
    {
        $map = array(
            'account_id'      => 'accountId',
            'account_rule_id' => 'accountRuleId',
            'logo_id'         => 'logoId',
            'logo_rule_id'    => 'logoRuleId',
            'used_fallback'   => 'usedFallback',
        );
        foreach ($data as $key => $value) {
            if (isset($map[$key])) {
                $this->{$map[$key]} = $value;
            }
        }
    }

    /** @return int|null */
    public function getAccountId()       { return $this->accountId; }

    /** @return int|null */
    public function getAccountRuleId()   { return $this->accountRuleId; }

    /** @return int|null */
    public function getLogoId()          { return $this->logoId; }

    /** @return int|null */
    public function getLogoRuleId()      { return $this->logoRuleId; }

    /** @return bool */
    public function usedFallback()       { return (bool)$this->usedFallback; }

    /**
     * Convenience: returns true when nothing matched and nothing fell back.
     * @return bool
     */
    public function isEmpty()
    {
        return $this->accountId === null && $this->logoId === null;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray()
    {
        return array(
            'account_id'      => $this->accountId,
            'account_rule_id' => $this->accountRuleId,
            'logo_id'         => $this->logoId,
            'logo_rule_id'    => $this->logoRuleId,
            'used_fallback'   => $this->usedFallback,
        );
    }
}
