<?php
namespace wapmorgan\UnifiedArchive;

interface AbstractArchive
{
    /**
     * @param $fileName
     * @return AbstractArchive|null
     */
    static public function open($fileName);

    /**
     * @param string|string[]|array[]
     * @param $archiveName
     * @return mixed
     */
    static public function archiveFiles($fileOrFiles, $archiveName);

    /**
     * AbstractArchive constructor.
     * @param $fileName
     * @param $type
     */
    public function __construct($fileName, $type);

    /**
     * @return array
     */
    public function getFileNames();

    /**
     * @param $fileName
     * @return ArchiveEntry
     */
    public function getFileData($fileName);

    /**
     * @param $fileName
     * @return string|bool
     */
    public function getFileContent($fileName);

    /**
     * @return array
     */
    public function getHierarchy();

    /**
     * @param $outputFolder
     * @param string|array|null $files
     * @return bool|int
     */
    public function extractFiles($outputFolder, $files = null);

    /**
     * @param $fileOrFiles
     * @return bool|int
     */
    public function deleteFiles($fileOrFiles);

    /**
     * @param $fileOrFiles
     * @return int|bool
     */
    public function addFiles($fileOrFiles);

    /**
     * @return int
     */
    public function countFiles();

    /**
     * @return int
     */
    public function getArchiveSize();

    /**
     * @return string
     */
    public function getArchiveType();

    /**
     * @return int
     */
    public function countCompressedFilesSize();

    /**
     * @return int
     */
    public function countUncompressedFilesSize();
}
