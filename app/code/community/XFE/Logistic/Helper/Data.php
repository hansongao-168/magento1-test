<?php

/**
 * XFE_Logistic Helper 空壳（向后兼容）。
 *
 * 历史：
 *   - 1.0.x ~ 1.2.x：提供 getParcelPodUrl() / getBasicAuthHeaderValue() 等方法读取 GLS 凭据
 *   - 1.3.0（ADR 0021）：删除上述方法，凭据统一由 XFE_Carrier::service_carrier_get_credentials 注入
 *
 * 保留原因：
 *   - config.xml 中 <helpers><xfe_logistic><class>XFE_Logistic_Helper</class></helpers>
 *     仍声明本类；删除类文件会导致 Magento factory 报 "Class not found"。
 *
 * @category   Community
 * @package    XFE_Logistic
 */
class XFE_Logistic_Helper_Data extends Mage_Core_Helper_Abstract
{
}