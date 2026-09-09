<?php
/**
 * 自定义字段 JSON 编解码器(L1 Domain 工具)
 *
 * 唯一允许接触"列原文字符串"和"值对象集合"互转的地方。Service / Model 都不
 * 直接调 json_encode / json_decode,统一走本 Codec。
 *
 * 关联文档: docs/architecture/carrier-account-custom-fields.md
 */
final class XFE_Carrier_Domain_CustomFieldCodec
{
    /**
     * 字符串(TEXT 列原文) → 值对象集合。空 / 非法 → 空集合(不抛异常)。
     *
     * @param string|null $jsonString
     * @return XFE_Carrier_Domain_CustomFieldCollection
     */
    public static function decode($jsonString)
    {
        if ($jsonString === null || $jsonString === '') {
            return new XFE_Carrier_Domain_CustomFieldCollection();
        }
        $data = json_decode((string) $jsonString, true);
        if (!is_array($data)) {
            return new XFE_Carrier_Domain_CustomFieldCollection();
        }
        // 仅保留 [key => {label,type,value,options?}] 形态,过滤 list/标量
        $filtered = array();
        foreach ($data as $k => $v) {
            if (is_string($k) && is_array($v)) {
                $filtered[$k] = $v;
            }
        }
        return XFE_Carrier_Domain_CustomFieldCollection::fromArray($filtered);
    }

    /**
     * 值对象集合 → JSON 字符串(用于持久化)。空集合 → "{}"(保持对象语义)。
     *
     * @param XFE_Carrier_Domain_CustomFieldCollection $coll
     * @return string
     */
    public static function encode(XFE_Carrier_Domain_CustomFieldCollection $coll)
    {
        $obj = (object) $coll->toArray();
        return json_encode(
            $obj,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
