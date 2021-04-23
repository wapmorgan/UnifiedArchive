<?php
namespace wapmorgan\UnifiedArchive\Drivers;

use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveCreationException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveModificationException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedArchiveException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\PclzipZipInterface;

abstract class BasicDriver
{
    const COMPRESSION_NONE = 0;
    const COMPRESSION_WEAK = 1;
    const COMPRESSION_AVERAGE = 2;
    const COMPRESSION_STRONG = 3;
    const COMPRESSION_MAXIMUM = 4;

    /**
     * @return array
     */
    public static function getSupportedFormats()
    {
        return [];
    }

    /**
     * @param $format
     * @return bool
     */
    public static function checkFormatSupport($format)
    {
        return false;
    }

    /**
     * @return string
     */
    public static function getDescription()
    {
        return null;
    }

    /**
     * @return string
     */
    public static function getInstallationInstruction()
    {
        return null;
    }

    /**
     * @param $format
     * @return bool
     */
    public static function canCreateArchive($format)
    {
        return false;
    }

    /**
     * @param $format
     * @return bool
     */
    public static function canAddFiles($format)
    {
        return false;
    }

    /**
     * @param $format
     * @return bool
     */
    public static function canDeleteFiles($format)
    {
        return false;
    }

    /**
     * @param $format
     * @return false
     */
    public static function canEncrypt($format)
    {
        return false;
    }

    /**
     * @param $format
     * @return false
     */
    public static function canStream($format)
    {
        return false;
    }

    /**
     * @param array $files
     * @param string $archiveFileName
     * @param string $archiveFormat
     * @param int $compressionLevel
     * @param null $password
     * @return int Number of archived files
     * @throws UnsupportedOperationException
     */
    public static function createArchive(array $files, $archiveFileName, $archiveFormat, $compressionLevel = self::COMPRESSION_AVERAGE, $password = null)
    {
        throw new UnsupportedOperationException();
    }

    /**
     * BasicDriver constructor.
     * @param string $archiveFileName
     * @param string $format
     * @param string|null $password Archive password for opening
     */
    abstract public function __construct($archiveFileName, $format, $password = null);

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
     * @return resource
     */
    abstract public function getFileStream($fileName);

    /**
     * @param $string
     * @return resource
     */
    public static function wrapStringInStream($string)
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, $string);
        rewind($resource);
        return $resource;
    }

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
    public function deleteFiles(array $files)
    {
        throw new UnsupportedOperationException();
    }

    /**
     * @param array $files
     * @return int Number of added files
     * @throws UnsupportedOperationException
     * @throws ArchiveModificationException
     */
    public function addFiles(array $files)
    {
        throw new UnsupportedOperationException();
    }

    /**
     * @param string $inArchiveName
     * @param string $content
     * @return bool
     * @throws UnsupportedOperationException
     * @throws ArchiveModificationException
     */
    abstract public function addFileFromString($inArchiveName, $content);

    /**
     * @return string|null
     */
    public function getComment()
    {
        return null;
    }

    /**
     * @param string|null $comment
     * @return null
     */
    public function setComment($comment)
    {
        return null;
    }
}