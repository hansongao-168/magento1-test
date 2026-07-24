<?php

/**
 * RuleService
 *
 * Owns the lifecycle of rules and their nested condition-group tree.
 * Three tables are touched atomically (best-effort: each write call):
 *   - xfe_carrier_carrier_rule         (the rules themselves)
 *   - xfe_carrier_rule_condition_group (nested groups; tree)
 *   - xfe_carrier_rule_condition       (leaf conditions inside a group)
 *
 * The tree recursion is contained here so the controller does not need to
 * know how a rule is shaped. It hands over arrays; we persist them.
 *
 * Dependencies (one-way):
 *   RuleService ─▶  Carrier_Rule       (DB entity, used for read-by-id)
 *   RuleService ─▶  Rule_ConditionGroup (DB entity)
 *   RuleService ─▶  Rule_Condition      (DB entity)
 *
 * Note: RuleService does NOT depend on Carrier model or other services.
 */
class XFE_Carrier_Model_Service_Rule
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
     * Persist a batch of rules for one carrier. Each rule may carry a
     * nested `groups` array, each group may carry a `conditions` array,
     * each condition may itself be a {type: "group"} sub-group, recursively.
     *
     * The diff between submitted ids and existing ids defines the deletes.
     *
     * @param int   $carrierId
     * @param mixed $payload  array|json-string|null
     * @return int Number of rules submitted (insert + update)
     */
    public function saveBatch($carrierId, $payload)
    {
        $carrierId = (int)$carrierId;
        if (!$carrierId) {
            return 0;
        }
        $rules = $this->_decode($payload);
        if (!is_array($rules) || empty($rules)) {
            $this->deleteAllForCarrier($carrierId);
            return 0;
        }

        $write     = $this->_getWrite();
        $ruleTable = $this->_getTable('rule');
        $groupTable = $this->_getTable('condition_group');
        $condTable  = $this->_getTable('condition');

        $existingIds = $write->fetchCol(
            $write->select()->from($ruleTable, 'rule_id')->where('carrier_id = ?', $carrierId)
        );
        $submittedIds = array();

        foreach ($rules as $ruleData) {
            $ruleId = isset($ruleData['rule_id']) ? (int)$ruleData['rule_id'] : 0;
            $row = array(
                'carrier_id'  => $carrierId,
                // Bind the rule to a specific account (nullable; carrier-level
                // rules created via Rule Management have account_id=NULL).
                'account_id'  => isset($ruleData['account_id']) && $ruleData['account_id'] !== ''
                    ? (int)$ruleData['account_id']
                    : null,
                'module_code' => isset($ruleData['module_code']) ? $ruleData['module_code'] : '',
                'name'        => isset($ruleData['name'])        ? $ruleData['name']        : '',
                'description' => isset($ruleData['description']) ? $ruleData['description'] : '',
                'status'      => isset($ruleData['status'])      ? (int)$ruleData['status'] : 1,
                'sort_order'  => isset($ruleData['sort_order'])  ? (int)$ruleData['sort_order'] : 0,
                'updated_at'  => Varien_Date::now(),
            );

            if ($ruleId > 0 && in_array($ruleId, $existingIds, true)) {
                $write->update($ruleTable, $row, array('rule_id = ?' => $ruleId));
            } else {
                $row['created_at'] = Varien_Date::now();
                $write->insert($ruleTable, $row);
                $ruleId = (int)$write->lastInsertId($ruleTable);
            }
            $submittedIds[] = $ruleId;

            // Wipe and re-write the condition tree for this rule.
            $write->delete($condTable, array(
                'group_id IN (?)' => $write->select()
                    ->from($groupTable, 'group_id')
                    ->where('rule_id = ?', $ruleId),
            ));
            $write->delete($groupTable, array('rule_id = ?' => $ruleId));

            $groups = isset($ruleData['groups']) ? $ruleData['groups'] : array();
            if (is_array($groups) && !empty($groups)) {
                $sortOrder = 0;
                foreach ($groups as $groupData) {
                    $this->_saveConditionGroup($groupData, $ruleId, null, $sortOrder++);
                }
            }
        }

        $removed = array_diff($existingIds, $submittedIds);
        foreach ($removed as $delRuleId) {
            $this->_deleteRuleById((int)$delRuleId);
        }
        return count($submittedIds);
    }

    /**
     * Delete one rule (and its condition tree).
     *
     * @param int $ruleId
     * @return bool
     */
    public function deleteById($ruleId)
    {
        $ruleId = (int)$ruleId;
        if (!$ruleId) {
            return false;
        }
        return $this->_deleteRuleById($ruleId);
    }

    /**
     * Delete every rule that belongs to a carrier (used before carrier delete).
     *
     * @param int $carrierId
     * @return int Number of rules deleted
     */
    public function deleteAllForCarrier($carrierId)
    {
        $carrierId = (int)$carrierId;
        if (!$carrierId) {
            return 0;
        }
        $write     = $this->_getWrite();
        $ruleTable = $this->_getTable('rule');

        $ruleIds = $write->fetchCol(
            $write->select()->from($ruleTable, 'rule_id')->where('carrier_id = ?', $carrierId)
        );
        foreach ($ruleIds as $ruleId) {
            $this->_deleteRuleById((int)$ruleId);
        }
        return count($ruleIds);
    }

    /**
     * @param int|null $parentGroupId
     * @param int      $sortOrder
     */
    protected function _saveConditionGroup(array $groupData, $ruleId, $parentGroupId = null, $sortOrder = 0)
    {
        $write      = $this->_getWrite();
        $groupTable = $this->_getTable('condition_group');
        $condTable  = $this->_getTable('condition');

        $row = array(
            'rule_id'    => $ruleId,
            'sort_order' => $sortOrder,
            'aggregator' => isset($groupData['aggregator']) ? $groupData['aggregator'] : 'all',
        );
        if ($parentGroupId !== null) {
            $row['parent_group_id'] = $parentGroupId;
        }
        $write->insert($groupTable, $row);
        $groupId = (int)$write->lastInsertId($groupTable);

        if (isset($groupData['conditions']) && is_array($groupData['conditions'])) {
            $condSortOrder = 0;
            foreach ($groupData['conditions'] as $item) {
                if (isset($item['type']) && $item['type'] === 'group') {
                    $this->_saveConditionGroup($item, $ruleId, $groupId, $condSortOrder++);
                } else {
                    $write->insert($condTable, array(
                        'group_id'   => $groupId,
                        'sort_order' => $condSortOrder++,
                        'attribute'  => isset($item['attribute']) ? $item['attribute'] : '',
                        'operator'   => isset($item['operator'])  ? $item['operator']  : '==',
                        'value'      => isset($item['value'])     ? $item['value']     : '',
                    ));
                }
            }
        }
        return $groupId;
    }

    /**
     * @param int $ruleId
     * @return bool
     */
    protected function _deleteRuleById($ruleId)
    {
        $write      = $this->_getWrite();
        $ruleTable  = $this->_getTable('rule');
        $groupTable = $this->_getTable('condition_group');
        $condTable  = $this->_getTable('condition');

        // Delete conditions of any group that belongs to this rule.
        $write->delete($condTable, array(
            'group_id IN (?)' => $write->select()
                ->from($groupTable, 'group_id')
                ->where('rule_id = ?', $ruleId),
        ));
        $write->delete($groupTable, array('rule_id = ?' => $ruleId));
        $write->delete($ruleTable, array('rule_id = ?' => $ruleId));
        return true;
    }

    /**
     * Same decode helper as AccountService.
     */
    protected function _decode($payload)
    {
        if (is_array($payload)) {
            return $payload;
        }
        if (is_string($payload) && $payload !== '') {
            $decoded = Mage::helper('core')->jsonDecode($payload);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    /**
     * @return Varien_Db_Adapter_Interface
     */
    protected function _getWrite()
    {
        return Mage::getSingleton('core/resource')->getConnection('core_write');
    }

    /**
     * Resolve the table short name (`rule`, `condition_group`, `condition`)
     * to its real table name without hard-coding.
     *
     * @param string $shortName
     * @return string
     */
    protected function _getTable($shortName)
    {
        switch ($shortName) {
            case 'rule':
                return Mage::getSingleton('core/resource')->getTableName('xfe_carrier/carrier_rule');
            case 'condition_group':
                return Mage::getSingleton('core/resource')->getTableName('xfe_carrier/rule_condition_group');
            case 'condition':
                return Mage::getSingleton('core/resource')->getTableName('xfe_carrier/rule_condition');
        }
        return '';
    }
}
