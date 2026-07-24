<?php

class XFE_Carrier_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Get shipping company options for the searchable select element.
     *
     * @param string|null $searchKeyword
     * @return array [['id' => 1, 'name' => 'DHL'], ...]
     */
    public function getShippingCompanyOptions($searchKeyword = null)
    {
        $allOptions = array(
            array('id' => 1, 'name' => 'DHL'),
            array('id' => 2, 'name' => 'UPS'),
            array('id' => 3, 'name' => 'FedEx'),
        );

        if ($searchKeyword === null || $searchKeyword === '') {
            return $allOptions;
        }

        if (is_numeric($searchKeyword)) {
            $id = (int)$searchKeyword;
            foreach ($allOptions as $opt) {
                if ($opt['id'] == $id) {
                    return array($opt);
                }
            }
            return array();
        }

        $keyword = mb_strtolower(trim($searchKeyword));
        $filtered = array();
        foreach ($allOptions as $opt) {
            if (mb_strpos(mb_strtolower($opt['name']), $keyword) !== false) {
                $filtered[] = $opt;
            }
        }
        return $filtered;
    }

    /**
     * Get condition attribute options for carrier rules
     *
     * @return array
     */
    public function getConditionAttributeOptions()
    {
        return array(
            'country_code'   => Mage::helper('xfe_carrier')->__('目的地国家'),
            'city'           => Mage::helper('xfe_carrier')->__('目的城市'),
            'zip_code'       => Mage::helper('xfe_carrier')->__('目的地邮编'),
            'package_count'  => Mage::helper('xfe_carrier')->__('包裹数量'),
            'package_weight' => Mage::helper('xfe_carrier')->__('包裹重量'),
            'length'         => Mage::helper('xfe_carrier')->__('长度'),
            'width'          => Mage::helper('xfe_carrier')->__('宽度'),
            'height'         => Mage::helper('xfe_carrier')->__('高度'),
            'volume'         => Mage::helper('xfe_carrier')->__('体积 (长x宽x高)'),
            'order_amount'   => Mage::helper('xfe_carrier')->__('订单金额'),
            'customer_group' => Mage::helper('xfe_carrier')->__('客户组'),
            'custom'         => Mage::helper('xfe_carrier')->__('自定义...'),
        );
    }

    /**
     * Get operator options
     *
     * @return array
     */
    public function getOperatorOptions()
    {
        return array(
            '=='       => Mage::helper('xfe_carrier')->__('等于'),
            '!='       => Mage::helper('xfe_carrier')->__('不等于'),
            '>'        => Mage::helper('xfe_carrier')->__('大于'),
            '>='       => Mage::helper('xfe_carrier')->__('大于等于'),
            '<'        => Mage::helper('xfe_carrier')->__('小于'),
            '<='       => Mage::helper('xfe_carrier')->__('小于等于'),
            'in'       => Mage::helper('xfe_carrier')->__('在列表中(逗号分隔)'),
            'contains' => Mage::helper('xfe_carrier')->__('包含'),
            'between'  => Mage::helper('xfe_carrier')->__('范围 (x~y)'),
        );
    }

    /**
     * Get numeric operators
     *
     * @return array
     */
    public function getNumericOperators()
    {
        return array(
            '==' => Mage::helper('xfe_carrier')->__('等于'),
            '!=' => Mage::helper('xfe_carrier')->__('不等于'),
            '>'  => Mage::helper('xfe_carrier')->__('大于'),
            '>=' => Mage::helper('xfe_carrier')->__('大于等于'),
            '<'  => Mage::helper('xfe_carrier')->__('小于'),
            '<=' => Mage::helper('xfe_carrier')->__('小于等于'),
            'between' => Mage::helper('xfe_carrier')->__('范围 (x~y)'),
        );
    }

    /**
     * Get string operators
     *
     * @return array
     */
    public function getStringOperators()
    {
        return array(
            '=='       => Mage::helper('xfe_carrier')->__('等于'),
            '!='       => Mage::helper('xfe_carrier')->__('不等于'),
            'in'       => Mage::helper('xfe_carrier')->__('在列表中(逗号分隔)'),
            'contains' => Mage::helper('xfe_carrier')->__('包含'),
        );
    }

    /**
     * Get flat module options for <select> dropdown (excluding "rules" module)
     *
     * @return array [code => label]
     */
    public function getCarrierModuleSelectOptions()
    {
        $groups = $this->getCarrierModules();
        $options = array();
        foreach ($groups as $groupCode => $group) {
            if (isset($group['modules'])) {
                foreach ($group['modules'] as $code => $module) {
                    if ($code === 'rules') {
                        continue; // 跳过规则管理本身
                    }
                    $options[$code] = $module['label'];
                }
            }
        }
        return $options;
    }

    /**
     * Get carrier module definitions from XML configuration
     *
     * Reads carrier_modules.xml and returns a structured array:
     * [
     *   'carrier' => [
     *     'label'   => '承运商',
     *     'modules' => [
     *       'logo'    => ['label'=>'Logo',    'block'=>'...', 'sort_order'=>20, 'show_new'=>0],
     *       'account' => ['label'=>'账号管理', 'block'=>'...', 'sort_order'=>30, 'show_new'=>0],
     *       ...
     *     ]
     *   ]
     * ]
     *
     * @return array
     */
    public function getCarrierModules()
    {
        $cacheKey = 'xfe_carrier_modules';
        $result = Mage::app()->loadCache($cacheKey);
        if ($result) {
            return unserialize($result);
        }

        $config = Mage::getConfig()->loadModulesConfiguration('carrier_modules.xml');
        $modulesNode = $config->getNode('carrier_modules');
        $result = array();

        if ($modulesNode) {
            foreach ($modulesNode->children() as $groupCode => $groupNode) {
                $group = array(
                    'label'   => (string)$groupNode->label,
                    'modules' => array(),
                );
                if ($groupNode->modules) {
                    foreach ($groupNode->modules->children() as $code => $moduleNode) {
                        $group['modules'][$code] = array(
                            'label'      => (string)$moduleNode->label,
                            'block'      => (string)$moduleNode->block,
                            'sort_order' => (int)$moduleNode->sort_order,
                            'show_new'   => (int)$moduleNode->show_new,
                        );
                    }
                    // Sort modules within group by sort_order
                    uasort($group['modules'], function ($a, $b) {
                        return $a['sort_order'] - $b['sort_order'];
                    });
                }
                $result[$groupCode] = $group;
            }
        }

        Mage::app()->saveCache(serialize($result), $cacheKey, array('config'));
        return $result;
    }

    /**
     * Validate that at least min_modules sub-modules have data.
     *
     * Accepts either a Carrier model OR an int (carrier_id). This is the
     * only entry point from the controller and does NOT need the Carrier
     * model to import any Logo/Account/Rule classes.
     *
     * @param XFE_Carrier_Model_Carrier|int $carrier
     * @param array $postData
     * @return bool
     * @throws Mage_Core_Exception
     */
    public function validateCarrierModules($carrier, $postData)
    {
        $config = Mage::getConfig()->loadModulesConfiguration('carrier_modules.xml');
        $minModules = (int)$config->getNode('carrier_module_validation/min_modules', 1);
        $activeCount = 0;

        $carrierId = is_object($carrier) ? (int)$carrier->getId() : (int)$carrier;
        if (!$carrierId) {
            return true; // New carrier: no validation
        }

        // Check each sub-module across all groups
        $modulesNode = $config->getNode('carrier_modules');
        if (!$modulesNode) {
            return true;
        }

        foreach ($modulesNode->children() as $groupNode) {
            if (!$groupNode->modules) {
                continue;
            }
            foreach ($groupNode->modules->children() as $code => $moduleNode) {
                $codeStr = (string)$code;
                switch ($codeStr) {
                    case 'logo':
                        $logoCount = Mage::getModel('xfe_carrier/carrier_logo')->getCollection()
                            ->addFieldToFilter('carrier_id', $carrierId)
                            ->getSize();
                        if ($logoCount > 0) {
                            $activeCount++;
                        }
                        break;

                    case 'account':
                        $accountData = isset($postData['accounts_data']) ? $postData['accounts_data'] : '';
                        $accounts = is_string($accountData) ? Mage::helper('core')->jsonDecode($accountData) : $accountData;
                        if (is_array($accounts) && count($accounts) > 0) {
                            $activeCount++;
                        }
                        break;

                    case 'rules':
                        $ruleData = isset($postData['rules_data']) ? $postData['rules_data'] : '';
                        $rules = is_string($ruleData) ? Mage::helper('core')->jsonDecode($ruleData) : $ruleData;
                        if (is_array($rules) && count($rules) > 0) {
                            $activeCount++;
                        }
                        break;
                }
            }
        }

        if ($activeCount < $minModules) {
            Mage::throwException(
                Mage::helper('xfe_carrier')->__('至少需要 %s 个模块有数据才能保存。', $minModules)
            );
        }

        return true;
    }
}
