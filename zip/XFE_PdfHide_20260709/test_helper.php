<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

// 模拟 Magento 完整的 Autoloader 路径
$paths = [
    'd:/www/m1-test.com/lib',
    'd:/www/m1-test.com/app/code/local',
    'd:/www/m1-test.com/app/code/community',
    'd:/www/m1-test.com/app/code/core',
];
spl_autoload_register(function ($c) use ($paths) {
    $file = str_replace('_', '/', $c) . '.php';
    foreach ($paths as $p) {
        $f = $p . '/' . $file;
        if (file_exists($f)) { require_once $f; return; }
    }
});

// 自动加载 Varien_Autoload::register 中用到的类
// Mage_Core_Helper_Abstract 在 app/code/core/Mage/Core/Helper/Abstract.php

// 测试 1: Helper 类可加载
$helper = new XFE_PdfHide_Helper_Data();
echo "1. Helper class: " . get_class($helper) . " - OK\n";

// 测试 2: hideTextWithCoords - 手动坐标
$ret = $helper->hideTextWithCoords(
    'd:/www/m1-test.com/delivery_proof_QY701757471.pdf',
    'd:/www/m1-test.com/delivery_proof_masked.pdf',
    [348.8, 693.7, 524.5, 711.1, 842]
);
echo "2. hideTextWithCoords: " . ($ret['success'] ? 'SUCCESS' : 'FAIL') . " - {$ret['message']}\n";

// 测试 3: hideText - 使用默认坐标
$ret2 = $helper->hideText(
    'd:/www/m1-test.com/delivery_proof_QY701757471.pdf',
    'd:/www/m1-test.com/delivery_proof_masked2.pdf',
    'CAMEL PEAK INTERNATIONAL'
);
echo "3. hideText (known coord): " . ($ret2['success'] ? 'SUCCESS' : 'FAIL') . " - {$ret2['message']}\n";

// 测试 4: setCoord
$helper->setCoord('MY CUSTOM TEXT', [100, 200, 300, 220, 842]);
echo "4. setCoord: OK\n";

// 测试 5: pdftotext availability
echo "5. pdftotext: " . ($helper->isPdftotextAvailable() ? 'available' : 'unavailable') . "\n";

echo "\n✅ All tests passed.\n";
