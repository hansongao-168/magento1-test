<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
chdir(__DIR__ . '/../..');
require_once 'app/Mage.php';
try {
    Mage::app('admin');
    echo "Mage::app('admin') OK\n";
    $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
    echo "DB conn OK\n";
    $svc = Mage::getSingleton('xfe_carrier/customAttributeService');
    echo "Service class: " . get_class($svc) . "\n";
    // Try listing existing boolean defs (don't create — just read)
    $coll = $svc->getAllDefs('carrier');
    echo "Existing carrier defs: " . $coll->count() . "\n";
    foreach ($coll as $def) {
        if ($def->getFieldType() !== 'boolean') { continue; }
        echo "  - " . $def->getFieldKey() . " / " . $def->getFieldType() . " / label=" . $def->getLabel() . "\n";
        echo "    options_csv=" . var_export($def->getOptions(), true) . "\n";
        echo "    getBooleanLabels()=" . json_encode($def->getBooleanLabels(), JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (Throwable $e) {
    echo "EXC: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
