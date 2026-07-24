<?php

/**
 * System Config Backend Model for Accounts JSON
 *
 * Handles encryption and decryption of sensitive fields (password, api_key)
 * within the accounts JSON stored in system configuration.
 *
 * The value stored in config is a JSON array of account objects:
 * [
 *   { "title": "Main", "login": "user", "password": "encrypted", "api_key": "encrypted", "account_number": "123" }
 * ]
 *
 * On save: encrypts 'password' and 'api_key' fields.
 * On load: decrypts 'password' and 'api_key' fields back to plaintext for editing.
 *
 * @category   Community
 * @package    XFE_DocumentUpload
 */
class XFE_DocumentUpload_Model_System_Config_Backend_Accounts extends Mage_Core_Model_Config_Data
{
	/**
	 * Encrypt sensitive fields before saving
	 *
	 * @return XFE_DocumentUpload_Model_System_Config_Backend_Accounts
	 */
	protected function _beforeSave()
	{
		$value = $this->getValue();

		if (is_string($value) && !empty($value)) {
			$accounts = @json_decode($value, true);
			if (is_array($accounts)) {
				$encryption = Mage::getModel('core/encryption');
				foreach ($accounts as &$account) {
					if (is_array($account)) {
						if (!empty($account['password'])) {
							$account['password'] = $this->_isEncrypted($account['password'])
								? $account['password']
								: $encryption->encrypt($account['password']);
						}
						if (!empty($account['api_key'])) {
							$account['api_key'] = $this->_isEncrypted($account['api_key'])
								? $account['api_key']
								: $encryption->encrypt($account['api_key']);
						}
						// Ensure required keys exist
						if (!isset($account['title'])) {
							$account['title'] = '';
						}
					}
				}
				$this->setValue(json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
			}
		}

		return parent::_beforeSave();
	}

	/**
	 * Decrypt sensitive fields after loading
	 *
	 * @return XFE_DocumentUpload_Model_System_Config_Backend_Accounts
	 */
	protected function _afterLoad()
	{
		$value = $this->getValue();

		if (is_string($value) && !empty($value)) {
			$accounts = @json_decode($value, true);
			if (is_array($accounts)) {
				$encryption = Mage::getModel('core/encryption');
				$modified = false;
				foreach ($accounts as &$account) {
					if (is_array($account)) {
						if (!empty($account['password']) && $this->_isEncrypted($account['password'])) {
							$account['password'] = $encryption->decrypt($account['password']);
							$modified = true;
						}
						if (!empty($account['api_key']) && $this->_isEncrypted($account['api_key'])) {
							$account['api_key'] = $encryption->decrypt($account['api_key']);
							$modified = true;
						}
					}
				}
				if ($modified) {
					$this->setValue(json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
				}
			}
		}

		return parent::_afterLoad();
	}

	/**
	 * Check if a string looks like an encrypted value (base64-encoded)
	 *
	 * @param string $value
	 * @return bool
	 */
	protected function _isEncrypted($value)
	{
		// Magento encrypted values are base64-encoded and typically longer than 20 chars
		return (bool)preg_match('/^[a-zA-Z0-9\/+]+=*$/', $value) && strlen($value) > 20;
	}
}
