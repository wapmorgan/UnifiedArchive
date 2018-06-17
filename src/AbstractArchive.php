<?php
namespace wapmorgan\UnifiedArchive;

interface AbstractArchive
{
    /**
     * Creates instance with right type.
     * @param  string $fileName Filename
     * @return AbstractArchive|null Returns AbstractArchive in case of successful
     * parsing of the file
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
     * Returns list of files
     * @return array List of files
     */
    public function getFileNames();

    /**
     * Checks that file exists in archive
     * @param string $fileName Name of file
     * @return boolean
     */
    public function isFileExists($fileName);

    /**
     * Returns file metadata
     * @param string $fileName
     * @return ArchiveEntry|bool
     */
    public function getFileData($fileName);

    /**
     * Returns file content
     * @param string $fileName
     * @return string|false
     */
    public function getFileContent($fileName);

    /**
     * Returns a resource for reading file from archive
     * @param string $fileName
     * @return resource|false
     */
    public function getFileResource($fileName);

    /**
     * Returns hierarchy
     * @return array
     */
    public function getHierarchy();

    /**
     * @param                   $outputFolder
     * @param string|array|null $files
     * @param bool              $expandFilesList
     *
     * @return bool|int
     */
    public function extractFiles($outputFolder, $files = null, $expandFilesList = false);

    /**
     * Updates existing archive by adding new files.
     * @param string[] $fileOrFiles
     * @return int|bool
     */
    public function addFiles($fileOrFiles);

    /**
     * Updates existing archive by removing files from it.
     *
     * @param string|string[] $fileOrFiles
     * @param bool            $expandFilesList
     *
     * @return bool|int
     */
    public function deleteFiles($fileOrFiles, $expandFilesList = false);

    /**
     * Counts number of files
     * @return int
     */
    public function countFiles();

    /**
     * Returns size of archive
     * @return int
     */
    public function getArchiveSize();

    /**
     * Returns type of archive
     * @return string
     */
    public function getArchiveType();

    /**
     * Counts size of all compressed data (in bytes)
     * @return int
     */
    public function countCompressedFilesSize();

    /**
     * Counts size of all uncompressed data (bytes)
     * @return int
     */
    public function countUncompressedFilesSize();
}
