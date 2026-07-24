<?php

/**
 * Evaluator (pure)
 *
 * Single responsibility: given a rule condition tree and a MatchContext,
 * answer TRUE if the tree matches. NO database, NO model imports.
 *
 * Tree shape (decoded by the resolver, identical to RuleService::saveBatch):
 *
 *   array(
 *     array(
 *       'aggregator'  => 'all' | 'any',      // AND / OR
 *       'conditions'  => array(
 *         array(
 *           'attribute' => 'country_code',
 *           'operator'  => '==',
 *           'value'     => 'US',
 *         ),
 *         array(
 *           'type'      => 'group',           // nested group
 *           'aggregator'=> 'any',
 *           'conditions'=> array( ... ),
 *         ),
 *       ),
 *     ),
 *     ...
 *   )
 *
 * Operators supported (string + numeric):
 *   ==   !=   >   >=   <   <=
 *   in   contains
 *   between  (semantics: "x~y", or numeric inclusive range)
 */
class XFE_Carrier_Model_Service_Rule_Evaluator
{
    /** @var self|null */
    protected static $_instance = null;

    public static function instance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public static function setInstance($instance)
    {
        self::$_instance = $instance;
    }

    /**
     * Public entry point.
     *
     * @param array                                                   $groups
     * @param XFE_Carrier_Model_Service_Rule_MatchContext              $context
     * @return bool  TRUE if the (top-level AND of groups) matches
     */
    public function evaluate(array $groups, XFE_Carrier_Model_Service_Rule_MatchContext $context)
    {
        if (empty($groups)) {
            // Empty rule = "always matches" - lets admins build a default branch.
            return true;
        }

        // Top-level behaviour: every group must match (AND). Groups may have
        // their own inner OR (aggregator=any).
        foreach ($groups as $group) {
            if (!$this->_evaluateGroup($group, $context)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array $group
     * @param XFE_Carrier_Model_Service_Rule_MatchContext $context
     * @return bool
     */
    protected function _evaluateGroup(array $group, XFE_Carrier_Model_Service_Rule_MatchContext $context)
    {
        $aggregator = isset($group['aggregator']) ? strtolower($group['aggregator']) : 'all';
        $items      = isset($group['conditions']) && is_array($group['conditions'])
            ? $group['conditions']
            : array();

        if (empty($items)) {
            return true; // empty group = vacuous true
        }

        foreach ($items as $item) {
            $matched = isset($item['type']) && $item['type'] === 'group'
                ? $this->_evaluateGroup($item, $context)
                : $this->_evaluateCondition($item, $context);

            if ($aggregator === 'any' && $matched) {
                return true;
            }
            if ($aggregator === 'all' && !$matched) {
                return false;
            }
        }
        return $aggregator === 'all';
    }

    /**
     * @param array $cond
     * @param XFE_Carrier_Model_Service_Rule_MatchContext $context
     * @return bool
     */
    protected function _evaluateCondition(array $cond, XFE_Carrier_Model_Service_Rule_MatchContext $context)
    {
        $attribute = isset($cond['attribute']) ? (string)$cond['attribute'] : '';
        $operator  = isset($cond['operator'])  ? (string)$cond['operator']  : '==';
        $expected  = isset($cond['value'])     ? $cond['value']             : null;
        $actual    = $context->get($attribute);

        switch ($operator) {
            case '==':
                return $this->_eq($actual, $expected);
            case '!=':
                return !$this->_eq($actual, $expected);
            case '>':
                return $this->_gt($actual, $expected);
            case '>=':
                return $this->_gt($actual, $expected) || $this->_eq($actual, $expected);
            case '<':
                return $this->_lt($actual, $expected);
            case '<=':
                return $this->_lt($actual, $expected) || $this->_eq($actual, $expected);
            case 'in':
                return $this->_in($actual, $expected);
            case 'contains':
                return $this->_contains($actual, $expected);
            case 'between':
                return $this->_between($actual, $expected);
            default:
                return false;
        }
    }

    /**
     * Loose equality: numeric strings compare as numbers, otherwise strings.
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected function _eq($a, $b)
    {
        if ($a === null || $b === null) {
            return false;
        }
        if (is_numeric($a) && is_numeric($b)) {
            return (float)$a === (float)$b;
        }
        return (string)$a === (string)$b;
    }

    /**
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected function _gt($a, $b)
    {
        if (!is_numeric($a) || !is_numeric($b)) {
            return false;
        }
        return (float)$a > (float)$b;
    }

    /**
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected function _lt($a, $b)
    {
        if (!is_numeric($a) || !is_numeric($b)) {
            return false;
        }
        return (float)$a < (float)$b;
    }

    /**
     * `in` operator: expected is a comma-separated list ("US,DE,FR")
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected function _in($a, $b)
    {
        if ($a === null || !is_string($b)) {
            return false;
        }
        foreach (explode(',', $b) as $token) {
            $token = trim($token);
            if ($token === '') continue;
            if ($this->_eq($a, $token)) {
                return true;
            }
        }
        return false;
    }

    /**
     * `contains` operator: substring (string) or contains-element (list).
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected function _contains($a, $b)
    {
        if ($a === null || $b === null) {
            return false;
        }
        if (is_array($a)) {
            return in_array($b, $a, false);
        }
        return mb_stripos((string)$a, (string)$b) !== false;
    }

    /**
     * `between` operator: expected like "5~10" inclusive on both sides.
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected function _between($a, $b)
    {
        if (!is_string($b) || !is_numeric($a)) {
            return false;
        }
        $parts = preg_split('/\s*~\s*/', $b);
        if (count($parts) !== 2) {
            return false;
        }
        $lo = $parts[0];
        $hi = $parts[1];
        return $this->_eq($a, $lo) || $this->_eq($a, $hi) || ($this->_gt($a, $lo) && $this->_lt($a, $hi));
    }
}
