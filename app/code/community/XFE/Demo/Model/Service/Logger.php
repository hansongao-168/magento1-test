<?php

/**
 * XFE_Demo_Model_Service_Logger
 *
 * 教学示例 service 2:把日志写到 Magento var/log。
 */
class XFE_Demo_Model_Service_Logger
{
    /**
     * @param string $level  info|debug|warn|error
     * @param string $msg
     * @return bool
     */
    public function log($level, $msg)
    {
        if (class_exists('Mage', false)) {
            Mage::log(sprintf('[%s] %s', $level, $msg), null, 'xfe_demo.log');
        }
        return true;
    }
}
