<?php

/**
 * Image processor
 *
 * Single-responsibility: pure image-processing functions for the logo service.
 * It owns NO business knowledge of carriers, logos, or filesystem layout —
 * those concerns live in LogoService. Here we only:
 *   - create a GD resource from a temp file
 *   - persist (PNG / SVG) to absolute path
 *   - read SVG intrinsic size
 *
 * The class is intentionally stateless so it can be replaced/swapped
 * (Imagick, Glide, remote CDN, ...) without disturbing the service layer.
 */
class XFE_Carrier_Model_Service_Image_Processor
{
    /** @var string[] */
    protected $_allowedExtensions = array('jpg', 'jpeg', 'png', 'gif', 'svg');

    /**
     * Whether a GD library is available. PNG transparency requires it.
     *
     * @return bool
     */
    public function isGdAvailable()
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    /**
     * Whether the source file extension is acceptable.
     *
     * @param string $extension
     * @return bool
     */
    public function isExtensionAllowed($extension)
    {
        $extension = strtolower((string)$extension);
        return in_array($extension, $this->_allowedExtensions, true);
    }

    /**
     * Load a GD image from a file path.
     * Caller is responsible for imagedestroy() of the returned resource.
     *
     * @param string $absolutePath
     * @param string $extension Lower-case extension WITHOUT the dot
     * @return resource|false GD resource or false on failure
     */
    public function loadGdResource($absolutePath, $extension)
    {
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                return @imagecreatefromjpeg($absolutePath);
            case 'png':
                return @imagecreatefrompng($absolutePath);
            case 'gif':
                return @imagecreatefromgif($absolutePath);
            default:
                $bytes = @file_get_contents($absolutePath);
                if ($bytes === false) {
                    return false;
                }
                return @imagecreatefromstring($bytes);
        }
    }

    /**
     * Save a GD image as PNG to disk, destroying the resource.
     *
     * @param resource $gdResource
     * @param string $absolutePath
     * @return bool
     */
    public function saveAsPng($gdResource, $absolutePath)
    {
        $ok = (bool)@imagepng($gdResource, $absolutePath);
        @imagedestroy($gdResource);
        return $ok;
    }

    /**
     * Read an SVG file's intrinsic width / height.
     * Returns array('width' => int|null, 'height' => int|null).
     *
     * @param string $absolutePath
     * @return array
     */
    public function readSvgSize($absolutePath)
    {
        $width = null;
        $height = null;

        $xml = @simplexml_load_file($absolutePath);
        if ($xml) {
            $attrs = $xml->attributes();
            if (isset($attrs['width']))  { $width  = (int)$attrs['width'];  }
            if (isset($attrs['height'])) { $height = (int)$attrs['height']; }
            if ($width === null && isset($attrs['viewBox'])) {
                $parts = preg_split('/[\s,]+/', (string)$attrs['viewBox']);
                if (count($parts) >= 4) {
                    $width  = (int)$parts[2];
                    $height = (int)$parts[3];
                }
            }
        }

        return array('width' => $width, 'height' => $height);
    }
}
