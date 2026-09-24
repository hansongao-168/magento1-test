<?php

/**
 * ASCII 转写适配器对外契约。
 *
 * L2 Gateway 公开接口：把任意文本按"国家上下文"转写为 ASCII 形式，
 * 用于物流地址标准化、TrackID 校验、运单匹配等场景。
 *
 * 实现约束（任何实现都必须满足，详见 mageplugin-ascii-transliterator-api.md）：
 *  - 非拉丁语系国家（CN/TW/HK/MO/JP/KR/KP/RU/UA/BY/BG/TH/VN/IN/AE/SA/QA/EG/
 *    US/GB/AU/NZ/IE）原样返回，不触发任何转写
 *  - 纯 ASCII 输入（仅含 U+0000..U+007F）原样返回
 *  - 转写后所有 U+0000..U+001F 与 U+007F 字符被静默删除
 *  - 不可打印 ASCII 范围 0x20-0x7E 之外的所有字符被静默删除（不是替换成 '?'）
 *
 * 上层（L3 Service / L4 Controller / Observer）只依赖此接口，
 * 不直接 new 具体 Gateway。
 *
 * @category   Community
 * @package    XFE_MagePlugin
 */
interface XFE_MagePlugin_Api_AsciiTransliteratorInterface
{
    /**
     * 根据国家上下文把文本转写为 ASCII 形式。
     *
     * @param string $text        待转写文本（UTF-8）
     * @param string $countryCode ISO 3166-1 alpha-2 国家代码（大写或小写均可，
     *                            内部会 strtoupper 规范化）
     * @return string ASCII 化后的文本；非拉丁国家或纯 ASCII 输入时返回原文
     */
    public function transliterate($text, $countryCode);
}
