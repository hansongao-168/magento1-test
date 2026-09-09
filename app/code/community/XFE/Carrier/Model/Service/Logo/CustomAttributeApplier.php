<?php
/**
 * 承运商 LOGO(xfe_carrier_carrier_logo)自定义属性 Applier
 *
 * 唯一对外入口(由 Controller 直接调):
 *   $applier = new XFE_Carrier_Model_Service_Logo_CustomAttributeApplier();
 *   $applier->applyFromPost($logoId, $post);
 *
 * 关联文档: docs/architecture/carrier-global-custom-field-defs.md
 */
final class XFE_Carrier_Model_Service_Logo_CustomAttributeApplier
    extends XFE_Carrier_Model_Service_CustomAttributeApplierAbstract
{
    protected function _getModelAlias()
    {
        return 'xfe_carrier/carrier_logo';
    }

    protected function _getEntityIdColumn()
    {
        return 'logo_id';
    }

    protected function _getEntityType()
    {
        return XFE_Carrier_Domain_CustomAttribute::ENTITY_TYPE_LOGO;
    }
}
