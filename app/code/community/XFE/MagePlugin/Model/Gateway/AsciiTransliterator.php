<?php

/**
 * voku/helper ASCII 适配器（L2 Gateway）。
 *
 * 职责：
 *  - 适配第三方库 voku/helper，把「按国家切语言 + 转写 + 降级删除非可打印 ASCII」
 *    三个能力封装到单一对外方法
 *  - 不做业务判断、不写日志、不发起 I/O
 *  - 通过 {@see XFE_MagePlugin_Domain_Constant_CountryLanguageMap} 获取映射表
 *  - 通过 {@see XFE_MagePlugin_Api_AsciiTransliteratorInterface} 对外暴露契约
 *
 * voku 加载策略（懒加载，避免类加载时强制依赖 voku 文件就位）：
 *  - 不引入 Composer、不依赖 vendor/autoload.php
 *  - 部署约定：voku/helper/src/voku/helper/ASCII.php 复制到 {BP}/lib/voku/helper/ASCII.php
 *    （BP 即 Magento 1 根目录常量）
 *  - 加载时机：首次真实调用 _invokeVoku() 时，类内部 _ensureVokuLoaded() 同步 require_once
 *  - 类可以被 Magento autoloader 加载、被测试 Stub 继承，无需 voku 文件就位
 *  - 如 voku 未就位，将抛 RuntimeException（明确错误信息，便于运维定位）
 *
 * @category   Community
 * @package    XFE_MagePlugin
 */

class XFE_MagePlugin_Model_Gateway_AsciiTransliterator
    implements XFE_MagePlugin_Api_AsciiTransliteratorInterface
{
    /**
     * voku/helper ASCII 库根类（含完整命名空间前缀）。
     * 用常量而非硬编码，便于将来替换实现或加单元测试 mock。
     */
    const VOKU_ASCII_CLASS = "\voku\helper\ASCII";

    /**
     * voku to_transliterate 的 $unknown 参数值。
     *
     * 设空串而非 "?" 的原因：我们后面会再过一遍 [^\x20-\x7E] 过滤，
     * 这样最终输出更接近原 XFE_LogisticImport_Helper_Normalizer 的语义
     * （直接删除非可打印字符，而非替换成 "?"）。
     */
    const VOKU_UNKNOWN_REPLACEMENT = "";

    /**
     * 不可打印 ASCII 范围（0x20-0x7E）之外字符的正则。
     *
     * 与原 toAscii 的 "[^\x20-\x7E] Remove" 规则等价，
     * 实现「静默丢弃不可识别字符」的降级语义。
     */
    const NON_PRINTABLE_ASCII_PATTERN = "/[^\x20-\x7E]/";

    /** @var string 已规范化的当前国家代码（大写），用于内部记录/调试 */
    protected $_countryCode = "";

    /**
     * {@inheritdoc}
     */
    public function transliterate($text, $countryCode)
    {
        $text        = (string) $text;
        $countryCode = strtoupper(trim((string) $countryCode));
        $this->_countryCode = $countryCode;

        $mapClass = "XFE_MagePlugin_Domain_Constant_CountryLanguageMap";

        // 1. 非拉丁语系国家：原样返回（与原代码语义一致）
        if ($mapClass::isNonLatin($countryCode)) {
            return $text;
        }

        // 2. 纯 ASCII 短路（避免不必要的 voku 调用）
        if (!preg_match('/[^\x00-\x7F]/u', $text)) {
            return $text;
        }

        // 3. 解析 voku 源语言
        $sourceLang = $mapClass::resolve($countryCode);

        // 4. 调用 voku 转写
        $ascii = $this->_invokeVoku($text, $sourceLang);

        // 5. 对齐原代码「删除非可打印 ASCII」的降级语义
        $ascii = preg_replace(self::NON_PRINTABLE_ASCII_PATTERN, "", $ascii);

        return $ascii;
    }

    /**
     * 调用 voku/helper ASCII 库执行实际转写。
     *
     * 单独抽方法便于单元测试时替换（voku 是静态方法，不易 mock，
     * 但子类化 Gateway 后可覆盖此方法返回固定字符串）。
     *
     * 进入此方法即意味着测试已结束（Stub 不会调用到这里），此时必须确保 voku 已加载。
     *
     * @param string $text       待转写文本
     * @param string $sourceLang voku 源语言标签（BCP-47）
     * @return string voku 转写后的字符串（仍可能含非可打印 ASCII，由调用方负责过滤）
     */
    protected function _invokeVoku($text, $sourceLang)
    {
        self::_ensureVokuLoaded();

        $class = self::VOKU_ASCII_CLASS;
        return $class::to_transliterate(
            $text,
            self::VOKU_UNKNOWN_REPLACEMENT,
            $sourceLang
        );
    }

    /**
     * 确保 voku/helper ASCII 类已加载（懒加载）。
     *
     * 部署约定：{BP}/lib/voku/helper/ASCII.php
     *   - voku/helper/src/voku/helper/ASCII.php 由部署层复制到上述路径
     *   - 若该路径不存在或文件不可读，抛 RuntimeException（含期望路径以便运维定位）
     *
     * 类加载时不触发本方法（便于 Magento autoloader + 测试 Stub 继承）；
     * 仅在 _invokeVoku() 首次被真实调用时触发。
     *
     * @return void
     * @throws \RuntimeException voku 文件未就位时
     */
    protected static function _ensureVokuLoaded()
    {
        if (class_exists(self::VOKU_ASCII_CLASS, false)) {
            return;
        }

        $magentoRoot = defined("BP") ? BP : dirname(__DIR__, 6);
        $vokuPath    = $magentoRoot . "/lib/voku/helper/ASCII.php";
        if (!is_file($vokuPath)) {
            throw new RuntimeException(
                "voku/helper ASCII 库未部署。期望路径：" . $vokuPath
                . "。请将 voku/helper/src/voku/helper/ASCII.php（及其依赖文件）复制到该路径。"
            );
        }
        require_once $vokuPath;
    }

    /**
     * 获取当前最近一次调用使用的国家代码（仅供调试/日志使用）。
     *
     * @return string
     */
    public function getCurrentCountryCode()
    {
        return $this->_countryCode;
    }
}
