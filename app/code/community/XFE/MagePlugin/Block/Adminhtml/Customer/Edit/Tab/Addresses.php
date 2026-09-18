<?php
/**
 * XFE_MagePlugin 客户地址 Tab（查看权限脱敏）。
 *
 * 账号 1/2（完整权限）：正常显示完整地址。
 * 其他账号：地址列表与表单仅显示邮编、城市、国家，并禁用复制粘贴。
 */
class XFE_MagePlugin_Block_Adminhtml_Customer_Edit_Tab_Addresses extends Mage_Adminhtml_Block_Customer_Edit_Tab_Addresses
{
    /**
     * 脱敏账号可保留的字段白名单。
     */
    const MASK_KEEP_FIELDS = array('postcode', 'city', 'country_id', 'region_id', 'region');

    /**
     * 是否完整权限（缓存）。
     *
     * @var bool|null
     */
    protected $_fullAccess = null;

    /**
     * 构造函数：替换为自定义模板。
     */
    public function __construct()
    {
        $this->setTemplate('xfe_mageplugin/customer/tab/addresses.phtml');
    }

    /**
     * 当前用户是否完整权限。
     *
     * @return bool
     */
    public function isFullAccess()
    {
        if ($this->_fullAccess === null) {
            $this->_fullAccess = Mage::helper('xfe_mageplugin')->isCurrentUserFullAccess();
        }
        return $this->_fullAccess;
    }

    /**
     * 判断地址是否应脱敏展示。
     *
     * @return bool
     */
    public function shouldMaskAddress()
    {
        return !$this->isFullAccess();
    }

    /**
     * 渲染脱敏后的地址 HTML（仅邮编、城市、国家）。
     *
     * @param Mage_Customer_Model_Address $address
     * @return string
     */
    public function getMaskedAddressHtml($address)
    {
        $parts = array();
        if ($postcode = $address->getPostcode()) {
            $parts[] = $this->escapeHtml($postcode);
        }
        if ($city = $address->getCity()) {
            $parts[] = $this->escapeHtml($city);
        }
        if ($countryId = $address->getCountryId()) {
            $countryName = Mage::app()->getLocale()->getCountryTranslation($countryId);
            $parts[] = $this->escapeHtml($countryName ? $countryName : $countryId);
        }
        return implode('<br/>', $parts);
    }

    /**
     * 渲染地址列表条目（按权限脱敏或完整）。
     *
     * @param Mage_Customer_Model_Address $address
     * @return string
     */
    public function getAddressHtml($address)
    {
        if ($this->shouldMaskAddress()) {
            return $this->getMaskedAddressHtml($address);
        }
        return $address->format('html');
    }

    /**
     * 初始化地址表单。
     *
     * 脱敏账号：仅保留邮编/城市/国家字段并只读，移除敏感字段。
     *
     * @return Mage_Adminhtml_Block_Customer_Edit_Tab_Addresses
     */
    public function initForm()
    {
        parent::initForm();

        if ($this->shouldMaskAddress() && $this->getForm()) {
            foreach ($this->getForm()->getElements() as $fieldset) {
                if ($fieldset instanceof Varien_Data_Form_Element_Fieldset) {
                    foreach ($fieldset->getElements() as $element) {
                        $fieldId = $element->getId();
                        if (!in_array($fieldId, self::MASK_KEEP_FIELDS, true)) {
                            $fieldset->removeField($fieldId);
                        }
                    }
                }
            }
            // 保留字段设为只读，防止修改/复制
            foreach (self::MASK_KEEP_FIELDS as $fieldId) {
                $element = $this->getForm()->getElement($fieldId);
                if ($element) {
                    $element->setReadonly(true, true);
                }
            }
        }

        return $this;
    }

    /**
     * 是否为只读（脱敏账号视为只读）。
     *
     * @return bool
     */
    public function isReadonly()
    {
        if ($this->shouldMaskAddress()) {
            return true;
        }
        return parent::isReadonly();
    }
}
