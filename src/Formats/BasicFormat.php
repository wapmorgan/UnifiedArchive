<?php
namespace wapmorgan\UnifiedArchive\Formats;

use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveCreationException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveModificationException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedArchiveException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\PclzipZipInterface;

abstract class BasicFormat
{
    /**
     * BasicFormat constructor.
     * @param string $archiveFileName
     */
    abstract public function __construct($archiveFileName);

    /**
     * Returns summary about an archive.
     * Called after
     * - constructing
     * - addFiles()
     * - deleteFiles()
     * @return ArchiveInformation
     */
    abstract public function getArchiveInformation();

    /**
     * @return array
     */
    abstract public function getFileNames();

    /**
     * @param string $fileName
     * @return bool
     */
    abstract public function isFileExists($fileName);

    /**
     * @param string $fileName
     * @return ArchiveEntry|false
     */
    abstract public function getFileData($fileName);

    /**
     * @param string $fileName
     * @return string|false
     */
    abstract public function getFileContent($fileName);

    /**
     * @param string $fileName
     * @return bool|resource|string
     */
    abstract public function getFileResource($fileName);

    /**
     * @param string $outputFolder
     * @param array  $files
     * @return int Number of extracted files
     * @throws ArchiveExtractionException
     */
    abstract public function extractFiles($outputFolder, array $files);

    /**
     * @param string $outputFolder
     * @return int Number of extracted files
     * @throws ArchiveExtractionException
     */
    abstract public function extractArchive($outputFolder);

    /**
     * @param array $files
     * @return false|int Number of deleted files
     * @throws UnsupportedOperationException
     * @throws ArchiveModificationException
     */
    abstract public function deleteFiles(array $files);

    /**
     * @param array $files
     * @return int Number of added files
     * @throws UnsupportedOperationException
     * @throws ArchiveModificationException
     */
    abstract public function addFiles(array $files);

    /**
     * @param array $files
     * @param string $archiveFileName
     * @return int Number of archived files
     * @throws UnsupportedOperationException
     * @throws ArchiveCreationException
     */
    public static function createArchive(array $files, $archiveFileName) {
        throw new UnsupportedOperationException();
    }

    /**
     * @return bool
     */
    public static function canCreateArchive()
    {
        return false;
    }

    /**
     * @return bool
     */
    public static function canAddFiles()
    {
        return false;
    }

    /**
     * @return bool
     */
    public static function canDeleteFiles()
    {
        return false;
    }

    /**
     * @throws UnsupportedOperationException
     * @return PclzipZipInterface
     */
    public function getPclZip()
    {
        throw new UnsupportedOperationException('Format '.get_class($this).' does not support PclZip-interface');
    }
}