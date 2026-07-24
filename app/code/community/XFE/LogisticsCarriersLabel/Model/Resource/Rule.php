<?php
/**
 * XFE LogisticsCarriersLabel Rule Resource Model
 *
 * @category   Community
 * @package    XFE_LogisticsCarriersLabel
 */
class XFE_LogisticsCarriersLabel_Model_Resource_Rule extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('xcarrierslabel/rule', 'rule_id');
    }

    /**
     * Load conditions after loading rule
     *
     * @param Mage_Core_Model_Abstract $object
     * @return $this
     */
    protected function _afterLoad(Mage_Core_Model_Abstract $object)
    {
        /** @var XFE_LogisticsCarriersLabel_Model_Rule $object */
        $groups = Mage::getModel('xcarrierslabel/conditionGroup')->getCollection()
            ->addRuleFilter($object->getId())
            ->setSortOrder();

        $conditionsData = array();
        foreach ($groups as $group) {
            $conditions = Mage::getModel('xcarrierslabel/condition')->getCollection()
                ->addGroupFilter($group->getId())
                ->setSortOrder();

            $conditionItems = array();
            foreach ($conditions as $condition) {
                $conditionItems[] = array(
                    'attribute' => $condition->getAttribute(),
                    'operator'  => $condition->getOperator(),
                    'value'     => $condition->getValue(),
                );
            }

            $conditionsData[] = array(
                'aggregator' => $group->getAggregator(),
                'conditions' => $conditionItems,
            );
        }

        $object->setConditionsData($conditionsData);
        return $this;
    }
}
