<?php

class XFE_Carrier_Block_Adminhtml_Carrier_Rule_Edit_Tab_General
    extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    protected function _prepareForm()
    {
        $helper = Mage::helper("xfe_carrier");
        $rule   = Mage::registry("xfe_carrier_rule_data");

        $form = new Varien_Data_Form();
        $this->setForm($form);

        $fieldset = $form->addFieldset("general_fieldset", array(
            "legend" => $helper->__("\xe8\xa7\x84\xe5\x88\x99\xe4\xbf\xa1\xe6\x81\xaf"),
        ));

        if ($rule && $rule->getId()) {
            $fieldset->addField("rule_id", "label", array(
                "label" => $helper->__("\xe8\xa7\x84\xe5\x88\x99 ID"),
                "name"  => "rule_id",
                "value" => $rule->getId(),
            ));
        }

        $fieldset->addField("carrier_id", "hidden", array(
            "name" => "carrier_id",
        ));

        $fieldset->addField("account_id", "hidden", array(
            "name" => "account_id",
        ));

        $fieldset->addField("logo_id", "hidden", array(
            "name" => "logo_id",
        ));

        $fieldset->addField("name", "text", array(
            "name"     => "name",
            "label"    => $helper->__("\xe8\xa7\x84\xe5\x88\x99\xe5\x90\x8d\xe7\xa7\xb0"),
            "title"    => $helper->__("\xe8\xa7\x84\xe5\x88\x99\xe5\x90\x8d\xe7\xa7\xb0"),
            "required" => true,
        ));

        $fieldset->addField("description", "textarea", array(
            "name"  => "description",
            "label" => $helper->__("\xe6\x8f\x8f\xe8\xbf\xb0"),
            "title" => $helper->__("\xe6\x8f\x8f\xe8\xbf\xb0"),
            "style" => "height:60px;",
        ));

        // Scope detection
        $request        = Mage::app()->getRequest();
        $accountId      = $rule ? (int)$rule->getAccountId() : (int)$request->getParam("account_id");
        $logoId         = $rule ? (int)$rule->getLogoId() : (int)$request->getParam("logo_id");
        $isAccountScope = ($accountId > 0);
        $isLogoScope    = ($logoId > 0);

        $moduleOptions = $helper->getCarrierModuleSelectOptions();
        if ($isAccountScope) {
            $fieldset->addField("module_code", "hidden", array(
                "name"  => "module_code",
                "value" => "account",
            ));
            $fieldset->addField("module_code_display", "label", array(
                "label" => $helper->__("\xe6\x89\x80\xe5\xb1\x9e\xe6\xa8\xa1\xe5\x9d\x97"),
                "value" => $helper->__("\xe8\xb4\xa6\xe5\x8f\xb7\xe7\xae\xa1\xe7\x90\x86"),
                "bold"  => true,
            ));
        } elseif ($isLogoScope) {
            // Logo-scope: module_code is implicitly "logo", not editable.
            $fieldset->addField("module_code", "hidden", array(
                "name"  => "module_code",
                "value" => "logo",
            ));
            $fieldset->addField("module_code_display", "label", array(
                "label" => $helper->__("\xe6\x89\x80\xe5\xb1\x9e\xe6\xa8\xa1\xe5\x9d\x97"),
                "value" => $helper->__("Logo"),
                "bold"  => true,
            ));
        } else {
            $moduleSelectOptions = array(array("value" => "", "label" => $helper->__("-- \xe8\xaf\xb7\xe9\x80\x89\xe6\x8b\xa9 --")));
            foreach ($moduleOptions as $code => $label) {
                $moduleSelectOptions[] = array("value" => $code, "label" => $label);
            }
            $fieldset->addField("module_code", "select", array(
                "name"   => "module_code",
                "label"  => $helper->__("\xe6\x89\x80\xe5\xb1\x9e\xe6\xa8\xa1\xe5\x9d\x97"),
                "title"  => $helper->__("\xe6\x89\x80\xe5\xb1\x9e\xe6\xa8\xa1\xe5\x9d\x97"),
                "values" => $moduleSelectOptions,
            ));
        }

        $fieldset->addField("status", "select", array(
            "name"   => "status",
            "label"  => $helper->__("\xe7\x8a\xb6\xe6\x80\x81"),
            "title"  => $helper->__("\xe7\x8a\xb6\xe6\x80\x81"),
            "values" => Mage::getSingleton("xfe_carrier/source_status")->toOptionArray(),
        ));

        $fieldset->addField("is_cancel_on_failure", "select", array(
            "name"   => "is_cancel_on_failure",
            "label"  => $helper->__("\xe5\xa4\xb1\xe8\xb4\xa5\xe5\x8f\x96\xe6\xb6\x88"),
            "title"  => $helper->__("\xe5\xa4\xb1\xe8\xb4\xa5\xe5\x8f\x96\xe6\xb6\x88"),
            "note"   => $helper->__("\xe5\x8c\xb9\xe9\x85\x8d\xe5\xa4\xb1\xe8\xb4\xa5\xe6\x97\xb6\xe5\x8f\x96\xe6\xb6\x88\xe8\xaf\xa5\xe8\xa7\x84\xe5\x88\x99"),
            "values" => array(
                array("value" => 0, "label" => $helper->__("\xe5\x90\xa6")),
                array("value" => 1, "label" => $helper->__("\xe6\x98\xaf")),
            ),
        ));

        $fieldset->addField("priority", "text", array(
            "name"  => "priority",
            "label" => $helper->__("\xe4\xbc\x98\xe5\x85\x88\xe7\xba\xa7"),
            "title" => $helper->__("\xe4\xbc\x98\xe5\x85\x88\xe7\xba\xa7"),
            "class" => "validate-number",
            "note"  => $helper->__("\xe8\x8c\x83\xe5\x9b\xb4\xe5\x8c\xb9\xe9\x85\x8d\xe6\x97\xb6\xe9\x9c\x80\xe8\xa6\x81\xe6\xaf\x94\xe8\xbe\x83\xe7\x9a\x84\xe4\xbc\x98\xe5\x85\x88\xe7\xba\xa7"),
        ));

        $fieldset->addField("sort_order", "text", array(
            "name"  => "sort_order",
            "label" => $helper->__("\xe6\x8e\x92\xe5\xba\x8f"),
            "title" => $helper->__("\xe6\x8e\x92\xe5\xba\x8f"),
            "class" => "validate-number",
            "note"  => $helper->__("\xe8\xb6\x8a\xe5\xb0\x8f\xe8\xb6\x8a\xe9\x9d\xa0\xe5\x89\x8d"),
        ));

        // Account scope indicator
        $accountScopeField = null;
        if ($accountId) {
            $account = Mage::getModel("xfe_carrier/carrier_account")->load($accountId);
            if ($account && $account->getId()) {
                $name = trim((string)$account->getAccountName());
                $code = trim((string)$account->getAccountCode());
                if ($name !== "" && $code !== "") {
                    $accountLabel = sprintf("%s (%s)", $name, $code);
                } elseif ($name !== "") {
                    $accountLabel = $name;
                } elseif ($code !== "") {
                    $accountLabel = $code;
                } else {
                    $accountLabel = sprintf("#%d", $accountId);
                }
            } else {
                $accountLabel = sprintf("#%d", $accountId);
            }

            $accountScopeField = $fieldset->addField("account_scope", "label", array(
                "label" => $helper->__("\xe9\x80\x82\xe7\x94\xa8\xe8\xb4\xa6\xe5\x8f\xb7"),
                "value" => $helper->__($accountLabel),
                "bold"  => true,
                "after_element_html" => '<br /><small style="color:#888;\">'
                    . $helper->__("\xe8\xaf\xa5\xe8\xa7\x84\xe5\x88\x99\xe5\xb0\x86\xe4\xbd\x9c\xe4\xb8\xba\xe8\xaf\xa5\xe8\xb4\xa6\xe5\x8f\xb7\xe7\x9a\x84\xe4\xbc\x98\xe9\x80\x89\xe8\xa7\x84\xe5\x88\x99\xe3\x80\x82\xe5\xa6\x82\xe9\x9c\x80\xe6\x94\xb9\xe5\x8f\x98\xe8\x8c\x83\xe5\x9b\xb4\xe8\xaf\xb7\xe5\x88\xb0\xe8\xb4\xa6\xe5\x8f\xb7\xe7\xae\xa1\xe7\x90\x86\xe9\xa1\xb5\xe9\x9d\xa2\xe6\x93\x8d\xe4\xbd\x9c\xe3\x80\x82")
                    . '</small>',
            ));
        }

        if ($rule) {
            $form->setValues($rule->getData());
        }

        // Re-apply label values
        if ($isAccountScope) {
            $moduleField = $form->getElement("module_code");
            if ($moduleField) {
                $moduleField->setValue("account");
            }
            $moduleDisplayField = $form->getElement("module_code_display");
            if ($moduleDisplayField) {
                $moduleDisplayField->setValue($helper->__("\xe8\xb4\xa6\xe5\x8f\xb7\xe7\xae\xa1\xe7\x90\x86"));
            }
        }
        if ($isLogoScope) {
            $moduleField = $form->getElement("module_code");
            if ($moduleField) {
                $moduleField->setValue("logo");
            }
            $moduleDisplayField = $form->getElement("module_code_display");
            if ($moduleDisplayField) {
                $moduleDisplayField->setValue($helper->__("Logo"));
            }
        }
        if ($accountScopeField) {
            $accountScopeField->setValue($helper->__($accountLabel));
        }

        return parent::_prepareForm();
    }

    public function getTabLabel()
    {
        return Mage::helper("xfe_carrier")->__("\xe8\xa7\x84\xe5\x88\x99\xe4\xbf\xa1\xe6\x81\xaf");
    }

    public function getTabTitle()
    {
        return Mage::helper("xfe_carrier")->__("\xe8\xa7\x84\xe5\x88\x99\xe4\xbf\xa1\xe6\x81\xaf");
    }

    public function canShowTab()
    {
        return true;
    }

    public function isHidden()
    {
        return false;
    }
}