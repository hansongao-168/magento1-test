<?php

/**
 * Logo file storage.
 *
 * Owns ONLY the filesystem layout for carrier logos:
 *   - resolving absolute / relative paths
 *   - ensuring directories exist
 *   - unlinking a single file (idempotent)
 *
 * It does not process images, does not write to the DB. Those concerns are
 * owned by the image processor and the service layer respectively.
 */
class XFE_Carrier_Model_Logo_File_Store
{
    /**
     * Root directory under media/ for logo files.
     *
     * @return string
     */
    public function getRelativeRoot()
    {
        return 'xfe' . DS . 'carrier' . DS . 'logo';
    }

    /**
     * Absolute directory for a specific carrier's logos.
     *
     * @param int $carrierId
     * @return string
     */
    public function getCarrierAbsoluteDir($carrierId)
    {
        return Mage::getBaseDir('media') . DS . $this->getRelativeRoot() . DS . (int)$carrierId;
    }

    /**
     * Make sure the per-carrier directory exists.
     *
     * @param int $carrierId
     * @return string Absolute directory path
     */
    public function ensureCarrierDir($carrierId)
    {
        $dir = $this->getCarrierAbsoluteDir($carrierId);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Build the relative + absolute path for a new file (caller supplies
     * the filename including extension).
     *
     * @param int $carrierId
     * @param string $filename
     * @return array{relative:string,absolute:string}
     */
    public function buildPaths($carrierId, $filename)
    {
        $relative = $this->getRelativeRoot() . DS . (int)$carrierId . DS . $filename;
        $absolute = Mage::getBaseDir('media') . DS . $relative;
        return array('relative' => $relative, 'absolute' => $absolute);
    }

    /**
     * Delete a single file referenced by its stored relative path.
     * Returns true if the file did not exist OR was successfully removed.
     *
     * @param string $relativePath
     * @return bool
     */
    public function deleteByRelativePath($relativePath)
    {
        if (!$relativePath) {
            return true;
        }
        $absolute = Mage::getBaseDir('media') . DS . $relativePath;
        if (!file_exists($absolute)) {
            return true;
        }
        return (bool)@unlink($absolute);
    }

    /**
     * Best-effort: remove the per-carrier directory if it is now empty.
     * Used on carrier delete. Never throws.
     *
     * @param int $carrierId
     * @return void
     */
    public function removeCarrierDirIfEmpty($carrierId)
    {
        $dir = $this->getCarrierAbsoluteDir($carrierId);
        if (is_dir($dir)) {
            @rmdir($dir);
        }
    }
}
