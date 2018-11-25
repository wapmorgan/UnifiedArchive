<?php
namespace wapmorgan\UnifiedArchive\Formats;

use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;

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
     * @return false|int Number of extracted files
     */
    abstract public function extractFiles($outputFolder, array $files);

    /**
     * @param string $outputFolder
     * @return false|int Number of extracted files
     */
    abstract public function extractArchive($outputFolder);

    /**
     * @param array $files
     * @return false|int Number of deleted files
     */
    abstract public function deleteFiles(array $files);

    /**
     * @param array $files
     * @return false|int Number of added files
     */
    abstract public function addFiles(array $files);

    /**
     * @param array $files
     * @param string $archiveFileName
     * @return false|int Number of archived files
     */
    abstract public static function createArchive(array $files, $archiveFileName);
}