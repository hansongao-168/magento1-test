<?php
/**
 * 承运商主账号(xfe_carrier_carrier_account)自定义字段 Service
 *
 * 唯一对外入口:XFE_Carrier_Model_Service_Registry::accountCustomFieldService()
 *
 * 关联文档: docs/architecture/carrier-account-custom-fields.md
 */
final class XFE_Carrier_Model_Service_Account_CustomFieldService
    extends XFE_Carrier_Model_Service_Account_CustomFieldServiceAbstract
{
    /** @var self|null */
    protected static $_instance = null;

    public static function instance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public static function setInstance($instance)
    {
        self::$_instance = $instance;
    }

    /**
     * @return string
     */
    protected function _getModelAlias()
    {
        return 'xfe_carrier/carrier_account';
    }

    /**
     * @return string
     */
    protected function _getEntityIdColumn()
    {
        return 'account_id';
    }

    /**
     * @return string
     */
    protected function _getEntityType()
    {
        return 'account';
    }
}
