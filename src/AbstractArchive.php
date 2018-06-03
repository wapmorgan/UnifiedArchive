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
     * @example If passed just one string:
     *  1. `archiveFiles('path/to/filename.txt', 'archive.zip')` => file will be stored as 'filename.txt' in the root of archive
     *  2. `archiveFiles('path/to/dir', 'archive.zip')` => directory contents will be stored in the root of archive
     * @example If passed array [key => value]:
     *  ```
     *  archiveFiles([
     *          'path/to/filename.txt',
     *          'path/to/dir',
     *          'path/to/another_filename.txt' => 'filename.txt',
     *          'path/to/another_dir' => 'directory',
     *          'path/to/third_dir' => '',
     *      ], 'archive.zip')
     *  ``` => for 2 first entries catalogs structure will be saved, 3 and 4 will be saved as `filename.txt` and `directory` respectively, all files from 5 directory will be stored in archive root
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
