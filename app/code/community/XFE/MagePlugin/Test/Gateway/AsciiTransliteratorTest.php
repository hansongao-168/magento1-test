<?php

/**
 * XFE_MagePlugin_Model_Gateway_AsciiTransliterator 测试。
 *
 * 启动策略：
 *  - 默认尝试 require app/Mage.php + Mage::app('admin') 加载 Magento autoloader 与配置，
 *    以便 Mage::getSingleton('xfe_mageplugin/gateway_asciiTransliterator') 能解析 Gateway 实例。
 *  - 若环境不支持（PHP 8+ 下 Magento 1.9 的 __autoload() fatal error），设置
 *    环境变量 MAGE_SKIP_APP=1 跳过 Mage::app()，仅跑单元测试部分。
 *
 * 由于 voku/helper 未安装，测试用 Stub 子类（覆盖 _invokeVoku）绕过真实 voku 调用。
 *
 * 运行方式：
 *   # 默认（依赖 Magento 启动）
 *   php app/code/community/XFE/MagePlugin/Test/Gateway/AsciiTransliteratorTest.php
 *
 *   # 跳过 Magento 启动（PHP 8+ 兼容）
 *   MAGE_SKIP_APP=1 php app/code/community/XFE/MagePlugin/Test/Gateway/AsciiTransliteratorTest.php
 *
 * 退出码 0 = 全部通过；1 = 有失败。
 */

$mageBooted = false;

if (!getenv('MAGE_SKIP_APP')) {
    $mageRoot = realpath(__DIR__ . '/../../../../../../../app/Mage.php');
    if ($mageRoot !== false && file_exists($mageRoot)) {
        require_once $mageRoot;
        try {
            Mage::app('admin');
            $mageBooted = true;
        } catch (\Throwable $e) {
            fwrite(STDERR, '[WARN] Mage::app() failed: ' . $e->getMessage()
                . ' (PHP 8+ 与 Magento 1.9 不兼容时正常)\n');
            $mageBooted = false;
        }
    }
} else {
    fwrite(STDERR, '[INFO] MAGE_SKIP_APP=1，跳过 Magento 启动\n');
}

class AsciiTransliteratorStub extends XFE_MagePlugin_Model_Gateway_AsciiTransliterator
{
    protected $_mockOutput = null;
    protected $_capturedSourceLang = null;

    public function setMockOutput($output) { $this->_mockOutput = (string) $output; }
    public function getCapturedSourceLang() { return $this->_capturedSourceLang; }

    protected function _invokeVoku($text, $sourceLang)
    {
        $this->_capturedSourceLang = $sourceLang;
        return $this->_mockOutput;
    }
}

$failed = 0;
$passed = 0;
$skipped = 0;

function assertEq($expected, $actual, $label)
{
    global $failed, $passed;
    $ok = $expected === $actual;
    if ($ok) {
        $passed++;
        echo '[PASS] ' . $label . PHP_EOL;
    } else {
        $failed++;
        echo '[FAIL] ' . $label
            . ' - expected ' . var_export($expected, true)
            . ', got '      . var_export($actual, true) . PHP_EOL;
    }
}

function assertSkip($label, $reason)
{
    global $skipped;
    $skipped++;
    echo '[SKIP] ' . $label . ' - ' . $reason . PHP_EOL;
}

$stub = new AsciiTransliteratorStub();

// ============================================================
// §9.1 白名单跳过（原样返回，不触发转写）
// ============================================================
assertEq('北京市朝阳区', $stub->transliterate('北京市朝阳区', 'CN'), '白名单 CN 原样返回');
assertEq('東京タワー',    $stub->transliterate('東京タワー',    'JP'), '白名单 JP 原样返回');
assertEq('Москва',        $stub->transliterate('Москва',        'RU'), '白名单 RU 原样返回');
assertEq('القاهرة',       $stub->transliterate('القاهرة',       'EG'), '白名单 EG 原样返回');
assertEq('123 Main St',   $stub->transliterate('123 Main St',   'US'), '白名单 US 纯 ASCII 原样返回');
assertEq('上海市浦东新区', $stub->transliterate('上海市浦东新区', 'CN'), '白名单 CN 复杂文本');
assertEq('Seoul',         $stub->transliterate('Seoul',         'KR'), '白名单 KR 拉丁字符也原样返回');
assertEq(null, $stub->getCapturedSourceLang(), '白名单跳过时不调用 _invokeVoku');

// ============================================================
// §9.2 纯 ASCII 短路
// ============================================================
$stub2 = new AsciiTransliteratorStub();
assertEq('Hello World', $stub2->transliterate('Hello World', 'DE'), '纯 ASCII 字符串短路');
assertEq('12345',       $stub2->transliterate('12345',       'DE'), '纯数字短路');
assertEq('',            $stub2->transliterate('',            'DE'), '空字符串短路');
assertEq(null, $stub2->getCapturedSourceLang(), '纯 ASCII 短路时不调用 _invokeVoku');

// ============================================================
// §9.3 德语转写（mock voku 输出）
// ============================================================
$stub3 = new AsciiTransliteratorStub();
$stub3->setMockOutput('Mueller Strasse 5');
assertEq('Mueller Strasse 5', $stub3->transliterate('Müller Straße 5', 'DE'), 'DE 转写（ä→ae、ß→ss）');
assertEq('de', $stub3->getCapturedSourceLang(), 'DE 使用 de 语言');

$stub3->setMockOutput('Muller.5');
assertEq('Muller.5', $stub3->transliterate('Müller．5', 'DE'), 'DE 输入含全角句点，输出保留 ASCII');

// ============================================================
// §9.4 法语转写
// ============================================================
$stub4 = new AsciiTransliteratorStub();
$stub4->setMockOutput('Cafe Creme');
assertEq('Cafe Creme', $stub4->transliterate('Café Crème', 'FR'), 'FR 转写（é→e）');
assertEq('fr', $stub4->getCapturedSourceLang(), 'FR 使用 fr 语言');

// ============================================================
// §9.5 未命中映射表（fallback "en"）
// ============================================================
$stub5 = new AsciiTransliteratorStub();
$stub5->setMockOutput('Muller');
assertEq('Muller', $stub5->transliterate('Müller', 'SG'), 'SG fallback en');
assertEq('en', $stub5->getCapturedSourceLang(), 'SG fallback en 语言');

$stub5->setMockOutput('Muller');
assertEq('Muller', $stub5->transliterate('Müller', 'XX'), 'XX 未知国家 fallback en');
assertEq('en', $stub5->getCapturedSourceLang(), 'XX 未知国家语言');

$stub5->setMockOutput('Cafe');
assertEq('Cafe', $stub5->transliterate('Café', ''), '空字符串 countryCode fallback');

// ============================================================
// §6.1 大小写不敏感
// ============================================================
$stub6 = new AsciiTransliteratorStub();
$stub6->setMockOutput('Muller');
assertEq('Muller', $stub6->transliterate('Müller', 'de'), '小写 country code');

$stub6->setMockOutput('Muller');
assertEq('Muller', $stub6->transliterate('Müller', 'De'), '混合大小写');

// ============================================================
// §6.2 降级语义：voku 输出含非可打印 ASCII 应被删除
// ============================================================
$stub7 = new AsciiTransliteratorStub();

$stub7->setMockOutput("Cafe\nCreme");
assertEq('CafeCreme', $stub7->transliterate('Café Crème', 'FR'), '删除 \n');

$stub7->setMockOutput("Cafe\x01Creme");
assertEq('CafeCreme', $stub7->transliterate('Café Crème', 'FR'), '删除 \x01');

$stub7->setMockOutput('Muller．5');
assertEq('Muller5', $stub7->transliterate('Müller．5', 'DE'), '全角句点（0xFF0E）被删除');

// ? 是可打印 ASCII（0x3F），不应被删除
$stub7->setMockOutput('Mueller???Strasse');
assertEq('Mueller???Strasse', $stub7->transliterate('Müller Straße', 'DE'), '?（0x3F 可打印）保留，不被删除');

$stub7->setMockOutput("Cafe\x07\x08Creme");
assertEq('CafeCreme', $stub7->transliterate('Café Crème', 'FR'), '删除 BEL/BS（控制字符）');

// ============================================================
// §6.3 Domain 常量类单独验证
// ============================================================
$map = 'XFE_MagePlugin_Domain_Constant_CountryLanguageMap';

assertEq(true,  $map::isNonLatin('CN'),  'Domain CN 在非拉丁白名单');
assertEq(true,  $map::isNonLatin('cn'),  'Domain CN 大小写不敏感');
assertEq(true,  $map::isNonLatin('JP'),  'Domain JP 在非拉丁白名单');
assertEq(true,  $map::isNonLatin('RU'),  'Domain RU 在非拉丁白名单');
assertEq(false, $map::isNonLatin('DE'),  'Domain DE 不在非拉丁白名单');
assertEq(false, $map::isNonLatin('SG'),  'Domain SG 不在非拉丁白名单');
assertEq(false, $map::isNonLatin(''),    'Domain 空字符串不在白名单');
assertEq(false, $map::isNonLatin('XX'),  'Domain XX 未知国家不在白名单');

assertEq('de',     $map::resolve('DE'),     'Domain DE → de');
assertEq('fr',     $map::resolve('FR'),     'Domain FR → fr');
assertEq('it',     $map::resolve('IT'),     'Domain IT → it');
assertEq('es',     $map::resolve('ES'),     'Domain ES → es');
assertEq('fr-CA',  $map::resolve('CA'),     'Domain CA → fr-CA');
assertEq('pt-BR',  $map::resolve('BR'),     'Domain BR → pt-BR');
assertEq('pt-PT',  $map::resolve('PT'),     'Domain PT → pt-PT');
assertEq('en',     $map::resolve('SG'),     'Domain SG → fallback en');
assertEq('en',     $map::resolve(''),       'Domain 空 → fallback en');
assertEq('de',     $map::resolve('de'),     'Domain DE 小写也能解析');

// ============================================================
// §6.4 Mage factory 集成测试（仅在 Mage 启动成功时执行）
// ============================================================
if ($mageBooted) {
    $gateway = Mage::getSingleton('xfe_mageplugin/gateway_asciiTransliterator');
    assertEq(true, $gateway instanceof XFE_MagePlugin_Api_AsciiTransliteratorInterface, 'Mage factory 返回实现 Api 接口的实例');
    assertEq(true, $gateway instanceof XFE_MagePlugin_Model_Gateway_AsciiTransliterator, 'Mage factory 返回正确的 Gateway 类');
} else {
    assertSkip('Mage factory 返回实现 Api 接口的实例', 'Mage 未启动（PHP 8+ 兼容）');
    assertSkip('Mage factory 返回正确的 Gateway 类', 'Mage 未启动（PHP 8+ 兼容）');
}

// ============================================================
// 汇总
// ============================================================
echo PHP_EOL;
echo '========================================' . PHP_EOL;
echo 'Passed: ' . $passed . ', Failed: ' . $failed . ', Skipped: ' . $skipped . PHP_EOL;
echo '========================================' . PHP_EOL;

exit($failed > 0 ? 1 : 0);



