<?php

/**
 * ResolverIntegrationTest
 *
 * Boots a real Magento 1.9 + MySQL backend on PHP 7.3, inserts a full
 * fixture (carrier + 3 rules + 3 accounts + 3 logos), drives the
 * Resolver service through three realistic contexts, then cleans up.
 *
 * Exits with code 0 on success.
 */

require_once 'D:\\www\\m1-test.com\\app\\Mage.php';
Mage::app()->setCurrentStore(0);

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

// ---------- 0. Self-heal missing columns (test environment only) -----------
$resource = Mage::getSingleton('core/resource');
$write = $resource->getConnection('core_write');
$read  = $resource->getConnection('core_read');

$tableRule    = $resource->getTableName('xfe_carrier/carrier_rule');
$tableAccount = $resource->getTableName('xfe_carrier/carrier_account');
$tableLogo    = $resource->getTableName('xfe_carrier/carrier_logo');

$hasColumn = function ($table, $col) use ($read) {
    $r = $read->fetchAll('SHOW COLUMNS FROM ' . $table . ' LIKE ' . $read->quote($col));
    return count($r) > 0;
};

if (!$hasColumn($tableRule, 'module_code')) {
    $write->addColumn($tableRule, 'module_code', 'VARCHAR(64) NULL DEFAULT NULL AFTER carrier_id');
}
if (!$hasColumn($tableRule, 'description')) {
    $write->addColumn($tableRule, 'description', 'TEXT NULL DEFAULT NULL AFTER name');
}
if (!$hasColumn($tableRule, 'account_id')) {
    $write->addColumn($tableRule, 'account_id', 'INT UNSIGNED NULL DEFAULT NULL AFTER carrier_id');
}
if (!$hasColumn($tableLogo, 'rule_id')) {
    $write->addColumn($tableLogo, 'rule_id', 'INT UNSIGNED NULL DEFAULT NULL AFTER logo_type');
}

// ---------- 1. Set up fixture via raw SQL (cheap, no model overhead) ---------
$tCarrier     = $resource->getTableName('xfe_carrier/carrier');
$tAccount     = $resource->getTableName('xfe_carrier/carrier_account');
$tLogo        = $resource->getTableName('xfe_carrier/carrier_logo');
$tRule        = $resource->getTableName('xfe_carrier/carrier_rule');
$tGroup       = $resource->getTableName('xfe_carrier/rule_condition_group');
$tCondition   = $resource->getTableName('xfe_carrier/rule_condition');
$now          = Varien_Date::now();

$write->insert($tCarrier, array(
    'name' => 'TEST Carrier (ResolverIntegration)', 'code' => 'test_resolver_' . uniqid(),
    'status' => 1, 'sort_order' => 9999, 'created_at' => $now, 'updated_at' => $now,
));
$carrierId = (int)$write->lastInsertId($tCarrier);
echo 'fixture carrier_id = ' . $carrierId . PHP_EOL;

// Rule A: module_code=account, country == US
$write->insert($tRule, array(
    'carrier_id' => $carrierId, 'name' => 'Rule A: US account',
    'module_code' => 'account', 'status' => 1, 'sort_order' => 10,
    'created_at' => $now, 'updated_at' => $now,
));
$ruleAccountUS = (int)$write->lastInsertId($tRule);
// (account_id bound later, after $accountUs is inserted)
// (account_id bound later, after $accountUs is inserted)

// Rule B: module_code=logo, country == US
$write->insert($tRule, array(
    'carrier_id' => $carrierId, 'name' => 'Rule B: US logo',
    'module_code' => 'logo', 'status' => 1, 'sort_order' => 10,
    'created_at' => $now, 'updated_at' => $now,
));
$ruleLogoUS = (int)$write->lastInsertId($tRule);

// Rule C: module_code=account, package_weight > 10
$write->insert($tRule, array(
    'carrier_id' => $carrierId, 'name' => 'Rule C: heavy shipments',
    'module_code' => 'account', 'status' => 1, 'sort_order' => 20,
    'created_at' => $now, 'updated_at' => $now,
));
$ruleAccountHeavy = (int)$write->lastInsertId($tRule);
// (account_id bound later, after $accountHeavy is inserted)
// (account_id bound later, after $accountHeavy is inserted)

// Conditions for Rule A (country=US)
$write->insert($tGroup, array('rule_id' => $ruleAccountUS, 'sort_order' => 0, 'aggregator' => 'all'));
$gAU = (int)$write->lastInsertId($tGroup);
$write->insert($tCondition, array(
    'group_id' => $gAU, 'sort_order' => 0,
    'attribute' => 'country_code', 'operator' => '==', 'value' => 'US',
));

// Conditions for Rule B (country=US)
$write->insert($tGroup, array('rule_id' => $ruleLogoUS, 'sort_order' => 0, 'aggregator' => 'all'));
$gLU = (int)$write->lastInsertId($tGroup);
$write->insert($tCondition, array(
    'group_id' => $gLU, 'sort_order' => 0,
    'attribute' => 'country_code', 'operator' => '==', 'value' => 'US',
));

// Conditions for Rule C (weight > 10)
$write->insert($tGroup, array('rule_id' => $ruleAccountHeavy, 'sort_order' => 0, 'aggregator' => 'all'));
$gCH = (int)$write->lastInsertId($tGroup);
$write->insert($tCondition, array(
    'group_id' => $gCH, 'sort_order' => 0,
    'attribute' => 'package_weight', 'operator' => '>', 'value' => '10',
));

// Accounts: 3 entries. Default-Account is inserted FIRST so its
// sort_order=5 is the lowest, making it the natural fallback target.
$write->insert($tAccount, array(
    'carrier_id' => $carrierId,
    'account_name' => 'Default-Account', 'account_no' => 'DEF-001',
    'status' => 1, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now,
));
$accountDefault = (int)$write->lastInsertId($tAccount);

$write->insert($tAccount, array(
    'carrier_id' => $carrierId,
    'account_name' => 'US-Account', 'account_no' => 'US-001',
    'status' => 1, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now,
));
$accountUs = (int)$write->lastInsertId($tAccount);

$write->insert($tAccount, array(
    'carrier_id' => $carrierId,
    'account_name' => 'Heavy-Account', 'account_no' => 'HV-001',
    'status' => 1, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now,
));
$accountHeavy = (int)$write->lastInsertId($tAccount);

// 1.0.7+: account_id lives on the rule, not on the account row.
$write->update($tRule, array('account_id' => $accountUs),     array('rule_id = ?' => $ruleAccountUS));
$write->update($tRule, array('account_id' => $accountHeavy), array('rule_id = ?' => $ruleAccountHeavy));

// Logos: Default-Logo first (sort_order=5) so it is the fallback winner.
$write->insert($tLogo, array(
    'carrier_id' => $carrierId, 'rule_id' => null,
    'logo_type' => 'main', 'sort_order' => 5, 'label' => 'Default-Logo',
    'path' => 'xfe/carrier/logo/test_default.png',
));
$logoDefault = (int)$write->lastInsertId($tLogo);

$write->insert($tLogo, array(
    'carrier_id' => $carrierId, 'rule_id' => $ruleLogoUS,
    'logo_type' => 'main', 'sort_order' => 10, 'label' => 'US-Logo',
    'path' => 'xfe/carrier/logo/test_us.png',
));
$logoUs = (int)$write->lastInsertId($tLogo);

// Invalidate the registry cache so new rule_modules XML / data is fresh
Mage::app()->getCacheInstance()->clean();

// ---------- 2. Drive the Resolver through 3 contexts ------------------------
$resolver = XFE_Carrier_Model_Service_Registry::ruleResolver();

// Context 1: US, weight 3 - Rule A (account US) and Rule B (logo US) should match
$ctx1 = XFE_Carrier_Model_Service_Rule_MatchContext::create(array(
    'country_code' => 'US', 'package_weight' => 3.0,
));
$r1 = $resolver->resolve($carrierId, $ctx1);
echo PHP_EOL . 'Context: country=US weight=3' . PHP_EOL;
echo '  account_rule_id=' . ($r1->getAccountRuleId() ?: 'null')
   . ' logo_rule_id='    . ($r1->getLogoRuleId()    ?: 'null')
   . ' fallback='         . ($r1->usedFallback() ? 'Y' : 'N')
   . PHP_EOL;
check('context1: matched US account by Rule A', $accountUs, $r1->getAccountId());
check('context1: matched US logo by Rule B',    $logoUs,    $r1->getLogoId());
check('context1: account_rule_id = Rule A',     $ruleAccountUS, $r1->getAccountRuleId());
check('context1: logo_rule_id = Rule B',        $ruleLogoUS, $r1->getLogoRuleId());
check('context1: no fallback',                  false, $r1->usedFallback());

// Context 2: DE, weight 3 - No rule matches -> should fall back to defaults
$ctx2 = XFE_Carrier_Model_Service_Rule_MatchContext::create(array(
    'country_code' => 'DE', 'package_weight' => 3.0,
));
$r2 = $resolver->resolve($carrierId, $ctx2);
echo PHP_EOL . 'Context: country=DE weight=3' . PHP_EOL;
echo '  account_id=' . ($r2->getAccountId() ?: 'null')
   . ' logo_id='    . ($r2->getLogoId() ?: 'null')
   . ' fallback='   . ($r2->usedFallback() ? 'Y' : 'N')
   . PHP_EOL;
check('context2: account falls back to default', $accountDefault, $r2->getAccountId());
check('context2: logo falls back to default',    $logoDefault, $r2->getLogoId());
check('context2: account_rule_id is null',       null, $r2->getAccountRuleId());
check('context2: logo_rule_id is null',          null, $r2->getLogoRuleId());
check('context2: used_fallback true',            true, $r2->usedFallback());

// Context 3: US, weight 15 - Rule C (heavy) should win over Rule A (sort_order: 10 < 20)
$ctx3 = XFE_Carrier_Model_Service_Rule_MatchContext::create(array(
    'country_code' => 'US', 'package_weight' => 15.0,
));
$r3 = $resolver->resolve($carrierId, $ctx3);
echo PHP_EOL . 'Context: country=US weight=15' . PHP_EOL;
echo '  account_id=' . ($r3->getAccountId() ?: 'null')
   . ' rule=' . ($r3->getAccountRuleId() ?: 'null')
   . PHP_EOL;
// Both rule A (country=US) and rule C (weight>10) match. Rule A has lower sort_order, wins.
check('context3: lower sort_order wins', $accountUs, $r3->getAccountId());
check('context3: rule_id = Rule A',       $ruleAccountUS, $r3->getAccountRuleId());

// Context 4: country=DE, weight=15 - only Rule C matches (US rules don't)
$ctx4 = XFE_Carrier_Model_Service_Rule_MatchContext::create(array(
    'country_code' => 'DE', 'package_weight' => 15.0,
));
$r4 = $resolver->resolve($carrierId, $ctx4);
echo PHP_EOL . 'Context: country=DE weight=15' . PHP_EOL;
check('context4: heavy account selected by Rule C', $accountHeavy, $r4->getAccountId());
check('context4: rule_id = Rule C',                  $ruleAccountHeavy, $r4->getAccountRuleId());
// Logo falls back (no logo-rule for DE), so used_fallback must be true.
check('context4: logo fell back to default',         $logoDefault, $r4->getLogoId());
check('context4: used_fallback = true (logo path)',  true,  $r4->usedFallback());

// resolveOne() convenience API
$ctx1Acct = XFE_Carrier_Model_Service_Rule_MatchContext::create(array('country_code' => 'US'));
$onlyAccount = $resolver->resolveOne(
    $carrierId, XFE_Carrier_Model_Service_Rule_Resolver::TARGET_ACCOUNT, $ctx1Acct
);
check('resolveOne: TARGET_ACCOUNT picks the US account', $accountUs, $onlyAccount);

$onlyLogo = $resolver->resolveOne(
    $carrierId, XFE_Carrier_Model_Service_Rule_Resolver::TARGET_LOGO, $ctx1Acct
);
check('resolveOne: TARGET_LOGO picks the US logo', $logoUs, $onlyLogo);

// use_fallback=false - when no match, returns null
$ctxFb = XFE_Carrier_Model_Service_Rule_MatchContext::create(array('country_code' => 'XX'));
$r5 = $resolver->resolve($carrierId, $ctxFb, false);
check('use_fallback=false: null account when no rule matches', null, $r5->getAccountId());
check('use_fallback=false: null logo when no rule matches',    null, $r5->getLogoId());

// ---------- 3. Cleanup fixture ----------------------------------------------
$write->delete($tCondition, array('group_id IN (?)' => array($gAU, $gLU, $gCH)));
$write->delete($tGroup,    array('group_id IN (?)' => array($gAU, $gLU, $gCH)));
$write->delete($tRule,     array('rule_id IN (?)'  => array($ruleAccountUS, $ruleLogoUS, $ruleAccountHeavy)));
$write->delete($tAccount,  array('carrier_id = ?'  => $carrierId));
$write->delete($tLogo,     array('carrier_id = ?'  => $carrierId));
$write->delete($tCarrier,  array('entity_id = ?'   => $carrierId));
echo PHP_EOL . 'fixture cleaned up.' . PHP_EOL;

// ---------- 4. Final report -------------------------------------------------
echo PHP_EOL;
if ($failed === 0) {
    echo 'ALL RESOLVER INTEGRATION TESTS PASSED' . PHP_EOL;
    exit(0);
}
echo $failed . ' RESOLVER INTEGRATION TESTS FAILED' . PHP_EOL;
exit(1);


