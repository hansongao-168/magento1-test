<?php

/**
 * Service Registry
 *
 * One-stop factory for carriers' domain services. Keeps call sites clean:
 *
 *     XFE_Carrier_Model_Service_Registry::logo()->upload(...)
 *
 * Why a registry (and not Magento <helpers>):
 *   - the services depend on injected collaborators (e.g. FileStore)
 *   - test code can substitute any service via setLogo() etc.
 *
 * Dependencies (one-way):
 *   Registry ─▶  LogoService  ─▶ FileStore + ImageProcessor + Carrier_Logo
 *   Registry ─▶  AccountService ─▶ Carrier_Account
 *   Registry ─▶  RuleService   ─▶ Carrier_Rule + ConditionGroup + Condition
 *
 * No service knows about Registry or about siblings.
 */
class XFE_Carrier_Model_Service_Registry
{
    /** @var XFE_Carrier_Model_Service_Logo|null */
    protected static $_logo = null;

    /** @var XFE_Carrier_Model_Service_Account|null */
    protected static $_account = null;

    /** @var XFE_Carrier_Model_Service_Rule|null */
    protected static $_rule = null;

    /** @var XFE_Carrier_Model_Service_Rule_Resolver|null */
    protected static $_ruleResolver = null;

    /**
     * @return XFE_Carrier_Model_Service_Logo
     */
    public static function logo()
    {
        if (self::$_logo === null) {
            self::$_logo = XFE_Carrier_Model_Service_Logo::instance();
        }
        return self::$_logo;
    }

    /**
     * @return XFE_Carrier_Model_Service_Account
     */
    public static function account()
    {
        if (self::$_account === null) {
            self::$_account = XFE_Carrier_Model_Service_Account::instance();
        }
        return self::$_account;
    }

    /**
     * @return XFE_Carrier_Model_Service_Rule
     */
    public static function rule()
    {
        if (self::$_rule === null) {
            self::$_rule = XFE_Carrier_Model_Service_Rule::instance();
        }
        return self::$_rule;
    }

    /**
     * Resolver - given a carrier id + shipment context, picks the right
     * account and logo. Read-only; safe to call from cart / checkout code.
     *
     * @return XFE_Carrier_Model_Service_Rule_Resolver
     */
    public static function ruleResolver()
    {
        if (self::$_ruleResolver === null) {
            self::$_ruleResolver = XFE_Carrier_Model_Service_Rule_Resolver::instance();
        }
        return self::$_ruleResolver;
    }

    /**
     * Test hook: replace any service.
     *
     * @param string $name 'logo' | 'account' | 'rule'
     * @param object|null $instance
     */
    public static function set($name, $instance)
    {
        $prop = '_' . $name;
        if (!property_exists(__CLASS__, $prop)) {
            return;
        }
        self::${$prop} = $instance;
    }
}
