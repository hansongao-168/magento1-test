<?php
/**
 * XFE_PdfHide Helper
 *
 * 功能: 在 PDF 中用白色方块覆盖指定文字（视觉上隐藏，不影响文字层）
 *
 * 用法:
 *   // 方式一：使用自动坐标检测（需服务器安装 pdftotext）
 *   Mage::helper('xfe_pdfhide')->hideText('/tmp/input.pdf', '/tmp/output.pdf', 'CAMEL PEAK INTERNATIONAL');
 *
 *   // 方式二：使用手动坐标（推荐，零外部依赖）
 *   Mage::helper('xfe_pdfhide')->hideTextWithCoords('/tmp/input.pdf', '/tmp/output.pdf',
 *       [348.8, 693.7, 524.5, 711.1, 842]);  // [x1, y1, x2, y2, pageHeight]
 *
 *   // 方式三：先设置坐标缓存，再按文字隐藏
 *   Mage::helper('xfe_pdfhide')->setCoord('MY TEXT', [100, 200, 300, 220, 842]);
 *   Mage::helper('xfe_pdfhide')->hideText('/tmp/input.pdf', '/tmp/output.pdf', 'MY TEXT');
 *
 * 坐标获取（在有 pdftotext 的开发机上运行）：
 *   php hide_pdf_text.php --find-text input.pdf "要定位的文字"
 */
class XFE_PdfHide_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * 手动坐标缓存
     * @var array
     */
    protected $_knownCoords = [
        'CAMEL PEAK INTERNATIONAL' => [348.8, 693.7, 524.5, 711.1, 842],
    ];

    /**
     * 注册手动坐标（用于已知 PDF 模板）
     *
     * @param string $text    PDF 中的文字
     * @param array  $coords  坐标数组 [x1, y1, x2, y2, pageHeight]（PDF 左下角坐标系）
     * @return $this
     */
    public function setCoord($text, array $coords)
    {
        $this->_knownCoords[$text] = $coords;
        return $this;
    }

    /**
     * 批量注册坐标
     *
     * @param array $coords  ['文字' => [x1, y1, x2, y2, ph], ...]
     * @return $this
     */
    public function setCoords(array $coords)
    {
        $this->_knownCoords = array_merge($this->_knownCoords, $coords);
        return $this;
    }

    /**
     * 隐藏 PDF 中的指定文字（自动检测坐标 / 手动坐标）
     *
     * @param string $inputFile  输入 PDF 路径
     * @param string $outputFile 输出 PDF 路径
     * @param string $searchText 要隐藏的文字
     * @return array ['success' => bool, 'message' => string, 'coords' => array|null]
     */
    public function hideText($inputFile, $outputFile, $searchText)
    {
        // 1. 定位坐标
        $coords = $this->_locateText($inputFile, $searchText);
        if (!$coords) {
            return [
                'success' => false,
                'message' => "无法定位文字 '{$searchText}'，请先用 setCoord() 注册坐标",
            ];
        }

        // 2. 处理 PDF
        return $this->_process($inputFile, $outputFile, $coords);
    }

    /**
     * 使用手动坐标隐藏文字（不依赖 pdftotext）
     *
     * @param string $inputFile  输入 PDF 路径
     * @param string $outputFile 输出 PDF 路径
     * @param array  $coords     [x1, y1, x2, y2, pageHeight]
     * @return array ['success' => bool, 'message' => string]
     */
    public function hideTextWithCoords($inputFile, $outputFile, array $coords)
    {
        return $this->_process($inputFile, $outputFile, $coords);
    }

    /**
     * 检测 pdftotext 是否可用
     *
     * @return bool
     */
    public function isPdftotextAvailable()
    {
        exec('pdftotext -v 2>&1', $out, $rc);
        return $rc === 0;
    }

    /**
     * 通过 pdftotext 查找文字坐标（仅开发环境使用）
     *
     * @param string $inputFile  PDF 路径
     * @param string $searchText 要定位的文字
     * @return array|null [x1, y1, x2, y2, pageHeight]（PDF 坐标）
     */
    public function findTextCoordinates($inputFile, $searchText)
    {
        if (!$this->isPdftotextAvailable()) {
            return null;
        }

        $cmd = sprintf('pdftotext -bbox-layout %s - 2>/dev/null', escapeshellarg($inputFile));

        $output = []; $rc = 0;
        exec($cmd, $output, $rc);
        if ($rc !== 0 || empty($output)) return null;

        $xml = implode("\n", $output);

        $pageHeight = null;
        if (preg_match('/<page\s+width="([\d.]+)"\s+height="([\d.]+)"/', $xml, $m)) {
            $pageHeight = (float)$m[2];
        }

        $words = preg_split('/\s+/', trim($searchText));
        if (empty($words) || !$pageHeight) return null;

        $bboxes = [];
        foreach ($words as $word) {
            $esc = preg_quote(htmlspecialchars($word, ENT_QUOTES, 'UTF-8'), '/');
            if (preg_match('/<word\s+xMin="([\d.]+)"\s+yMin="([\d.]+)"\s+xMax="([\d.]+)"\s+yMax="([\d.]+)">' . $esc . '<\/word>/', $xml, $m)) {
                $bboxes[] = [(float)$m[1], (float)$m[2], (float)$m[3], (float)$m[4]];
            }
        }

        if (empty($bboxes)) return null;

        $tx1 = min(array_column($bboxes, 0)) - 3;
        $ty1 = min(array_column($bboxes, 1)) - 2;
        $tx2 = max(array_column($bboxes, 2)) + 3;
        $ty2 = max(array_column($bboxes, 3)) + 2;

        // 转换为 PDF 左下角坐标系
        return [
            $tx1,                       // x1
            $pageHeight - $ty2,         // y1 (底部)
            $tx2,                       // x2
            $pageHeight - $ty1,         // y2 (顶部)
            $pageHeight,
        ];
    }

    // ============ 内部方法 ============

    /**
     * 获取文字坐标
     */
    protected function _locateText($inputFile, $searchText)
    {
        // 1. 优先用已知坐标
        if (isset($this->_knownCoords[$searchText])) {
            return $this->_knownCoords[$searchText];
        }

        // 2. 回退 pdftotext
        return $this->findTextCoordinates($inputFile, $searchText);
    }

    /**
     * 在 PDF 页面上绘制白色覆盖
     */
    protected function _process($inputFile, $outputFile, array $coords)
    {
        list($x1, $y1, $x2, $y2) = $coords;

        try {
            $pdf = Zend_Pdf::load($inputFile);
        } catch (Exception $e) {
            return ['success' => false, 'message' => '加载 PDF 失败: ' . $e->getMessage()];
        }

        $page = $pdf->pages[0];
        $white = new Zend_Pdf_Color_Rgb(1, 1, 1);
        $page->setFillColor($white);
        $page->setLineColor($white);
        $page->drawRectangle($x1, $y1, $x2, $y2);

        try {
            $pdf->save($outputFile);
        } catch (Exception $e) {
            return ['success' => false, 'message' => '保存 PDF 失败: ' . $e->getMessage()];
        }

        $size = file_exists($outputFile) ? filesize($outputFile) : 0;
        return ['success' => true, 'message' => "已保存 ({$size} bytes)", 'coords' => $coords];
    }
}
