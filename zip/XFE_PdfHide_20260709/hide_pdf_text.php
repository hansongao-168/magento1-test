<?php
/**
 * PDF 文字隐藏工具 - 纯 PHP 版本
 * 
 * 功能: 在 PDF 中用白色方块覆盖指定文字（视觉上隐藏，不影响文字层）
 * 
 * 两种定位模式:
 *   1. 自动检测 - 通过 pdftotext -bbox-layout (poppler-utils) 自动定位文字坐标
 *   2. 手动坐标 - 在 $manualCoordinates 中配置，无需任何外部依赖
 * 
 * 服务器依赖:
 *   - PHP 8.0+
 *   - Magento 1 自带的 Zend_Pdf 库 (lib/Zend/Pdf/)
 *   - pdftotext (poppler-utils) —— 可选，不安装也能用（配置坐标即可）
 * 
 * 用法:
 *   php hide_pdf_text.php                              # 默认隐藏 CAMEL PEAK INTERNATIONAL
 *   php hide_pdf_text.php input.pdf                    # 指定输入文件
 *   php hide_pdf_text.php input.pdf output.pdf          # 指定输入输出
 *   php hide_pdf_text.php input.pdf output.pdf "文字"    # 指定输入输出和要隐藏的文字
 *   php hide_pdf_text.php input.pdf output.pdf "文字" x1 y1 x2 y2  # 手动指定PDF坐标
 *   php hide_pdf_text.php --find-text input.pdf "文字"  # 只查找文字坐标，不处理
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

$projectRoot = __DIR__;
$libPath     = $projectRoot . '/lib';
$wslDistro   = 'Ubuntu-22.04';  // WSL 发行版（仅 Windows 开发环境有用）

$defaultSearchText = 'CAMEL PEAK INTERNATIONAL';
$defaultInputFile  = $projectRoot . '/delivery_proof_QY701757471.pdf';
$defaultOutputFile = $projectRoot . '/delivery_proof_masked.pdf';

// ============================================================
// 手动坐标配置 (无需 pdftotext 的备用方案)
// ============================================================
// 格式: '搜索文字' => ['x1', 'y1', 'x2', 'y2', 'page_height']
// 坐标系统: PDF 标准坐标（原点左下角）
// 可以通过 "--find-text" 模式自动获取坐标值
// ============================================================
$manualCoordinates = [
    'CAMEL PEAK INTERNATIONAL' => [348.8, 693.7, 524.5, 711.1, 842],
    // 可以继续添加更多（使用 --find-text 模式获取坐标）：
    // 'CAMEL PEAK INTERNATIONAL' => [348.8, 693.7, 524.5, 711.1, 842],
    // 'ANOTHER TEXT'             => [100, 200, 300, 220, 842],
];
// ============================================================

// 自动加载器
spl_autoload_register(function ($class) use ($libPath) {
    $file = $libPath . '/' . str_replace('_', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * 检测 pdftotext 是否可用（优先检查系统，再查 WSL）
 */
function isPdftotextAvailable()
{
    // 检查系统 PATH 中是否有 pdftotext
    exec('pdftotext -v 2>&1', $out, $rc);
    if ($rc === 0) return 'system';

    // 检查 WSL 中是否有 pdftotext
    exec('wsl -d Ubuntu-22.04 -- pdftotext -v 2>&1', $out, $rc);
    if ($rc === 0) return 'wsl';

    return false;
}

function toWslPath($path) {
    $p = str_replace('\\', '/', $path);
    if (preg_match('/^([a-zA-Z]):(.+)$/', $p, $m)) {
        return '/mnt/' . strtolower($m[1]) . $m[2];
    }
    return $p;
}

/**
 * 通过 pdftotext 查找文字边界框
 */
function findTextBboxPdftotext($inputFile, $searchText)
{
    $mode = isPdftotextAvailable();
    if (!$mode) return null;

    $wslInput = ($mode === 'wsl') ? toWslPath($inputFile) : escapeshellarg($inputFile);

    if ($mode === 'wsl') {
        $cmd = 'wsl -d Ubuntu-22.04 -- bash -c "pdftotext -bbox-layout ' . escapeshellarg($wslInput) . ' - 2>/dev/null"';
    } else {
        $cmd = 'pdftotext -bbox-layout ' . $wslInput . ' - 2>/dev/null';
    }

    $output = []; $rc = 0;
    exec($cmd, $output, $rc);
    if ($rc !== 0 || empty($output)) return null;

    $xml = implode("\n", $output);

    $pageHeight = null;
    if (preg_match('/<page\s+width="([\d.]+)"\s+height="([\d.]+)"/', $xml, $m)) {
        $pageHeight = (float)$m[2];
    }

    $words = preg_split('/\s+/', trim($searchText));
    if (empty($words)) return null;

    $bboxes = [];
    foreach ($words as $word) {
        $esc = preg_quote(htmlspecialchars($word, ENT_QUOTES, 'UTF-8'), '/');
        if (preg_match('/<word\s+xMin="([\d.]+)"\s+yMin="([\d.]+)"\s+xMax="([\d.]+)"\s+yMax="([\d.]+)">' . $esc . '<\/word>/', $xml, $m)) {
            $bboxes[] = ['xMin' => (float)$m[1], 'yMin' => (float)$m[2], 'xMax' => (float)$m[3], 'yMax' => (float)$m[4]];
        }
    }

    if (empty($bboxes)) return null;

    // 返回 pdftotext 坐标（左上角原点）
    return [
        min(array_column($bboxes, 'xMin')) - 3,
        min(array_column($bboxes, 'yMin')) - 2,
        max(array_column($bboxes, 'xMax')) + 3,
        max(array_column($bboxes, 'yMax')) + 2,
        $pageHeight
    ];
}

/**
 * 转换 pdftotext 坐标（左上角原点）为 PDF 坐标（左下角原点）
 */
function toPdfCoords($tx1, $ty1, $tx2, $ty2, $pageHeight)
{
    return [$tx1, $pageHeight - $ty2, $tx2, $pageHeight - $ty1];
}

/**
 * 获取文字坐标
 * 优先级: 1) 手动坐标配置（无需外部依赖） 2) pdftotext自动检测
 */
function locateText($inputFile, $searchText, &$mode)
{
    // 优先使用手动坐标（零外部依赖，最可靠）
    global $manualCoordinates;
    if (isset($manualCoordinates[$searchText])) {
        $mode = 'manual';
        return $manualCoordinates[$searchText];
    }

    // 手动坐标未配置时，回退到 pdftotext 自动检测
    $bbox = findTextBboxPdftotext($inputFile, $searchText);
    if ($bbox !== null) {
        $mode = 'pdftotext';
        list($tx1, $ty1, $tx2, $ty2, $ph) = $bbox;
        return toPdfCoords($tx1, $ty1, $tx2, $ty2, $ph);
    }

    return null;
}

/**
 * 隐藏 PDF 中的文字
 */
function hideTextInPdf($inputFile, $outputFile, $searchText)
{
    $mode = '';
    $coords = locateText($inputFile, $searchText, $mode);

    if ($coords === null) {
        return [
            'success' => false,
            'message' => "无法定位文字 '{$searchText}'。\n"
                       . "  ※ 安装 pdftotext 自动检测: apt install poppler-utils\n"
                       . "  ※ 或在 \$manualCoordinates 中添加手动坐标\n"
                       . "  ※ 或使用 --find-text 模式先查找坐标"
        ];
    }

    list($px1, $py1, $px2, $py2) = $coords;

    try {
        $pdf = Zend_Pdf::load($inputFile);
    } catch (Exception $e) {
        return ['success' => false, 'message' => '加载 PDF 失败: ' . $e->getMessage()];
    }

    $page = $pdf->pages[0];
    $white = new Zend_Pdf_Color_Rgb(1, 1, 1);
    $page->setFillColor($white);
    $page->setLineColor($white);
    $page->drawRectangle($px1, $py1, $px2, $py2);

    try {
        $pdf->save($outputFile);
    } catch (Exception $e) {
        return ['success' => false, 'message' => '保存失败: ' . $e->getMessage()];
    }

    $size = file_exists($outputFile) ? filesize($outputFile) : 0;
    return ['success' => true, 'message' => "已保存 ({$size} bytes)", 'mode' => $mode, 'coords' => $coords];
}

// ============ 命令行 ============
if (php_sapi_name() === 'cli') {
    $args = $_SERVER['argv'] ?? [];
    array_shift($args);

    // --find-text 模式：只查找坐标
    if (($args[0] ?? '') === '--find-text') {
        $inputFile = $args[1] ?? $defaultInputFile;
        $searchText = $args[2] ?? $defaultSearchText;

        if (!file_exists($inputFile)) {
            file_put_contents('php://stderr', "文件不存在: {$inputFile}\n");
            exit(1);
        }

        $bbox = findTextBboxPdftotext($inputFile, $searchText);
        if ($bbox === null) {
            file_put_contents('php://stderr', "pdftotext 不可用或未找到文字，请手动安装 poppler-utils\n");
            exit(1);
        }

        list($tx1, $ty1, $tx2, $ty2, $ph) = $bbox;
        list($px1, $py1, $px2, $py2) = toPdfCoords($tx1, $ty1, $tx2, $ty2, $ph);

        file_put_contents('php://stderr', "文字: {$searchText}\n");
        file_put_contents('php://stderr', "pdftotext坐标 (左上角原点): ({$tx1}, {$ty1}) - ({$tx2}, {$ty2})\n");
        file_put_contents('php://stderr', "PDF坐标 (左下角原点):     ({$px1}, {$py1}) - ({$px2}, {$py2})\n");
        file_put_contents('php://stderr', "页面高度: {$ph}\n\n");
        file_put_contents('php://stderr', "请将以下配置添加至 \$manualCoordinates:\n");
        file_put_contents('php://stderr', "    '{$searchText}' => [{$px1}, {$py1}, {$px2}, {$py2}, {$ph}],\n");
        exit(0);
    }

    // 正常模式
    $inputFile  = $args[0] ?? $defaultInputFile;
    $outputFile = $args[1] ?? $defaultOutputFile;
    $searchText = $args[2] ?? $defaultSearchText;

    if (!preg_match('/^([a-zA-Z]:|\\\\|\\/)/', $inputFile))
        $inputFile = $projectRoot . '/' . ltrim($inputFile, '\\/');
    if (!preg_match('/^([a-zA-Z]:|\\\\|\\/)/', $outputFile))
        $outputFile = $projectRoot . '/' . ltrim($outputFile, '\\/');

    if (!file_exists($inputFile)) {
        file_put_contents('php://stderr', "❌ 文件不存在: {$inputFile}\n");
        exit(1);
    }

    // 检查坐标来源
    $hasManual = isset($manualCoordinates[$searchText]);
    $hasPdfTool = isPdftotextAvailable();
    file_put_contents('php://stderr', "=== PDF 文字隐藏工具 ===\n");
    file_put_contents('php://stderr', "输入: {$inputFile}\n输出: {$outputFile}\n文字: {$searchText}\n");
    file_put_contents('php://stderr', "坐标来源: " . ($hasManual ? "手动坐标 \$manualCoordinates" : "pdftotext 自动检测") . "\n");
    file_put_contents('php://stderr', "\n");

    $result = hideTextInPdf($inputFile, $outputFile, $searchText);

    if ($result['success']) {
        $size = file_exists($outputFile) ? filesize($outputFile) : 0;
        $modeLabel = isset($result['mode']) ? " (via {$result['mode']})" : '';
        $c = $result['coords'] ?? [];
        file_put_contents('php://stderr', "✅ 成功{$modeLabel} ({$size} bytes)\n");
        if (!empty($c)) {
            file_put_contents('php://stderr', "   坐标: ({$c[0]},{$c[1]})-({$c[2]},{$c[3]})\n");
        }
    } else {
        file_put_contents('php://stderr', "❌ {$result['message']}\n");
        exit(1);
    }
}
