<?php

/**
 * InlineRuleEditorTest
 *
 * Since 1.0.7 the Account Edit form renders a dynamic multi-rule inline
 * editor. JS serialises each block into a JSON array submitted as
 * 'inline_rules_data'; the controller's _materialiseInlineRules() does
 * an insert / update / delete diff against the carrier+account rule pool.
 *
 * Tests replay the controller logic at the DB layer (the controller method
 * is protected):
 *
 *   1. Save account + first inline rule; assert rule.account_id = account.
 *   2. Add a second inline rule; assert both rules live on the account.
 *   3. Delete one rule via the diff (drop from payload); only the
 *      surviving rule remains.
 *   4. Empty-name rows are dropped on the floor; existing empty rows
 *      are deleted (trash-icon behaviour).
 *
 * Run from CLI (PHP 7.3.4+ with Mage bootstrap):
 *   php app/code/community/XFE/Carrier/Test/InlineRuleEditorTest.php
 */
require_once 'D:/www/m1-test.com/app/Mage.php';
Mage::app()->getCacheInstance()->banUse('config');
Mage::getConfig()->reinit();

$failed = 0;

function check($label, $expected, $actual) {
    global $failed;
    $ok = $expected === $actual;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        echo '       expected: ' . var_export($expected, true) . PHP_EOL;
        echo '       got     : ' . var_export($actual, true) . PHP_EOL;
        $failed++;
    }
}

function section($title) {
    echo PHP_EOL . '--- ' . $title . ' ---' . PHP_EOL;
}

function materialiseRules($carrierId, $accountId, array $payload) {
    $write      = Mage::getSingleton('core/resource')->getConnection('core_write');
    $ruleTable  = Mage::getSingleton('core/resource')->getTableName('xfe_carrier/carrier_rule');
    $groupTable = Mage::getSingleton('core/resource')->getTableName('xfe_carrier/rule_condition_group');
    $condTable  = Mage::getSingleton('core/resource')->getTableName('xfe_carrier/rule_condition');

    $existingIds = $write->fetchCol(
        $write->select()->from($ruleTable, 'rule_id')
            ->where('carrier_id = ?', $carrierId)
            ->where('account_id = ?', $accountId)
    );

    $kept = array();
    $inserted = 0; $updated = 0;
    foreach ($payload as $row) {
        $name   = isset($row['name'])    ? trim((string)$row['name'])    : '';
        $ruleId = isset($row['rule_id']) ? (int)$row['rule_id']          : 0;
        if ($name === '') {
            if ($ruleId > 0 && in_array($ruleId, $existingIds, true)) {
                $write->delete($condTable,  array('group_id IN (?)' => $write->select()->from($groupTable, 'group_id')->where('rule_id = ?', $ruleId)));
                $write->delete($groupTable, array('rule_id = ?' => $ruleId));
                $write->delete($ruleTable,  array('rule_id = ?' => $ruleId));
            }
            continue;
        }
        $data = array(
            'carrier_id'           => $carrierId,
            'account_id'           => $accountId,
            'module_code'          => 'account',
            'name'                 => $name,
            'status'               => isset($row['is_active'])            ? (int)$row['is_active']            : 1,
            'is_cancel_on_failure' => isset($row['is_cancel_on_failure']) ? (int)$row['is_cancel_on_failure'] : 0,
            'sort_order'           => isset($row['sort_order'])           ? (int)$row['sort_order']           : 0,
            'updated_at'           => Varien_Date::now(),
        );
        if ($ruleId > 0 && in_array($ruleId, $existingIds, true)) {
            $write->update($ruleTable, $data, array('rule_id = ?' => $ruleId));
            $updated++;
            $kept[] = $ruleId;
        } else {
            $data['created_at'] = Varien_Date::now();
            $write->insert($ruleTable, $data);
            $inserted++;
            $kept[] = (int)$write->lastInsertId($ruleTable);
        }
    }
    $removed = array_diff($existingIds, $kept);
    $deleted = count($removed);
    foreach ($removed as $delId) {
        $delId = (int)$delId;
        $write->delete($condTable,  array('group_id IN (?)' => $write->select()->from($groupTable, 'group_id')->where('rule_id = ?', $delId)));
        $write->delete($groupTable, array('rule_id = ?' => $delId));
        $write->delete($ruleTable,  array('rule_id = ?' => $delId));
    }
    return compact('inserted', 'updated', 'deleted');
}

section('Seeding carrier fixture');
$write      = Mage::getSingleton('core/resource')->getConnection('core_write');
$carrierTbl = Mage::getSingleton('core/resource')->getTableName('xfe_carrier/carrier');
$accountTbl = Mage::getSingleton('core/resource')->getTableName('xfe_carrier/carrier_account');
$ruleTbl    = Mage::getSingleton('core/resource')->getTableName('xfe_carrier/carrier_rule');

$now = Varien_Date::now();
$write->insert($carrierTbl, array(
    'name'       => 'Test Carrier ' . uniqid(),
    'code'       => 'TST-' . strtoupper(substr(md5(uniqid()), 0, 6)),
    'status'     => 1,
    'sort_order' => 0,
    'created_at' => $now,
    'updated_at' => $now,
));
$carrierId = (int)$write->lastInsertId($carrierTbl);

$write->insert($accountTbl, array(
    'carrier_id'   => $carrierId,
    'account_code' => 'ACCT-XFE',
    'account_name' => 'Test Account',
    'status'       => 1,
    'sort_order'   => 10,
    'created_at'   => $now,
    'updated_at'   => $now,
));
$accountId = (int)$write->lastInsertId($accountTbl);

echo "carrier_id=$carrierId, account_id=$accountId" . PHP_EOL;

// 1. First inline rule
section('Add first inline rule');
$stats = materialiseRules($carrierId, $accountId, array(
    array(
        'rule_id'              => 0,
        'name'                 => 'US-East Heavy',
        'is_active'            => 1,
        'is_cancel_on_failure' => 1,
        'sort_order'           => 5,
    ),
));
check('1 row inserted', 1, $stats['inserted']);
check('0 rows updated', 0, $stats['updated']);
check('0 rows deleted', 0, $stats['deleted']);

$firstId = (int)$write->fetchOne(
    $write->select()->from($ruleTbl, 'rule_id')
        ->where('carrier_id = ?', $carrierId)
        ->where('account_id = ?', $accountId)
        ->order('sort_order ASC')->limit(1)
);
check('rule.account_id = account', $accountId, (int)$write->fetchOne(
    "SELECT account_id FROM $ruleTbl WHERE rule_id = ?", $firstId
));
check('rule.carrier_id = carrier', $carrierId, (int)$write->fetchOne(
    "SELECT carrier_id FROM $ruleTbl WHERE rule_id = ?", $firstId
));
check('rule.module_code = account', 'account', (string)$write->fetchOne(
    "SELECT module_code FROM $ruleTbl WHERE rule_id = ?", $firstId
));

// 2. Add second rule
section('Add second inline rule');
$stats = materialiseRules($carrierId, $accountId, array(
    array('rule_id'=>$firstId, 'name'=>'US-East Heavy', 'is_active'=>1, 'is_cancel_on_failure'=>1, 'sort_order'=>5),
    array('rule_id'=>0, 'name'=>'EU Standard', 'is_active'=>1, 'is_cancel_on_failure'=>0, 'sort_order'=>10),
));
check('0 inserted', 0, $stats['inserted']);
check('1 updated',  1, $stats['updated']);
check('0 deleted',  0, $stats['deleted']);

$countForAccount = (int)$write->fetchOne(
    "SELECT COUNT(*) FROM $ruleTbl WHERE carrier_id = ? AND account_id = ?",
    array($carrierId, $accountId)
);
check('account has 2 rules', 2, $countForAccount);

// 3. Delete via diff
section('Delete one rule via payload diff');
$stats = materialiseRules($carrierId, $accountId, array(
    array('rule_id'=>$firstId, 'name'=>'US-East Heavy', 'is_active'=>1, 'is_cancel_on_failure'=>1, 'sort_order'=>5),
));
check('0 inserted', 0, $stats['inserted']);
check('1 updated',  1, $stats['updated']);
check('1 deleted',  1, $stats['deleted']);

$countForAccount = (int)$write->fetchOne(
    "SELECT COUNT(*) FROM $ruleTbl WHERE carrier_id = ? AND account_id = ?",
    array($carrierId, $accountId)
);
check('account has 1 rule', 1, $countForAccount);
check('surviving rule is the US-East one', $firstId, (int)$write->fetchOne(
    "SELECT rule_id FROM $ruleTbl WHERE carrier_id = ? AND account_id = ?",
    array($carrierId, $accountId)
));

// 4. Empty-name rows
section('Empty-name rows are dropped / trash-empties existing');

materialiseRules($carrierId, $accountId, array(
    array('rule_id'=>$firstId, 'name'=>'US-East Heavy', 'is_active'=>1, 'is_cancel_on_failure'=>1, 'sort_order'=>5),
    array('rule_id'=>0, 'name'=>'   ', 'is_active'=>1, 'is_cancel_on_failure'=>0, 'sort_order'=>99),
));
check('still 1 rule (empty ignored)', 1, (int)$write->fetchOne(
    "SELECT COUNT(*) FROM $ruleTbl WHERE carrier_id = ? AND account_id = ?",
    array($carrierId, $accountId)
));

materialiseRules($carrierId, $accountId, array(
    array('rule_id'=>$firstId, 'name'=>'', 'is_active'=>1, 'is_cancel_on_failure'=>1, 'sort_order'=>5),
));
check('0 rules left after trashing the only one', 0, (int)$write->fetchOne(
    "SELECT COUNT(*) FROM $ruleTbl WHERE carrier_id = ? AND account_id = ?",
    array($carrierId, $accountId)
));

section('Cleanup');
$write->delete($ruleTbl,    array('carrier_id = ?' => $carrierId));
$write->delete($accountTbl, array('account_id = ?' => $accountId));
$write->delete($carrierTbl, array('entity_id = ?'  => $carrierId));

echo PHP_EOL;
if ($failed === 0) {
    echo 'ALL INLINE RULE EDITOR TESTS PASSED' . PHP_EOL;
    exit(0);
}
echo $failed . ' INLINE RULE EDITOR TESTS FAILED' . PHP_EOL;
exit(1);
