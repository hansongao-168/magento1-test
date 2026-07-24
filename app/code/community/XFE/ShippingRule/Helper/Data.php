<?php
/**
 * XFE ShippingRule Helper
 *
 * @category   Community
 * @package    XFE_ShippingRule
 */
class XFE_ShippingRule_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Get billing type options
     *
     * @return array
     */
    public function getBillingTypeOptions()
    {
        return array(
            'per_order'   => Mage::helper('xfeshippingrule')->__('Per Order'),
            'per_package' => Mage::helper('xfeshippingrule')->__('Per Package'),
        );
    }

    /**
     * Get status options
     *
     * @return array
     */
    public function getStatusOptions()
    {
        return array(
            '1' => Mage::helper('xfeshippingrule')->__('Enabled'),
            '0' => Mage::helper('xfeshippingrule')->__('Disabled'),
        );
    }

    /**
     * Get stack mode options
     *
     * @return array
     */
    public function getStackModeOptions()
    {
        return array(
            '0' => Mage::helper('xfeshippingrule')->__('Not Stackable (stop on first match)'),
            '1' => Mage::helper('xfeshippingrule')->__('Stackable (accumulate with other rules)'),
        );
    }

    /**
     * Get condition attribute options
     *
     * @return array
     */
    public function getConditionAttributeOptions()
    {
        return array(
            'user_id'        => Mage::helper('xfeshippingrule')->__('Customer ID'),
            'user_email'     => Mage::helper('xfeshippingrule')->__('Customer Email'),
            'country_code'   => Mage::helper('xfeshippingrule')->__('Country Code'),
            'city'           => Mage::helper('xfeshippingrule')->__('City Name'),
            'zip_code'       => Mage::helper('xfeshippingrule')->__('Zip/Postal Code'),
            'package_count'  => Mage::helper('xfeshippingrule')->__('Package Count'),
            'package_weight' => Mage::helper('xfeshippingrule')->__('Package Weight'),
            'length'         => Mage::helper('xfeshippingrule')->__('Length'),
            'width'          => Mage::helper('xfeshippingrule')->__('Width'),
            'height'         => Mage::helper('xfeshippingrule')->__('Height'),
            'volume'         => Mage::helper('xfeshippingrule')->__('Volume (Length x Width x Height)'),
            'custom'         => Mage::helper('xfeshippingrule')->__('Custom...'),
        );
    }

    /**
     * Get operator options
     *
     * @return array
     */
    public function getOperatorOptions()
    {
        return array(
            '=='       => Mage::helper('xfeshippingrule')->__('Equals'),
            '!='       => Mage::helper('xfeshippingrule')->__('Not Equals'),
            '>'        => Mage::helper('xfeshippingrule')->__('Greater Than'),
            '>='       => Mage::helper('xfeshippingrule')->__('Greater or Equal'),
            '<'        => Mage::helper('xfeshippingrule')->__('Less Than'),
            '<='       => Mage::helper('xfeshippingrule')->__('Less or Equal'),
            'in'       => Mage::helper('xfeshippingrule')->__('In (comma-separated)'),
            'contains' => Mage::helper('xfeshippingrule')->__('Contains'),
            'between'  => Mage::helper('xfeshippingrule')->__('Between (x~y)'),
        );
    }

    /**
     * Get numeric operators (for numeric attributes)
     *
     * @return array
     */
    public function getNumericOperators()
    {
        return array(
            '==' => Mage::helper('xfeshippingrule')->__('Equals'),
            '!=' => Mage::helper('xfeshippingrule')->__('Not Equals'),
            '>'  => Mage::helper('xfeshippingrule')->__('Greater Than'),
            '>=' => Mage::helper('xfeshippingrule')->__('Greater or Equal'),
            '<'  => Mage::helper('xfeshippingrule')->__('Less Than'),
            '<=' => Mage::helper('xfeshippingrule')->__('Less or Equal'),
            'between' => Mage::helper('xfeshippingrule')->__('Between (x~y)'),
        );
    }

    /**
     * Get string operators (for string attributes)
     *
     * @return array
     */
    public function getStringOperators()
    {
        return array(
            '=='       => Mage::helper('xfeshippingrule')->__('Equals'),
            '!='       => Mage::helper('xfeshippingrule')->__('Not Equals'),
            'in'       => Mage::helper('xfeshippingrule')->__('In (comma-separated)'),
            'contains' => Mage::helper('xfeshippingrule')->__('Contains'),
        );
    }

    /**
     * Check if attribute is numeric
     *
     * @param string $attribute
     * @return bool
     */
    public function isNumericAttribute($attribute)
    {
        $numericAttrs = array(
            'user_id', 'package_count', 'package_weight',
            'length', 'width', 'height', 'volume',
        );
        return in_array($attribute, $numericAttrs);
    }
}
