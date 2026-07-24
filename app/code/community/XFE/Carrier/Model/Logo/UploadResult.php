<?php

/**
 * Plain DTO returned by LogoService::upload().
 *
 * Decouples callers from the logo model / filesystem layout so that the
 * controller / block layer can render preview without reaching into Service.
 */
class XFE_Carrier_Model_Logo_UploadResult
{
    /** @var bool */
    protected $_success = false;

    /** @var string */
    protected $_message = '';

    /** @var string|null Relative media path (web path after media/) */
    protected $_path = null;

    /** @var int|null */
    protected $_width = null;

    /** @var int|null */
    protected $_height = null;

    public function __construct(array $data = array())
    {
        if (isset($data['success'])) { $this->_success = (bool)$data['success']; }
        if (isset($data['message'])) { $this->_message = (string)$data['message']; }
        if (isset($data['path']))    { $this->_path = $data['path']; }
        if (isset($data['width']))   { $this->_width = (int)$data['width']; }
        if (isset($data['height']))  { $this->_height = (int)$data['height']; }
    }

    public function isSuccess() { return $this->_success; }
    public function getMessage() { return $this->_message; }
    public function getPath()    { return $this->_path; }
    public function getWidth()   { return $this->_width; }
    public function getHeight()  { return $this->_height; }

    /**
     * @return array
     */
    public function toArray()
    {
        return array(
            'success' => $this->_success,
            'message' => $this->_message,
            'path'    => $this->_path,
            'width'   => $this->_width,
            'height'  => $this->_height,
        );
    }
}
