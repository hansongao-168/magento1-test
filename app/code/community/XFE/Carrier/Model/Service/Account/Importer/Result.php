<?php

/**
 * 结果对象：承运商账号批量导入。
 *
 * 计数器默认 0；addError 追加到（行号 => 消息）映射。
 * 供控制器渲染汇总块与逐行错误表使用。
 */
class XFE_Carrier_Model_Service_Account_Importer_Result
{
    /** @var int 新建账号数 */
    public $created = 0;

    /** @var int 更新账号数 */
    public $updated = 0;

    /** @var int 跳过行数 */
    public $skipped = 0;

    /** @var array<int, string> */
    protected $_errors = array();

    /**
     * @param int    $rowNo   1 起始的行号（0 表示文件级错误）
     * @param string $message
     */
    public function addError($rowNo, $message)
    {
        $this->_errors[(int)$rowNo] = (string)$message;
    }

    /**
     * @return array<int, string>
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->_errors);
    }
}
