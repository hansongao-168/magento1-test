<?php
/**
 * 承运商账号(xfe_carrier_carrier_account)自定义属性 Applier
 *
 * 1.0.15+ 严格模式入口。Controller saveAccountAction 改调本类。
 *
 * 关联文档: docs/architecture/carrier-global-custom-field-defs.md
 */
final class XFE_Carrier_Model_Service_Account_CustomAttributeApplier
    extends XFE_Carrier_Model_Service_CustomAttributeApplierAbstract
{
    protected function _getModelAlias()
    {
        return 'xfe_carrier/carrier_account';
    }

    protected function _getEntityIdColumn()
    {
        return 'account_id';
    }

    protected function _getEntityType()
    {
        return XFE_Carrier_Domain_CustomAttribute::ENTITY_TYPE_ACCOUNT;
    }
}
