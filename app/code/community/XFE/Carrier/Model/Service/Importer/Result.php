<?php

/**
 * Result object for XFE_Carrier_Model_Service_Importer.
 *
 * Counters default to 0; addError appends to a (row-number => message)
 * map. Used by the controller to render a summary block after upload.
 */
class XFE_Carrier_Model_Service_Importer_Result
{
    /** @var int */
    public $created = 0;

    /** @var int */
    public $updated = 0;

    /** @var int */
    public $skipped = 0;

    /** @var array<int, string> */
    protected $_errors = array();

    /**
     * @param int    $rowNo  1-indexed row number (0 = file-level error)
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

    /**
     * @return int
     */
    public function getTotal()
    {
        return $this->created + $this->updated + $this->skipped;
    }
}