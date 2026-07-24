<?php

/**
 * EvaluatorTest
 *
 * Run from CLI:
 *   php app/code/community/XFE/Carrier/Test/Rule/EvaluatorTest.php
 *
 * Exits with code 0 when ALL assertions pass, 1 otherwise.
 *
 * Autoloads only the XFE_Carrier_Model_* tree so it can run WITHOUT
 * bootstrapping the full Magento application.
 */

spl_autoload_register(function ($class) {
    if (strpos($class, 'XFE_Carrier_Model_') !== 0) {
        return;
    }
    $parts = explode('_', $class);
    array_shift($parts); // XFE
    array_shift($parts); // Carrier
    array_shift($parts); // Model
    $path = implode('/', $parts) . '.php';
    $candidate = __DIR__ . '/../../Model/' . $path;
    if (file_exists($candidate)) {
        require_once $candidate;
    }
});

$failed = 0;

function assertEq($expected, $actual, $label) {
    global $failed;
    $ok = $expected === $actual;
    $msg = ($ok ? '[PASS] ' : '[FAIL] ') . $label
         . ' - expected ' . var_export($expected, true)
         . ', got ' . var_export($actual, true);
    echo $msg . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

$ev = XFE_Carrier_Model_Service_Rule_Evaluator::instance();

// == operator
$ctx = XFE_Carrier_Model_Service_Rule_MatchContext::create(array('country_code' => 'US'));
assertEq(true,  $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'country_code','operator'=>'==','value'=>'US')))), $ctx),  '== match');
assertEq(false, $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'country_code','operator'=>'==','value'=>'DE')))), $ctx), '== miss');

// numeric coercion
$ctxNum = XFE_Carrier_Model_Service_Rule_MatchContext::create(array('package_count' => '3'));
assertEq(true, $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'package_count','operator'=>'==','value'=>3)))), $ctxNum), '== numeric coercion');

// missing key
$ctxEmpty = XFE_Carrier_Model_Service_Rule_MatchContext::create(array());
assertEq(false, $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'country_code','operator'=>'==','value'=>'US')))), $ctxEmpty), '== missing key');

// !=
assertEq(true,  $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'country_code','operator'=>'!=','value'=>'DE')))), $ctx), '!= different');
assertEq(false, $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'country_code','operator'=>'!=','value'=>'US')))), $ctx), '!= equal');

// > >= < <=
$ctxW = XFE_Carrier_Model_Service_Rule_MatchContext::create(array('package_weight' => 5));
assertEq(true,  $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'package_weight','operator'=>'>','value'=>3)))), $ctxW), '> greater');
assertEq(false, $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'package_weight','operator'=>'>','value'=>5)))), $ctxW), '> equal fails');
assertEq(true,  $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'package_weight','operator'=>'>=','value'=>5)))), $ctxW), '>= equal');
assertEq(true,  $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'package_weight','operator'=>'<','value'=>10)))), $ctxW), '< less');
assertEq(true,  $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'package_weight','operator'=>'<=','value'=>5)))), $ctxW), '<= equal');

// > on strings
$ctxStr = XFE_Carrier_Model_Service_Rule_MatchContext::create(array('city' => 'NY'));
assertEq(false, $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'city','operator'=>'>','value'=>'A')))), $ctxStr), '> on strings returns false');

// in
assertEq(true,  $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'country_code','operator'=>'in','value'=>'US,CA,MX')))), $ctx), 'in hit');
assertEq(false, $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'country_code','operator'=>'in','value'=>'DE,FR,UK')))), $ctx), 'in miss');
assertEq(true,  $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'country_code','operator'=>'in','value'=>'  US ,  DE  ')))), $ctx), 'in whitespace tolerant');

// contains
$ctxC = XFE_Carrier_Model_Service_Rule_MatchContext::create(array('city' => 'New York'));
assertEq(true,  $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'city','operator'=>'contains','value'=>'York')))), $ctxC), 'contains substring');
assertEq(true,  $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'city','operator'=>'contains','value'=>'york')))), $ctxC), 'contains case-insensitive');
assertEq(false, $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'city','operator'=>'contains','value'=>'Boston')))), $ctxC), 'contains miss');

// between
assertEq(true,  $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'package_weight','operator'=>'between','value'=>'1~10')))), $ctxW), 'between inside');
assertEq(false, $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'package_weight','operator'=>'between','value'=>'10~20')))), $ctxW), 'between outside');
assertEq(true,  $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array(array('attribute'=>'package_weight','operator'=>'between','value'=>'5~10')))), $ctxW), 'between lower-edge inclusive');

// nested groups: country=US AND (weight<1 OR weight>5)
$ruleNested = array(array(
    'aggregator' => 'all',
    'conditions' => array(
        array('attribute' => 'country_code', 'operator' => '==', 'value' => 'US'),
        array(
            'type' => 'group',
            'aggregator' => 'any',
            'conditions' => array(
                array('attribute' => 'package_weight', 'operator' => '<', 'value' => 1),
                array('attribute' => 'package_weight', 'operator' => '>', 'value' => 5),
            ),
        ),
    ),
));
$ctxUS3 = XFE_Carrier_Model_Service_Rule_MatchContext::create(array('country_code' => 'US', 'package_weight' => 3));
$ctxDE3 = XFE_Carrier_Model_Service_Rule_MatchContext::create(array('country_code' => 'DE', 'package_weight' => 3));
$ctxUS7 = XFE_Carrier_Model_Service_Rule_MatchContext::create(array('country_code' => 'US', 'package_weight' => 7));
assertEq(false, $ev->evaluate($ruleNested, $ctxUS3), 'nested: US+weight=3 miss');
assertEq(false, $ev->evaluate($ruleNested, $ctxDE3), 'nested: DE short-circuits');
assertEq(true,  $ev->evaluate($ruleNested, $ctxUS7), 'nested: US+weight=7 hits inner-OR');

// multi-group top-level AND
$multiGroup = array(
    array('aggregator'=>'all','conditions'=>array(array('attribute'=>'country_code','operator'=>'==','value'=>'US'))),
    array('aggregator'=>'all','conditions'=>array(array('attribute'=>'zip_code','operator'=>'in','value'=>'10001,10002'))),
);
assertEq(true,  $ev->evaluate($multiGroup, XFE_Carrier_Model_Service_Rule_MatchContext::create(array('country_code'=>'US','zip_code'=>'10001'))), 'multi-group AND hit');
assertEq(false, $ev->evaluate($multiGroup, XFE_Carrier_Model_Service_Rule_MatchContext::create(array('country_code'=>'US','zip_code'=>'99999'))), 'multi-group AND miss');

// empty
assertEq(true, $ev->evaluate(array(), $ctx), 'empty tree = true');
assertEq(true, $ev->evaluate(array(array('aggregator'=>'all','conditions'=>array())), $ctx), 'empty group = true');

echo PHP_EOL;
if ($failed === 0) {
    echo 'ALL EVALUATOR TESTS PASSED' . PHP_EOL;
    exit(0);
}
echo $failed . ' EVALUATOR TESTS FAILED' . PHP_EOL;
exit(1);
