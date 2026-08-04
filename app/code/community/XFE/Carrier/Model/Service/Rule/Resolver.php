<?php

/**
 * Rule Resolver
 *
 * "Given a shipment context, which account and which logo should I use
 *  for this carrier?" - that question is what this service answers.
 *
 * Algorithm:
 *   1. Load every active rule of the carrier whose module_code matches the
 *      target type (`account` or `logo`), ordered by priority DESC, then
 *      updated_at DESC, then sort_order ASC, then rule_id ASC. The first
 *      rule whose condition tree matches the context wins.
 *   2. For each rule:
 *      a. decode its condition tree (groups + conditions)
 *      b. ask the pure Evaluator whether the MatchContext matches
 *      c. on match: find the bound target. For accounts the binding
 *         lives on the rule (rule.account_id) - one rule, one account,
 *         but an account may carry many rules. For logos the binding is
 *         still on the logo (logo.rule_id) for now.
 *   3. If nothing matched, optionally fall back to the carrier's default
 *      target. For accounts that is "most recently updated, status=1"
 *      so the admin's last edits to the password / api_key / endpoint
 *      are picked up on the next label print.
 *
 * Dependencies (one-way):
 *   Resolver 鈹€鈻? Evaluator              (pure)
 *   Resolver 鈹€鈻? Carrier_Rule           (read rules + condition tree)
 *   Resolver 鈹€鈻? Carrier_Account        (read bound account)
 *   Resolver 鈹€鈻? Carrier_Logo           (read bound logo)
 *
 * The resolver does NOT call any other service - it goes straight to
 * the data entities it needs.
 */
class XFE_Carrier_Model_Service_Rule_Resolver
{
    const TARGET_ACCOUNT = 'account';
    const TARGET_LOGO    = 'logo';

    /** @var self|null */
    protected static $_instance = null;

    /** @var XFE_Carrier_Model_Service_Rule_Evaluator */
    protected $_evaluator;

    public static function instance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self(
                XFE_Carrier_Model_Service_Rule_Evaluator::instance()
            );
        }
        return self::$_instance;
    }

    public static function setInstance($instance)
    {
        self::$_instance = $instance;
    }

    public function __construct(XFE_Carrier_Model_Service_Rule_Evaluator $evaluator)
    {
        $this->_evaluator = $evaluator;
    }

    /**
     * Resolve both targets in one pass (cheap; one DB query per kind).
     *
     * @param int $carrierId
     * @param XFE_Carrier_Model_Service_Rule_MatchContext $context
     * @param bool $fallback Whether to fall back to defaults when nothing matches
     * @return XFE_Carrier_Model_Service_Rule_MatchResult
     */
    public function resolve($carrierId, XFE_Carrier_Model_Service_Rule_MatchContext $context, $fallback = true)
    {
        $carrierId = (int)$carrierId;
        if (!$carrierId) {
            return new XFE_Carrier_Model_Service_Rule_MatchResult();
        }

        $accountId = $this->resolveTarget($carrierId, self::TARGET_ACCOUNT, $context, $fallback);
        $logoId    = $this->resolveTarget($carrierId, self::TARGET_LOGO,    $context, $fallback);

        return new XFE_Carrier_Model_Service_Rule_MatchResult(array(
            'account_id'      => $accountId['id'],
            'account_rule_id' => $accountId['rule_id'],
            'logo_id'         => $logoId['id'],
            'logo_rule_id'    => $logoId['rule_id'],
            'used_fallback'   => ($accountId['used_fallback'] || $logoId['used_fallback']),
        ));
    }

    /**
     * Resolve only one target. Useful when the caller already knows what
     * they need.
     *
     * @param int $carrierId
     * @param string $targetType  self::TARGET_ACCOUNT or self::TARGET_LOGO
     * @param XFE_Carrier_Model_Service_Rule_MatchContext $context
     * @param bool $fallback
     * @return int|null  matched target id, or null if nothing matched
     */
    public function resolveOne($carrierId, $targetType, XFE_Carrier_Model_Service_Rule_MatchContext $context, $fallback = true)
    {
        $r = $this->resolveTarget($carrierId, $targetType, $context, $fallback);
        return $r['id'];
    }

    /**
     * Internal: returns array('id' => int|null, 'rule_id' => int|null,
     *                       'used_fallback' => bool).
     *
     * @param int $carrierId
     * @param string $targetType
     * @param XFE_Carrier_Model_Service_Rule_MatchContext $context
     * @param bool $fallback
     * @return array
     */
    protected function resolveTarget($carrierId, $targetType, XFE_Carrier_Model_Service_Rule_MatchContext $context, $fallback)
    {
        $rules = $this->_loadActiveRules($carrierId, $targetType);

        foreach ($rules as $rule) {
            $tree = $this->_loadRuleConditionTree((int)$rule->getId());
            if (!$this->_evaluator->evaluate($tree, $context)) {
                continue;
            }
            // First match wins - rules are pre-sorted by priority DESC, updated_at DESC, sort_order ASC.
            $targetId = $this->_findTargetId($targetType, (int)$rule->getId());
            if ($targetId) {
                return array(
                    'id' => $targetId,
                    'rule_id' => (int)$rule->getId(),
                    'used_fallback' => false,
                );
            }
        }

        if (!$fallback) {
            return array('id' => null, 'rule_id' => null, 'used_fallback' => false);
        }

        $default = $this->_findDefaultTarget($targetType, $carrierId);
        return array(
            'id' => $default,
            'rule_id' => null,
            'used_fallback' => $default !== null,
        );
    }

    /**
     * Load active rules of a carrier whose `module_code` matches.
     *
     * @return XFE_Carrier_Model_Resource_Carrier_Rule_Collection
     */
    protected function _loadActiveRules($carrierId, $targetType)
    {
        // Ranking:
        //   1. priority DESC    - the explicit priority knob the admin sets
        //   2. updated_at DESC  - same priority: most recently touched rule wins
        //   3. sort_order ASC   - legacy tie-breaker (lower wins)
        //   4. rule_id ASC      - final stability tie-breaker so identical
        //                         configurations are deterministic
        return Mage::getModel('xfe_carrier/carrier_rule')->getCollection()
            ->addFieldToFilter('carrier_id', (int)$carrierId)
            ->addFieldToFilter('status', 1)
            ->addFieldToFilter('module_code', $targetType)
            ->setOrder('priority', 'DESC')
            ->setOrder('updated_at', 'DESC')
            ->setOrder('sort_order', 'ASC')
            ->setOrder('rule_id', 'ASC');
    }

    /**
     * Decode a rule's condition tree into the array shape the Evaluator
     * accepts. Mirrors RuleService::saveBatch().
     *
     * @param int $ruleId
     * @return array
     */
    protected function _loadRuleConditionTree($ruleId)
    {
        $resource   = Mage::getSingleton('core/resource');
        $read       = $resource->getConnection('core_read');
        $groupTable = $resource->getTableName('xfe_carrier/rule_condition_group');
        $condTable  = $resource->getTableName('xfe_carrier/rule_condition');

        $groups = $read->fetchAll(
            $read->select()->from($groupTable)
                ->where('rule_id = ?', $ruleId)
                ->where('parent_group_id IS NULL')
                ->order('sort_order ASC')
        );

        if (empty($groups)) {
            return array();
        }

        $groupIds = array_map(function ($g) { return (int)$g['group_id']; }, $groups);
        $conditions = $read->fetchAll(
            $read->select()->from($condTable)
                ->where('group_id IN (?)', $groupIds)
                ->order('sort_order ASC')
        );

        $byGroup = array();
        foreach ($conditions as $c) {
            $byGroup[(int)$c['group_id']][] = $c;
        }

        $tree = array();
        foreach ($groups as $g) {
            $tree[] = $this->_buildGroupNode($g, $byGroup, $read, $groupTable);
        }
        return $tree;
    }

    /**
     * @param array $group
     * @param array $byGroup
     * @param Varien_Db_Adapter_Interface $read
     * @param string $groupTable
     * @return array
     */
    protected function _buildGroupNode(array $group, array $byGroup, $read, $groupTable)
    {
        $node = array(
            'aggregator' => isset($group['aggregator']) ? $group['aggregator'] : 'all',
            'conditions' => array(),
        );
        $gid = (int)$group['group_id'];

        $subGroups = $read->fetchAll(
            $read->select()->from($groupTable)
                ->where('parent_group_id = ?', $gid)
                ->order('sort_order ASC')
        );
        $subConds = isset($byGroup[$gid]) ? $byGroup[$gid] : array();

        // Merge by sort_order into a single list, preserving the original
        // interleaving by treating them in sort_order ascending.
        $merged = array();
        foreach ($subConds as $c) {
            $merged[] = array(
                'sort_order' => (int)$c['sort_order'],
                'node'       => array(
                    'attribute' => $c['attribute'],
                    'operator'  => $c['operator'],
                    'value'     => $c['value'],
                ),
            );
        }
        foreach ($subGroups as $sg) {
            $merged[] = array(
                'sort_order' => (int)$sg['sort_order'],
                'node'       => array('type' => 'group', 'data' => $sg),
            );
        }
        usort($merged, function ($a, $b) { return $a['sort_order'] - $b['sort_order']; });

        foreach ($merged as $entry) {
            if (isset($entry['node']['type']) && $entry['node']['type'] === 'group') {
                $node['conditions'][] = $this->_buildGroupNode(
                    $entry['node']['data'], $byGroup, $read, $groupTable
                );
            } else {
                $node['conditions'][] = $entry['node'];
            }
        }
        return $node;
    }

    /**
     * Find the account / logo that is bound to a given rule_id.
     *
     * @param string $targetType
     * @param int    $ruleId
     * @return int|null
     */
    protected function _findTargetId($targetType, $ruleId)
    {
        if ($targetType === self::TARGET_ACCOUNT) {
            // Link moved in 1.0.7: rule.account_id -> xfe_carrier_carrier_account.
            $rule = Mage::getModel('xfe_carrier/carrier_rule')->load($ruleId);
            $accountId = $rule && $rule->getId() ? (int)$rule->getAccountId() : 0;
            if (!$accountId) {
                return null;
            }
            $row = Mage::getModel('xfe_carrier/carrier_account')->load($accountId);
            if (!$row->getId() || (int)$row->getStatus() !== 1) {
                return null;
            }
        } elseif ($targetType === self::TARGET_LOGO) {
            $row = Mage::getModel('xfe_carrier/carrier_logo')->getCollection()
                ->addFieldToFilter('rule_id', $ruleId)
                ->setOrder('sort_order', 'ASC')
                ->getFirstItem();
        } else {
            return null;
        }
        return $row && $row->getId() ? (int)$row->getId() : null;
    }

    /**
     * Default target = most recently updated row, status=1.
     *
     * @param string $targetType
     * @param int $carrierId
     * @return int|null
     */
    protected function _findDefaultTarget($targetType, $carrierId)
    {
        // When no rule matches we want the password / api_key / endpoint etc.
        // of the *most recently updated* row, so that an admin editing a
        // specific account sees their changes picked up on the next label
        // print without having to fight a stale default.
        //
        // Ordering:
        //   1. updated_at DESC  - the most recently saved row wins
        //   2. sort_order ASC   - legacy tie-breaker (lower wins)
        //   3. account_id / logo_id ASC - deterministic final tie-breaker
        if ($targetType === self::TARGET_ACCOUNT) {
            $row = Mage::getModel('xfe_carrier/carrier_account')->getCollection()
                ->addFieldToFilter('carrier_id', $carrierId)
                ->addFieldToFilter('status', 1)
                ->setOrder('updated_at', 'DESC')
                ->setOrder('sort_order', 'ASC')
                ->setOrder('account_id', 'ASC')
                ->getFirstItem();
        } elseif ($targetType === self::TARGET_LOGO) {
            $row = Mage::getModel('xfe_carrier/carrier_logo')->getCollection()
                ->addFieldToFilter('carrier_id', $carrierId)
                ->setOrder('updated_at', 'DESC')
                ->setOrder('sort_order', 'ASC')
                ->setOrder('logo_id', 'ASC')
                ->getFirstItem();
        } else {
            return null;
        }
        return $row && $row->getId() ? (int)$row->getId() : null;
    }
}
