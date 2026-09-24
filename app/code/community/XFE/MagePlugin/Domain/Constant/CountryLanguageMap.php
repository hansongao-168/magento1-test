<?php

/**
 * ISO 3166-1 alpha-2 → voku ASCII BCP-47 语言标签映射表。
 *
 * 集中管理两类业务常量（AGENTS.md 5.2）：
 *  - 国家 → 语言映射：影响 ä→ae vs ä→a 等转写策略
 *  - 非拉丁语系白名单：命中时调用方应跳过转写、原样返回
 *
 * 任何修改必须同步更新 docs/architecture/mageplugin-ascii-transliterator-api.md 的契约表。
 *
 * @category   Community
 * @package    XFE_MagePlugin
 */
class XFE_MagePlugin_Domain_Constant_CountryLanguageMap
{
    /**
     * 国家代码未命中映射表时使用的兜底语言标签。
     *
     * voku 'en' 规则近似 Any-Latin：变音符号一律去除（é→e、ñ→n、ü→u），
     * 不做 ae/oe/ue 这种发音保留。适用于无明确语言偏好的地址清洗。
     */
    const LANGUAGE_FALLBACK = 'en';

    /**
     * 非拉丁语系国家白名单。
     *
     * 命中时 Gateway 应原样返回文本，不做任何转写。
     * 来源：原 XFE_LogisticImport_Helper_Normalizer::toAscii 中的 nonLatinCountries。
     *
     * 分组说明：
     *  - CJK：CN/TW/HK/MO/JP/KR/KP（防止日文被错误拼音化为汉语拼音、保留汉字/假名）
     *  - 西里尔：RU/UA/BY/BG
     *  - 东南亚/南亚：TH/VN/IN
     *  - 阿拉伯中东：AE/SA/QA/EG
     *  - 纯英语国家：US/GB/AU/NZ/IE（输入假设为纯英文，转写无意义）
     */
    private static $nonLatinCountries = array(
        'CN', 'TW', 'HK', 'MO',
        'JP',
        'KR', 'KP',
        'RU', 'UA', 'BY', 'BG',
        'TH', 'VN', 'IN',
        'AE', 'SA', 'QA', 'EG',
        'US', 'GB', 'AU', 'NZ', 'IE',
    );

    /**
     * 国家代码 → voku 源语言标签映射。
     *
     * 键：ISO 3166-1 alpha-2（大写）
     * 值：BCP-47 语言标签（voku ASCII::to_transliterate 接受的 source_lang 格式）
     *
     * 字段选择原则：
     *  - DE/AT/CH/LI 走 'de'，让 ä→ae、ö→oe、ü→ue、ß→ss（voku PHP 数组表）
     *  - 多语言国家（BE/CH/LU）按主语言默认，业务侧可通过系统配置覆盖
     */
    private static $map = array(
        // 德语区
        'DE' => 'de',
        'AT' => 'de',
        'CH' => 'de',
        'LI' => 'de',
        'LU' => 'de',

        // 法语区
        'FR' => 'fr',
        'MC' => 'fr',
        'CA' => 'fr-CA',
        'BE' => 'fr',

        // 意大利语
        'IT' => 'it',
        'SM' => 'it',
        'VA' => 'it',

        // 西班牙/葡萄牙语
        'ES' => 'es',
        'MX' => 'es',
        'AR' => 'es',
        'CL' => 'es',
        'CO' => 'es',
        'PE' => 'es',
        'VE' => 'es',
        'UY' => 'es',
        'PY' => 'es',
        'BO' => 'es',
        'EC' => 'es',
        'CR' => 'es',
        'GT' => 'es',
        'HN' => 'es',
        'NI' => 'es',
        'PA' => 'es',
        'DO' => 'es',
        'CU' => 'es',
        'PR' => 'es',
        'PT' => 'pt-PT',
        'BR' => 'pt-BR',
        'AO' => 'pt-PT',
        'MZ' => 'pt-PT',

        // 荷兰语
        'NL' => 'nl',
        'SR' => 'nl',
        'AW' => 'nl',
        'CW' => 'nl',

        // 北欧
        'SE' => 'sv',
        'NO' => 'no',
        'DK' => 'da',
        'FO' => 'da',
        'GL' => 'da',
        'FI' => 'fi',
        'IS' => 'is',

        // 中东欧
        'PL' => 'pl',
        'CZ' => 'cs',
        'SK' => 'sk',
        'HU' => 'hu',
        'RO' => 'ro',
        'MD' => 'ro',
        'GR' => 'el',
        'CY' => 'el',
        'TR' => 'tr',
        'SI' => 'sl',
        'HR' => 'hr',
        'BA' => 'hr',
        'AL' => 'sq',

        // 波罗的海
        'LT' => 'lt',
        'LV' => 'lv',
        'EE' => 'et',
    );

    /**
     * 判断国家代码是否属于“非拉丁语系白名单”。
     *
     * 命中时调用方必须原样返回文本，不做任何转写。
     *
     * @param string $countryCode ISO 3166-1 alpha-2（大小写不敏感）
     * @return bool
     */
    public static function isNonLatin($countryCode)
    {
        $countryCode = strtoupper(trim((string) $countryCode));
        return in_array($countryCode, self::$nonLatinCountries, true);
    }

    /**
     * 根据国家代码解析 voku 源语言标签。
     *
     * 解析顺序：
     *  1. 国家代码命中映射表 → 返回对应 BCP-47
     *  2. 未命中 → 返回 LANGUAGE_FALLBACK（默认 'en'）
     *
     * @param string $countryCode ISO 3166-1 alpha-2（大小写不敏感）
     * @return string BCP-47 语言标签，例如 'de'、'fr-CA'、'pt-BR'
     */
    public static function resolve($countryCode)
    {
        $countryCode = strtoupper(trim((string) $countryCode));
        if (isset(self::$map[$countryCode])) {
            return self::$map[$countryCode];
        }
        return self::LANGUAGE_FALLBACK;
    }
}
