<?php
/**
 * XFE_MagePlugin 重写 Mage_Admin_Model_User。
 *
 * 扩展后台用户模型：
 *   - 加载时注入手机号 / IP 白名单（供编辑表单显示）。
 *   - 保存后持久化手机号 / IP 白名单到独立表。
 *   - 删除后清理关联数据。
 *
 * 未修改核心文件，通过 <models><admin><rewrite><user> 生效。
 */
class XFE_MagePlugin_Model_Admin_User extends Mage_Admin_Model_User
{
    /**
     * 加载后注入扩展字段。
     *
     * @return Mage_Admin_Model_User
     */
    protected function _afterLoad()
    {
        parent::_afterLoad();

        if ($this->getId()) {
            // 扩展表可能尚未建立（数据库升级未跑）：优雅降级，不阻塞用户加载
            try {
                $phone = Mage::getResourceModel('xfe_mageplugin/admin_phone')->getPhoneByUserId($this->getId());
                if ($phone) {
                    $this->setData('xfe_phone', $phone);
                }

                $ips = $this->_loadWhitelistIps();
                if ($ips) {
                    $this->setData('xfe_ip_whitelist', implode("\n", $ips));
                }
            } catch (Exception $e) {
                Mage::log(
                    '[XFE_MagePlugin][User] 加载扩展字段失败（可能数据库表未建立）：' . $e->getMessage(),
                    null,
                    'xfe_mageplugin_ipguard.log',
                    true
                );
            }
        }

        return $this;
    }

    /**
     * 保存后持久化手机号 / IP 白名单。
     *
     * @return Mage_Admin_Model_User
     */
    protected function _afterSave()
    {
        parent::_afterSave();

        if ($this->getId()) {
            // 扩展表可能尚未建立：不阻塞用户保存
            try {
                $this->_savePhone();
                $this->_saveWhitelistIps();
            } catch (Exception $e) {
                Mage::log(
                    '[XFE_MagePlugin][User] 持久化扩展字段失败（可能数据库表未建立）：' . $e->getMessage(),
                    null,
                    'xfe_mageplugin_ipguard.log',
                    true
                );
            }
        }

        return $this;
    }

    /**
     * 删除后清理手机号 / IP 白名单。
     *
     * @return Mage_Admin_Model_User
     */
    protected function _afterDelete()
    {
        parent::_afterDelete();

        if ($this->getId()) {
            try {
                $write = Mage::getSingleton('core/resource')->getConnection('core_write');
                $resource = Mage::getSingleton('core/resource');
                $write->delete(
                    $resource->getTableName('xfe_mageplugin/admin_phone'),
                    array('user_id = ?' => (int)$this->getId())
                );
                $write->delete(
                    $resource->getTableName('xfe_mageplugin/admin_whitelist'),
                    array('user_id = ?' => (int)$this->getId())
                );
            } catch (Exception $e) {
                Mage::log(
                    '[XFE_MagePlugin][User] 删除扩展字段失败：' . $e->getMessage(),
                    null,
                    'xfe_mageplugin_ipguard.log',
                    true
                );
            }
        }

        return $this;
    }

    /**
     * 持久化手机号。
     *
     * @return void
     */
    protected function _savePhone()
    {
        $phone = trim((string)$this->getData('xfe_phone'));
        Mage::getResourceModel('xfe_mageplugin/admin_phone')->savePhone((int)$this->getId(), $phone);
    }

    /**
     * 持久化 IP 白名单（整体替换）。
     *
     * @return void
     */
    protected function _saveWhitelistIps()
    {
        $raw = (string)$this->getData('xfe_ip_whitelist');
        $ips = $this->_parseIps($raw);

        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $resource = Mage::getSingleton('core/resource');
        $table = $resource->getTableName('xfe_mageplugin/admin_whitelist');

        // 先清空该用户白名单，再写入
        $write->delete($table, array('user_id = ?' => (int)$this->getId()));

        $whitelist = Mage::getResourceModel('xfe_mageplugin/admin_whitelist');
        foreach ($ips as $ip) {
            $whitelist->addIp((int)$this->getId(), $ip, 0);
        }
    }

    /**
     * 读取当前用户白名单 IP 列表。
     *
     * @return array
     */
    protected function _loadWhitelistIps()
    {
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $resource = Mage::getSingleton('core/resource');
        $table = $resource->getTableName('xfe_mageplugin/admin_whitelist');
        $select = $read->select()
            ->from($table, 'ip')
            ->where('user_id = ?', (int)$this->getId())
            ->order('entity_id ASC');
        return (array)$read->fetchCol($select);
    }

    /**
     * 解析 IP 白名单文本（按行/逗号分隔，去空、去重）。
     *
     * @param string $raw
     * @return array
     */
    protected function _parseIps($raw)
    {
        $raw = str_replace(',', "\n", $raw);
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $ips = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && !in_array($line, $ips, true)) {
                $ips[] = $line;
            }
        }
        return $ips;
    }
}
