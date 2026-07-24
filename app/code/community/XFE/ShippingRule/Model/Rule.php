<?php
/**
 * XFE ShippingRule Rule Model
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Model_Rule extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('xfeshippingrule/rule');
    }

    /**
     * Save conditions after saving rule
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _afterSave()
    {
        parent::_afterSave();

        if (!$this->getId()) {
            return $this;
        }

        // Delete old conditions and condition groups directly (more reliable than ORM collection)
        $resource   = Mage::getSingleton('core/resource');
        $write      = $resource->getConnection('core_write');
        $condTable  = $resource->getTableName('xfeshippingrule/condition');
        $groupTable = $resource->getTableName('xfeshippingrule/condition_group');

        // Delete conditions belongs to this rule's groups first
        $write->delete($condTable, array(
            'group_id IN (?)' => $write->select()
                ->from($groupTable, 'group_id')
                ->where('rule_id = ?', $this->getId())
        ));

        // Then delete all groups (CASCADE handles children via FK)
        $write->delete($groupTable, array(
            'rule_id = ?' => $this->getId()
        ));

        // Save new conditions
        $groupsData = $this->getGroupsData();
        if (is_string($groupsData)) {
            $groupsData = Mage::helper('core')->jsonDecode($groupsData);
        }

        if (!is_array($groupsData) || empty($groupsData)) {
            return $this;
        }

        $sortOrder = 0;
        foreach ($groupsData as $groupData) {
            $this->_saveConditionGroup($groupData, $this->getId(), null, $sortOrder++);
        }

        return $this;
    }

    /**
     * Recursively save a condition group (with conditions and sub-groups)
     * Uses direct SQL INSERT to avoid ORM resource autoloading issues.
     *
     * @param array $groupData
     * @param int $ruleId
     * @param int|null $parentGroupId
     * @param int $sortOrder
     * @return int Saved group ID
     */
    protected function _saveConditionGroup(array $groupData, $ruleId, $parentGroupId = null, $sortOrder = 0)
    {
        $resource = Mage::getSingleton('core/resource');
        $write    = $resource->getConnection('core_write');
        $groupTable = $resource->getTableName('xfeshippingrule/condition_group');
        $condTable  = $resource->getTableName('xfeshippingrule/condition');

        // Insert condition group
        $groupDataRow = array(
            'rule_id'    => $ruleId,
            'sort_order' => $sortOrder,
            'aggregator' => isset($groupData['aggregator']) ? $groupData['aggregator'] : 'all',
        );
        if ($parentGroupId !== null) {
            $groupDataRow['parent_group_id'] = $parentGroupId;
        }
        $write->insert($groupTable, $groupDataRow);
        $groupId = $write->lastInsertId($groupTable);

        if (isset($groupData['conditions']) && is_array($groupData['conditions'])) {
            $condSortOrder = 0;
            $subGroupSortOrder = 0;

            foreach ($groupData['conditions'] as $item) {
                // Check if item is a nested sub-group
                if (isset($item['type']) && $item['type'] === 'group') {
                    $this->_saveConditionGroup($item, $ruleId, $groupId, $subGroupSortOrder++);
                } else {
                    // Simple condition
                    $condRow = array(
                        'group_id'   => $groupId,
                        'sort_order' => $condSortOrder++,
                        'attribute'  => isset($item['attribute']) ? $item['attribute'] : '',
                        'operator'   => isset($item['operator']) ? $item['operator'] : '==',
                        'value'      => isset($item['value']) ? $item['value'] : '',
                    );
                    $write->insert($condTable, $condRow);
                }
            }
        }

        return $groupId;
    }

    /**
     * Calculate shipping fee based on order data
     *
     * Supports stack_mode:
     * - stack_mode=0 (default): return first matching rule immediately
     * - stack_mode=1: accumulate fees from all matching rules
     *
     * @param array $orderData
     *   Keys: user_id, user_email, country_code, city, zip_code,
     *         package_count, package_weight, length, width, height,
     *         order_total (for percent calculation)
     * @return array ['matched' => bool, 'fee' => float, 'rule_id' => int|null]
     */
    public function calculate(array $orderData)
    {
        // Load all enabled rules
        $rules = Mage::getModel('xfeshippingrule/rule')->getCollection()
            ->addActiveFilter();

        $totalFee = 0.0;
        $matchedRules = array();

        foreach ($rules as $rule) {
            // Check package count range
            $packageCount = isset($orderData['package_count']) ? (int)$orderData['package_count'] : 1;
            $packageMin = (int)$rule->getPackageMin();
            $packageMax = $rule->getPackageMax();

            if ($packageCount < $packageMin) {
                continue;
            }
            if ($packageMax !== null && $packageCount > (int)$packageMax) {
                continue;
            }

            // Reload rule with conditions
            $rule->load($rule->getId());
            $conditionsData = $rule->getConditionsData();

            // If no conditions, rule matches by default (package range only)
            $matched = true;
            if (!empty($conditionsData)) {
                $matched = false;
                // Group logic: OR between groups
                foreach ($conditionsData as $groupData) {
                    $aggregator = isset($groupData['aggregator']) ? $groupData['aggregator'] : 'all';
                    $conditions = isset($groupData['conditions']) ? $groupData['conditions'] : array();

                    if (empty($conditions)) {
                        continue;
                    }

                    $groupMatched = $this->_matchGroup($conditions, $aggregator, $orderData);
                    if ($groupMatched) {
                        $matched = true;
                        break;
                    }
                }
            }

            if (!$matched) {
                continue;
            }

            // Calculate shipping fee
            $fee = $this->_calculateFee($rule, $orderData, $packageCount);

            // Check stack mode
            if ($rule->getStackMode()) {
                // Stackable: accumulate fees for all matching rules
                $totalFee += $fee;
                $matchedRules[] = $rule->getId();
            } else {
                // Not stackable: return first match immediately
                return array(
                    'matched' => true,
                    'fee'     => $fee,
                    'rule_id' => $rule->getId(),
                );
            }
        }

        // Return accumulated fees if any stackable rules matched
        if (!empty($matchedRules)) {
            return array(
                'matched' => true,
                'fee'     => $totalFee,
                'rule_id' => $matchedRules[0],
            );
        }

        return array(
            'matched' => false,
            'fee'     => 0.0,
            'rule_id' => null,
        );
    }

    /**
     * Calculate fee for a matched rule
     *
     * @param XFE_ShippingRule_Model_Rule $rule
     * @param array $orderData
     * @param int $packageCount
     * @return float
     */
    protected function _calculateFee($rule, $orderData, $packageCount)
    {
        $shippingFee = (float)$rule->getShippingFee();

        // Billing type: per_order or per_package
        if ($rule->getBillingType() === 'per_package') {
            $shippingFee *= $packageCount;
        }

        // Load type to get calculation type
        $type = Mage::getModel('xfeshippingrule/type')->load($rule->getTypeId());
        $calculationType = $type->getCalculationType();

        switch ($calculationType) {
            case 'percent':
                $orderTotal = isset($orderData['order_total']) ? (float)$orderData['order_total'] : 0;
                $fee = $shippingFee * $orderTotal / 100;
                break;

            case 'weight':
                $packageWeight = isset($orderData['package_weight']) ? (float)$orderData['package_weight'] : 0;
                $fee = $shippingFee * $packageWeight;
                break;

            case 'fixed':
            default:
                $fee = $shippingFee;
                break;
        }

        return round($fee, 4);
    }

    /**
     * Match a group of conditions/items against order data
     * Supports recursive matching for nested sub-groups
     *
     * @param array $conditions
     * @param string $aggregator all=AND, any=OR
     * @param array $orderData
     * @return bool
     */
    protected function _matchGroup(array $conditions, $aggregator, array $orderData)
    {
        if ($aggregator === 'any') {
            // OR logic: any item matches
            foreach ($conditions as $condition) {
                if ($this->_matchGroupItem($condition, $orderData)) {
                    return true;
                }
            }
            return false;
        } else {
            // AND logic: all items must match
            foreach ($conditions as $condition) {
                if (!$this->_matchGroupItem($condition, $orderData)) {
                    return false;
                }
            }
            return true;
        }
    }

    /**
     * Match a single group item (condition or nested sub-group)
     *
     * @param array $item
     * @param array $orderData
     * @return bool
     */
    protected function _matchGroupItem($item, array $orderData)
    {
        // Check if item is a nested sub-group
        if (isset($item['type']) && $item['type'] === 'group') {
            $subAggregator = isset($item['aggregator']) ? $item['aggregator'] : 'all';
            $subConditions = isset($item['conditions']) ? $item['conditions'] : array();
            // Recursive match for nested group
            return $this->_matchGroup($subConditions, $subAggregator, $orderData);
        }

        // Simple condition
        return $this->_matchCondition($item, $orderData);
    }

    /**
     * Match a single condition against order data
     *
     * @param array $condition
     * @param array $orderData
     * @return bool
     */
    protected function _matchCondition(array $condition, array $orderData)
    {
        $attribute = isset($condition['attribute']) ? $condition['attribute'] : '';
        $operator  = isset($condition['operator']) ? $condition['operator'] : '==';
        $value     = isset($condition['value']) ? $condition['value'] : '';

        // Handle volume as auto-calculated value (length * width * height)
        if ($attribute === 'volume') {
            $length = isset($orderData['length']) ? (float)$orderData['length'] : 0;
            $width  = isset($orderData['width']) ? (float)$orderData['width'] : 0;
            $height = isset($orderData['height']) ? (float)$orderData['height'] : 0;
            $subjectValue = $length * $width * $height;
        } else {
            if (!isset($orderData[$attribute])) {
                return false;
            }
            $subjectValue = $orderData[$attribute];
        }

        switch ($operator) {
            case '==':
                return (string)$subjectValue === (string)$value;

            case '!=':
                return (string)$subjectValue !== (string)$value;

            case '>':
                return (float)$subjectValue > (float)$value;

            case '>=':
                return (float)$subjectValue >= (float)$value;

            case '<':
                return (float)$subjectValue < (float)$value;

            case '<=':
                return (float)$subjectValue <= (float)$value;

            case 'in':
                $values = array_map('trim', explode(',', $value));
                return in_array((string)$subjectValue, $values);

            case 'contains':
                return strpos((string)$subjectValue, (string)$value) !== false;

            case 'between':
                $parts = explode('~', $value);
                if (count($parts) !== 2) {
                    return false;
                }
                $min = (float)trim($parts[0]);
                $max = (float)trim($parts[1]);
                $subjectFloat = (float)$subjectValue;
                return $subjectFloat >= $min && $subjectFloat <= $max;

            default:
                return false;
        }
    }
}
