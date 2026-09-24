<?php
/**
 * XFE_MagePlugin 短信验证码会话资源模型。
 */
class XFE_MagePlugin_Model_Resource_Sms_Verification extends XFE_MagePlugin_Model_Resource
{
    protected function _construct()
    {
        $this->_init('xfe_mageplugin/sms_verification', 'entity_id');
    }

    /**
     * 清理过期验证码会话。
     *
     * @return void
     */
    public function deleteExpired()
    {
        $write = $this->_getWriteAdapter();
        $write->delete($this->getMainTable(), array('expires_at < ?' => now()));
    }
}
