<?php

/**
 * LogoService
 *
 * Owns the full lifecycle of a carrier logo:
 *   - validate uploaded file
 *   - persist file to disk via File\Store
 *   - process image (PNG / SVG) via Image\Processor
 *   - persist DB record via XFE_Carrier_Model_Carrier_Logo
 *   - delete (with file) by logo id
 *   - delete all logos for a carrier (used before carrier delete)
 *
 * Dependencies (one-way):
 *   LogoService -> File\Store      (filesystem layout)
 *   LogoService -> Image\Processor (pure image ops)
 *   LogoService -> Carrier_Logo    (DB entity)
 *
 * Callers pass only a carrier id and the file payload; the service does
 * not need the Carrier model.
 */
class XFE_Carrier_Model_Service_Logo
{
    /** @var self|null */
    protected static $_instance = null;

    /** @var XFE_Carrier_Model_Logo_File_Store */
    protected $_fileStore;

    /** @var XFE_Carrier_Model_Service_Image_Processor */
    protected $_imageProcessor;

    public static function instance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self(
                new XFE_Carrier_Model_Logo_File_Store(),
                new XFE_Carrier_Model_Service_Image_Processor()
            );
        }
        return self::$_instance;
    }

    public static function setInstance($instance)
    {
        self::$_instance = $instance;
    }

    public function __construct(
        XFE_Carrier_Model_Logo_File_Store $fileStore,
        XFE_Carrier_Model_Service_Image_Processor $imageProcessor
    ) {
        $this->_fileStore = $fileStore;
        $this->_imageProcessor = $imageProcessor;
    }

    /**
     * Process an uploaded file, write it to disk and create a logo row.
     * Returns a UploadResult DTO. Never throws - failures are encoded in
     * the DTO so the controller can show a friendly message.
     *
     * @param int   $carrierId
     * @param array $fileData   Standard $_FILES row
     * @param string|null $label
     * @param string|null $type   main / mobile / alt / ...
     * @param int|null    $ruleId Optional rule that should select this logo
     * @return XFE_Carrier_Model_Logo_UploadResult
     */
    public function upload($carrierId, array $fileData, $label = null, $type = null, $ruleId = null)
    {
        $carrierId = (int)$carrierId;
        if (!$carrierId) {
            return new XFE_Carrier_Model_Logo_UploadResult(array(
                'success' => false,
                'message' => Mage::helper('xfe_carrier')->__('Missing carrier id.'),
            ));
        }
        if (empty($fileData['tmp_name']) || empty($fileData['name'])
            || !is_uploaded_file($fileData['tmp_name'])) {
            return new XFE_Carrier_Model_Logo_UploadResult(array(
                'success' => false,
                'message' => Mage::helper('xfe_carrier')->__('Upload failed.'),
            ));
        }

        $ext = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        if (!$this->_imageProcessor->isExtensionAllowed($ext)) {
            return new XFE_Carrier_Model_Logo_UploadResult(array(
                'success' => false,
                'message' => Mage::helper('xfe_carrier')->__('Unsupported image format.'),
            ));
        }

        $this->_fileStore->ensureCarrierDir($carrierId);
        $isSvg = ($ext === 'svg');
        $filename = uniqid('logo_', true) . '.' . ($isSvg ? 'svg' : 'png');
        $paths = $this->_fileStore->buildPaths($carrierId, $filename);

        $width = null;
        $height = null;

        if ($isSvg) {
            if (!@copy($fileData['tmp_name'], $paths['absolute'])) {
                return new XFE_Carrier_Model_Logo_UploadResult(array(
                    'success' => false,
                    'message' => Mage::helper('xfe_carrier')->__('Could not store SVG.'),
                ));
            }
            $svgSize = $this->_imageProcessor->readSvgSize($paths['absolute']);
            $width  = $svgSize['width'];
            $height = $svgSize['height'];
        } else {
            if (!$this->_imageProcessor->isGdAvailable()) {
                return new XFE_Carrier_Model_Logo_UploadResult(array(
                    'success' => false,
                    'message' => Mage::helper('xfe_carrier')->__('GD library unavailable.'),
                ));
            }
            $gd = $this->_imageProcessor->loadGdResource($fileData['tmp_name'], $ext);
            if (!$gd) {
                return new XFE_Carrier_Model_Logo_UploadResult(array(
                    'success' => false,
                    'message' => Mage::helper('xfe_carrier')->__('Could not read image.'),
                ));
            }
            $width  = imagesx($gd);
            $height = imagesy($gd);
            if (!$this->_imageProcessor->saveAsPng($gd, $paths['absolute'])) {
                return new XFE_Carrier_Model_Logo_UploadResult(array(
                    'success' => false,
                    'message' => Mage::helper('xfe_carrier')->__('Could not save image.'),
                ));
            }
        }

        $logo = Mage::getModel('xfe_carrier/carrier_logo');
        $logo->setData(array(
            'carrier_id' => $carrierId,
            'rule_id'    => ($ruleId === null || $ruleId === '') ? null : (int)$ruleId,
            'label'      => $label,
            'logo_type'  => $type,
            'sort_order' => $this->_getNextSortOrder($carrierId),
            'path'       => $paths['relative'],
            'width'      => $width,
            'height'     => $height,
        ))->save();

        return new XFE_Carrier_Model_Logo_UploadResult(array(
            'success' => true,
            'path'    => $paths['relative'],
            'width'   => $width,
            'height'  => $height,
        ));
    }

    /**
     * Delete a single logo (record + file). Refuses if not owned by $carrierId.
     *
     * @param int $carrierId
     * @param int $logoId
     * @return bool
     */
    public function deleteById($carrierId, $logoId)
    {
        $carrierId = (int)$carrierId;
        $logoId    = (int)$logoId;
        if (!$logoId || !$carrierId) {
            return false;
        }

        $logo = Mage::getModel('xfe_carrier/carrier_logo')->load($logoId);
        if (!$logo->getId() || (int)$logo->getCarrierId() !== $carrierId) {
            return false;
        }

        $this->_fileStore->deleteByRelativePath($logo->getPath());
        $logo->delete();
        return true;
    }

    /**
     * Delete every logo that belongs to a carrier (used before carrier delete).
     *
     * @param int $carrierId
     * @return int
     */
    public function deleteAllForCarrier($carrierId)
    {
        $carrierId = (int)$carrierId;
        if (!$carrierId) {
            return 0;
        }

        $logos = Mage::getModel('xfe_carrier/carrier_logo')->getCollection()
            ->addFieldToFilter('carrier_id', $carrierId);

        $count = 0;
        foreach ($logos as $logo) {
            $this->_fileStore->deleteByRelativePath($logo->getPath());
            $logo->delete();
            $count++;
        }

        $this->_fileStore->removeCarrierDirIfEmpty($carrierId);
        return $count;
    }

    /**
     * @param int $carrierId
     * @return int
     */
    protected function _getNextSortOrder($carrierId)
    {
        $resource = Mage::getSingleton('core/resource');
        $read   = $resource->getConnection('core_read');
        $table  = $resource->getTableName('xfe_carrier/carrier_logo');
        $max    = (int)$read->fetchOne(
            $read->select()
                ->from($table, array(new Zend_Db_Expr('MAX(sort_order)')))
                ->where('carrier_id = ?', $carrierId)
        );
        return $max + 10;
    }
}
