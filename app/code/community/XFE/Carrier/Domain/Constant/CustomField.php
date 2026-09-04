<?php
/**
 * 承运商账号自定义字段的常量定义
 *
 * 集中放"业务阈值 / 状态码 / 错误消息常量"——按 AGENTS.md 5.2 不写魔法常量。
 *
 * 关联文档: docs/architecture/carrier-account-custom-fields.md
 */
final class XFE_Carrier_Domain_Constant_CustomField
{
    /** 单条记录 key 名 */
    const POST_BAG_KEY = 'custom_fields';

    /** POST 中每个字段的子字段名 */
    const POST_FIELD_KEY    = 'key';
    const POST_FIELD_LABEL  = 'label';
    const POST_FIELD_TYPE   = 'type';
    const POST_FIELD_VALUE  = 'value';
    const POST_FIELD_OPTIONS = 'options';

    /** 事件名 */
    const EVENT_CHANGED = 'xfe_carrier_account_custom_field_changed';

    /** 事件 action 取值 */
    const ACTION_ADDED   = 'added';
    const ACTION_UPDATED = 'updated';
    const ACTION_REMOVED = 'removed';

    /**
     * 校验 POST 数据形态:必须 [POST_BAG_KEY => [key => [sub=>val, ...]]]
     *
     * @param array $post
     * @return bool
     */
    public static function isPostBagShape(array $post)
    {
        return isset($post[self::POST_BAG_KEY])
            && is_array($post[self::POST_BAG_KEY]);
    }
}
