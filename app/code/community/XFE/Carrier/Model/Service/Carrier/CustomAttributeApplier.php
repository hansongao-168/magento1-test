<?php
/**
 * 承运商本身(xfe_carrier_carrier)自定义属性 Applier
 *
 * 唯一对外入口(由 Controller 直接调):
 *   $applier = new XFE_Carrier_Model_Service_Carrier_CustomAttributeApplier();
 *   $applier->applyFromPost($carrierId, $post);
 *
 * 关联文档: docs/architecture/carrier-global-custom-field-defs.md
 */
final class XFE_Carrier_Model_Service_Carrier_CustomAttributeApplier
    extends XFE_Carrier_Model_Service_CustomAttributeApplierAbstract
{
    protected function _getModelAlias()
    {
        return 'xfe_carrier/carrier';
    }

    protected function _getEntityIdColumn()
    {
        return 'carrier_id';
    }

    protected function _getEntityType()
    {
        return XFE_Carrier_Domain_CustomAttribute::ENTITY_TYPE_CARRIER;
    }
}
