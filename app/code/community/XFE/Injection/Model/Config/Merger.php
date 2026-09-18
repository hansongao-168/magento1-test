<?php

/**
 * XFE_Injection_Model_Config_Merger
 *
 * 把单个或多个 injection.xml 合并到 Registry。
 *
 * 合并策略:
 *   - hooks / services:严格去重,重复抛 DuplicateHookException / DuplicateServiceException
 *   - callings:同 callingId 后注册覆盖前注册(允许消费方覆盖默认)
 *
 * 启动期 Loader 调用 loadAll() 触发本类工作。
 *
 * 关联文档:docs/architecture/injection-api.md §3.5
 */
final class XFE_Injection_Model_Config_Merger
{
    /** @var XFE_Injection_Model_Registry */
    private $_registry;

    /** @var XFE_Injection_Model_Config_XmlReader */
    private $_reader;

    public function __construct()
    {
        $this->_registry = XFE_Injection_Model_Registry::getInstance();
        $this->_reader   = new XFE_Injection_Model_Config_XmlReader();
    }

    /**
     * 把一个 XML 文件合并到 Registry。
     *
     * @param string $moduleName
     * @param string $xmlContent
     */
    public function mergeFromXml($moduleName, $xmlContent)
    {
        $moduleName = (string) $moduleName;
        $xmlContent = (string) $xmlContent;

        if (trim($xmlContent) === '') {
            return;
        }

        $useErrors = libxml_use_internal_errors(true);
        $xml       = simplexml_load_string($xmlContent);
        libxml_clear_errors();
        libxml_use_internal_errors($useErrors);

        if ($xml === false) {
            throw new XFE_Injection_Domain_Exception_InvalidArgumentException(
                "Module[$moduleName] injection.xml is not valid XML"
            );
        }

        $parsed = $this->_reader->read($moduleName, $xml);

        foreach ($parsed['hooks'] as $hook) {
            $this->_registry->registerHook($hook);
        }
        foreach ($parsed['services'] as $svc) {
            $this->_registry->registerService($svc);
        }
        foreach ($parsed['callings'] as $call) {
            $this->_registry->registerCalling($call);
        }
    }

    /**
     * 把多份 XML 合并(顺序敏感)。
     *
     * @param array<string,string> $xmlSources moduleName => xmlContent
     */
    public function mergeMultiple(array $xmlSources)
    {
        foreach ($xmlSources as $moduleName => $xmlContent) {
            $this->mergeFromXml($moduleName, $xmlContent);
        }
    }
}
