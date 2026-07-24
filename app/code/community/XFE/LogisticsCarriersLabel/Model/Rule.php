<?php
/**
 * XFE LogisticsCarriersLabel Rule Model
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_Model_Rule extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('xcarrierslabel/rule');
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

        // Delete old condition groups and conditions (CASCADE will handle conditions)
        $oldGroups = Mage::getModel('xcarrierslabel/conditionGroup')->getCollection()
            ->addRuleFilter($this->getId());

        foreach ($oldGroups as $group) {
            $group->delete();
        }

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
            $group = Mage::getModel('xcarrierslabel/conditionGroup');
            $group->setRuleId($this->getId());
            $group->setSortOrder($sortOrder++);
            $group->setAggregator(isset($groupData['aggregator']) ? $groupData['aggregator'] : 'all');
            $group->save();

            if (isset($groupData['conditions']) && is_array($groupData['conditions'])) {
                $condSortOrder = 0;
                foreach ($groupData['conditions'] as $conditionData) {
                    $condition = Mage::getModel('xcarrierslabel/condition');
                    $condition->setGroupId($group->getId());
                    $condition->setSortOrder($condSortOrder++);
                    $condition->setAttribute(isset($conditionData['attribute']) ? $conditionData['attribute'] : '');
                    $condition->setOperator(isset($conditionData['operator']) ? $conditionData['operator'] : '==');
                    $condition->setValue(isset($conditionData['value']) ? $conditionData['value'] : '');
                    $condition->save();
                }
            }
        }

        return $this;
    }

    /**
     * Check if this rule matches given order data
     *
     * @param array $orderData Keys: country_code, zip_code, weight, order_total, package_count, customer_group
     * @return bool
     */
    public function matches(array $orderData)
    {
        $conditionsData = $this->getConditionsData();

        // If no conditions, rule matches by default
        if (empty($conditionsData)) {
            return true;
        }

        // Group logic: OR between groups
        foreach ($conditionsData as $groupData) {
            $aggregator = isset($groupData['aggregator']) ? $groupData['aggregator'] : 'all';
            $conditions = isset($groupData['conditions']) ? $groupData['conditions'] : array();

            if (empty($conditions)) {
                continue;
            }

            if ($this->_matchGroup($conditions, $aggregator, $orderData)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match a group of conditions against order data
     *
     * @param array $conditions
     * @param string $aggregator all=AND, any=OR
     * @param array $orderData
     * @return bool
     */
    protected function _matchGroup(array $conditions, $aggregator, array $orderData)
    {
        if ($aggregator === 'any') {
            foreach ($conditions as $condition) {
                if ($this->_matchCondition($condition, $orderData)) {
                    return true;
                }
            }
            return false;
        } else {
            foreach ($conditions as $condition) {
                if (!$this->_matchCondition($condition, $orderData)) {
                    return false;
                }
            }
            return true;
        }
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

        if (!isset($orderData[$attribute])) {
            return false;
        }

        $subjectValue = $orderData[$attribute];

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

    /**
     * Build tracking URL by replacing placeholders
     *
     * @param string $trackingNumber
     * @param string $zip Optional zip code
     * @return string
     */
    public function buildTrackingUrl($trackingNumber, $zip = '')
    {
        $url = $this->getTrackingUrlTemplate();
        if (!$url) {
            return '';
        }

        $url = str_replace('{tracking_number}', urlencode($trackingNumber), $url);
        $url = str_replace('{zip}', urlencode($zip), $url);

        return $url;
    }
}
