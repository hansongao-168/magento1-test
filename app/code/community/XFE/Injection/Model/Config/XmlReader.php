<?php

/**
 * XFE_Injection_Model_Config_XmlReader
 *
 * 把 injection.xml 的 SimpleXMLElement 解析为 Domain 定义对象。
 *
 * 输入 XML 形态(参考 docs/architecture/injection-architecture.md §3):
 *   <config>
 *       <injection>
 *           <services>
 *               <service_xxx><class>XFE_A_Model_X</class><method>doY</method></service_xxx>
 *           </services>
 *           <hooks>
 *               <hook_yyy><description>...</description></hook_yyy>
 *           </hooks>
 *           <callings>
 *               <calling id="calling_zzz" hook="hook_yyy" service="service_xxx" method="doY">
 *                   <argument name="k1" from="context.x"/>
 *                   <argument name="k2" from="literal:true"/>
 *               </calling>
 *           </callings>
 *       </injection>
 *   </config>
 */
final class XFE_Injection_Model_Config_XmlReader
{
    /**
     * @param string $moduleName
     * @param SimpleXMLElement $xml
     * @return array 形如:
     *   'hooks'    => [HookDefinition, ...]
     *   'services' => [ServiceDefinition, ...]
     *   'callings' => [CallingDefinition, ...]
     */
    public function read($moduleName, SimpleXMLElement $xml)
    {
        $hooks    = array();
        $services = array();
        $callings = array();

        // Magento 的 config.xml 顶层结构: <config><modules>...</modules><injection>...</injection></config>
        // 但 <modules> 在 config.xml 里是模块自身版本,不是 injection 部分。
        $injectionNode = null;
        if (isset($xml->injection)) {
            $injectionNode = $xml->injection;
        }

        if ($injectionNode === null) {
            return array('hooks' => $hooks, 'services' => $services, 'callings' => $callings);
        }

        // --- hooks ---
        if (isset($injectionNode->hooks)) {
            foreach ($injectionNode->hooks->children() as $tagName => $hookNode) {
                // tag 形如 hook_xxx,但用户也可能用 <hook id="xxx">
                $hookId = $this->_extractNodeId($tagName, $hookNode, 'hook');
                if ($hookId === null) {
                    continue;
                }
                $desc = isset($hookNode->description) ? (string) $hookNode->description : '';
                $hooks[] = new XFE_Injection_Domain_HookDefinition($hookId, $desc, $moduleName);
            }
        }

        // --- services ---
        if (isset($injectionNode->services)) {
            foreach ($injectionNode->services->children() as $tagName => $svcNode) {
                $svcId = $this->_extractNodeId($tagName, $svcNode, 'service');
                if ($svcId === null) {
                    continue;
                }
                $class   = isset($svcNode->class)   ? (string) $svcNode->class   : '';
                $method  = isset($svcNode->method)  ? (string) $svcNode->method  : '';
                if ($class === '' || $method === '') {
                    throw new XFE_Injection_Domain_Exception_InvalidArgumentException(
                        "Module[$moduleName] service[$svcId] must have <class> and <method>"
                    );
                }
                $services[] = new XFE_Injection_Domain_ServiceDefinition($svcId, $class, $method, $moduleName);
            }
        }

        // --- callings ---
        if (isset($injectionNode->callings)) {
            foreach ($injectionNode->callings->calling as $callingNode) {
                /** @var SimpleXMLElement $callingNode */
                $callingId = (string) ($callingNode['id'] ?? '');
                $hookId    = (string) ($callingNode['hook'] ?? '');
                $svcId     = (string) ($callingNode['service'] ?? '');
                $method    = (string) ($callingNode['method'] ?? '');

                if ($hookId === '' || $svcId === '') {
                    throw new XFE_Injection_Domain_Exception_InvalidArgumentException(
                        "Module[$moduleName] calling must have hook+service attributes"
                    );
                }
                if ($method === '') {
                    $method = $svcId;
                }
                if ($callingId === '') {
                    $callingId = $hookId . '__' . $svcId . '__' . $method;
                }

                $args = array();
                foreach ($callingNode->argument as $argNode) {
                    $args[] = array(
                        'name' => (string) ($argNode['name'] ?? ''),
                        'from' => (string) ($argNode['from'] ?? ''),
                    );
                }

                $callings[] = new XFE_Injection_Domain_CallingDefinition(
                    $callingId, $hookId, $svcId, $method, $args, $moduleName
                );
            }
        }

        return array(
            'hooks'    => $hooks,
            'services' => $services,
            'callings' => $callings,
        );
    }

    /**
     * 提取子节点 ID。
     * 支持两种写法:
     *   1. 标签名本身就是 ID:<hook_my_hook>...</hook_my_hook>
     *   2. 属性 id:<hook id="my_hook">...</hook>
     *
     * @param string $tagName
     * @param SimpleXMLElement $node
     * @param string $prefix  如 'hook' 或 'service'
     * @return string|null
     */
    private function _extractNodeId($tagName, SimpleXMLElement $node, $prefix)
    {
        // 形式 1: 属性 id="..."(最高优先级,允许覆盖 tag name)
        if (isset($node['id']) && (string) $node['id'] !== '') {
            return (string) $node['id'];
        }
        // 形式 2: 标签名本身即 ID(如 <hook_xxx>、<service_xxx>)
        // 校验:必须以 prefix_ 开头,否则可能写错(比如漏了 prefix)
        $prefixWithUnderscore = $prefix . '_';
        if (strpos($tagName, $prefixWithUnderscore) === 0) {
            return $tagName;
        }
        throw new XFE_Injection_Domain_Exception_InvalidArgumentException(
            "Tag '$tagName' must start with '$prefixWithUnderscore' or define id attribute"
        );
    }
}
