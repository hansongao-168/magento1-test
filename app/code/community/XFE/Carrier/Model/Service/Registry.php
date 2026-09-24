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

    /** @var XFE_Carrier_Model_Service_FtpAccount|null */
    protected static $_ftpAccount = null;

    /** @var XFE_Carrier_Model_Service_Rule|null */
    protected static $_rule = null;

    /** @var XFE_Carrier_Model_Service_Rule_Resolver|null */
    protected static $_ruleResolver = null;

    /** @var XFE_Carrier_Model_Service_Importer|null */
    protected static $_importer = null;

    /** @var XFE_Carrier_Model_Service_Rule_Importer|null */
    protected static $_ruleImporter = null;

    /** @var XFE_Carrier_Model_Service_Rule_Exporter|null */
    protected static $_ruleExporter = null;

    /** @var XFE_Carrier_Model_Service_Account_Importer|null */
    protected static $_accountImporter = null;

    /** @var XFE_Carrier_Model_Service_Account_Exporter|null */
    protected static $_accountExporter = null;

    /** @var XFE_Carrier_Model_Service_FtpAccount_Importer|null */
    protected static $_ftpAccountImporter = null;

    /** @var XFE_Carrier_Model_Service_FtpAccount_Exporter|null */
    protected static $_ftpAccountExporter = null;

    /** @var XFE_Carrier_Model_Service_Account_CustomFieldService|null */
    protected static $_accountCustomFieldService = null;

    /** @var XFE_Carrier_Model_Service_FtpAccount_CustomFieldService|null */
    protected static $_ftpAccountCustomFieldService = null;

    /** @var XFE_Carrier_Model_Service_CustomAttributeService|null */
    protected static $_customAttributeService = null;

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
     * FTP账号服务 (1.0.10+)。
     *
     * @return XFE_Carrier_Model_Service_FtpAccount
     */
    public static function ftpAccount()
    {
        if (self::$_ftpAccount === null) {
            self::$_ftpAccount = XFE_Carrier_Model_Service_FtpAccount::instance();
        }
        return self::$_ftpAccount;
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
     * CSV importer for xfe_carrier_carrier (1.0.13+).
     *
     * @return XFE_Carrier_Model_Service_Importer
     */
    public static function importer()
    {
        if (self::$_importer === null) {
            self::$_importer = XFE_Carrier_Model_Service_Importer::instance();
        }
        return self::$_importer;
    }

    /**
     * 承运商规则批量导入服务。
     *
     * @return XFE_Carrier_Model_Service_Rule_Importer
     */
    public static function ruleImporter()
    {
        if (self::$_ruleImporter === null) {
            self::$_ruleImporter = XFE_Carrier_Model_Service_Rule_Importer::instance();
        }
        return self::$_ruleImporter;
    }

    /**
     * 承运商规则批量导出服务。
     *
     * @return XFE_Carrier_Model_Service_Rule_Exporter
     */
    public static function ruleExporter()
    {
        if (self::$_ruleExporter === null) {
            self::$_ruleExporter = XFE_Carrier_Model_Service_Rule_Exporter::instance();
        }
        return self::$_ruleExporter;
    }

    /**
     * 承运商账号批量导入服务。
     *
     * @return XFE_Carrier_Model_Service_Account_Importer
     */
    public static function accountImporter()
    {
        if (self::$_accountImporter === null) {
            self::$_accountImporter = XFE_Carrier_Model_Service_Account_Importer::instance();
        }
        return self::$_accountImporter;
    }

    /**
     * 承运商账号批量导出服务。
     *
     * @return XFE_Carrier_Model_Service_Account_Exporter
     */
    public static function accountExporter()
    {
        if (self::$_accountExporter === null) {
            self::$_accountExporter = XFE_Carrier_Model_Service_Account_Exporter::instance();
        }
        return self::$_accountExporter;
    }

    /**
     * 承运商 FTP账号批量导入服务。
     *
     * @return XFE_Carrier_Model_Service_FtpAccount_Importer
     */
    public static function ftpAccountImporter()
    {
        if (self::$_ftpAccountImporter === null) {
            self::$_ftpAccountImporter = XFE_Carrier_Model_Service_FtpAccount_Importer::instance();
        }
        return self::$_ftpAccountImporter;
    }

    /**
     * 承运商 FTP账号批量导出服务。
     *
     * @return XFE_Carrier_Model_Service_FtpAccount_Exporter
     */
    public static function ftpAccountExporter()
    {
        if (self::$_ftpAccountExporter === null) {
            self::$_ftpAccountExporter = XFE_Carrier_Model_Service_FtpAccount_Exporter::instance();
        }
        return self::$_ftpAccountExporter;
    }

    /**
     * 承运商主账号自定义字段服务 (1.0.14+)。
     *
     * @return XFE_Carrier_Model_Service_Account_CustomFieldService
     */
    public static function accountCustomFieldService()
    {
        if (self::$_accountCustomFieldService === null) {
            self::$_accountCustomFieldService = XFE_Carrier_Model_Service_Account_CustomFieldService::instance();
        }
        return self::$_accountCustomFieldService;
    }

    /**
     * 承运商 FTP 账号自定义字段服务 (1.0.14+)。
     *
     * @return XFE_Carrier_Model_Service_FtpAccount_CustomFieldService
     */
    public static function ftpAccountCustomFieldService()
    {
        if (self::$_ftpAccountCustomFieldService === null) {
            self::$_ftpAccountCustomFieldService = XFE_Carrier_Model_Service_FtpAccount_CustomFieldService::instance();
        }
        return self::$_ftpAccountCustomFieldService;
    }

    /**
     * 自定义属性中央管控服务 (1.0.15+)。
     * 4 分类(承运商/账号/FTP/LOGO)共享一个属性定义表,统一管理增删改 + Im/Ex。
     *
     * @return XFE_Carrier_Model_Service_CustomAttributeService
     */
    public static function customAttributeService()
    {
        if (self::$_customAttributeService === null) {
            self::$_customAttributeService = XFE_Carrier_Model_Service_CustomAttributeService::instance();
        }
        return self::$_customAttributeService;
    }

    /**
     * Test hook: replace any service.
     *
     * @param string $name 'logo' | 'account' | 'ftpAccount' | 'rule' | 'importer' | 'ruleImporter' | 'ruleExporter' | 'accountImporter' | 'accountExporter' | 'ftpAccountImporter' | 'ftpAccountExporter' | 'accountCustomFieldService' | 'ftpAccountCustomFieldService' | 'customAttributeService'
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
