<?php
namespace wapmorgan\UnifiedArchive;

use InvalidArgumentException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveCreationException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveModificationException;
use wapmorgan\UnifiedArchive\Exceptions\EmptyFileListException;
use wapmorgan\UnifiedArchive\Exceptions\FileAlreadyExistsException;
use wapmorgan\UnifiedArchive\Exceptions\NonExistentArchiveFileException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedArchiveException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\Formats\BasicDriver;

/**
 * Class which represents archive in one of supported formats.
 */
class UnifiedArchive
{
    const VERSION = '1.1.0';

    /** @var string Type of current archive */
    protected $format;

    /** @var BasicDriver Adapter for current archive */
    protected $archive;

    /** @var array List of files in current archive */
    protected $files;

    /** @var int Number of files in archive */
    protected $filesQuantity;

    /** @var int Cumulative size of uncompressed files */
    protected $uncompressedFilesSize;

    /** @var int Cumulative size of compressed files */
    protected $compressedFilesSize;

    /** @var int Total size of archive file */
    protected $archiveSize;
    /**
     * @var null
     */
    protected $password;

    /**
     * Creates a UnifiedArchive instance for passed archive
     *
     * @param string $fileName Archive filename
     * @param null $password
     * @return UnifiedArchive|null Returns UnifiedArchive in case of successful reading of the file
     * @throws UnsupportedOperationException
     */
    public static function open($fileName, $password = null)
    {
        if (!file_exists($fileName) || !is_readable($fileName)) {
            throw new InvalidArgumentException('Could not open file: ' . $fileName);
        }

        $format = Formats::detectArchiveFormat($fileName);
        if (!Formats::canOpen($format)) {
            return null;
        }

        return new static($fileName, $format, $password);
    }

    /**
     * Checks whether archive can be opened with current system configuration
     *
     * @param string $fileName Archive filename
     * @return bool
     */
    public static function canOpen($fileName)
    {
        $format = Formats::detectArchiveFormat($fileName);
        return $format !== false && Formats::canOpen($format);
    }

    /**
     * Opens the file as one of supported formats
     *
     * @param string $fileName Archive filename
     * @param string $format Archive type
     * @param string|null $password
     * @throws UnsupportedOperationException
     */
    public function __construct($fileName, $format, $password = null)
    {
        $driver = Formats::getFormatDriver($format);

        $this->format = $format;
        $this->archiveSize = filesize($fileName);
        $this->password = $password;

        /** @var BasicDriver archive */
        $this->archive = new $driver($fileName, $format, $password);
        $this->scanArchive();
    }

    /**
     * Rescans array after modification
     */
    protected function scanArchive()
    {
        $information = $this->archive->getArchiveInformation();
        $this->files = $information->files;
        $this->compressedFilesSize = $information->compressedFilesSize;
        $this->uncompressedFilesSize = $information->uncompressedFilesSize;
        $this->filesQuantity = count($information->files);
    }

    /**
     * Closes archive
     */
    public function __destruct()
    {
        unset($this->archive);
    }

    /**
     * Returns an instance of class implementing PclZipOriginalInterface
     * interface.
     *
     * @return PclzipZipInterface Returns an instance of a class implementing PclZipOriginalInterface
     * @throws UnsupportedOperationException
     */
    public function getPclZipInterface()
    {
        return $this->archive->getPclZip();
    }

    /**
     * Counts number of files
     *
     * @return int
     */
    public function countFiles()
    {
        return $this->filesQuantity;
    }

    /**
     * Counts cumulative size of all uncompressed data (bytes)
     *
     * @return int
     */
    public function countUncompressedFilesSize()
    {
        return $this->uncompressedFilesSize;
    }

    /**
     * Returns size of archive file in bytes
     *
     * @return int
     */
    public function getArchiveSize()
    {
        return $this->archiveSize;
    }

    /**
     * Returns type of archive
     *
     * @return string One of class constants
     */
    public function getArchiveFormat()
    {
        return $this->format;
    }

    /**
     * Counts cumulative size of all compressed data (in bytes)
     *
     * @return int
     */
    public function countCompressedFilesSize()
    {
        return $this->compressedFilesSize;
    }

    /**
     * Returns list of files, excluding folders.
     *
     * Paths is present in unix-style (with forward slash - /).
     *
     * @return array List of files
     */
    public function getFileNames()
    {
        return array_values($this->files);
    }

    /**
     * Checks that file exists in archive
     *
     * @param string $fileName File name in archive
     * @return bool
     */
    public function isFileExists($fileName)
    {
        return in_array($fileName, $this->files, true);
    }

    /**
     * Returns file metadata of file in archive
     *
     * @param string $fileName File name in archive
     * @return ArchiveEntry
     * @throws NonExistentArchiveFileException
     */
    public function getFileData($fileName)
    {
        if (!in_array($fileName, $this->files, true)) {
            throw new NonExistentArchiveFileException('File ' . $fileName . ' does not exist in archive');
        }

        return $this->archive->getFileData($fileName);
    }

    /**
     * Returns full file content as string
     *
     * @param string $fileName File name in archive
     * @return string
     * @throws NonExistentArchiveFileException
     */
    public function getFileContent($fileName)
    {
        if (!in_array($fileName, $this->files, true)) {
            throw new NonExistentArchiveFileException('File ' . $fileName . ' does not exist in archive');
        }

        return $this->archive->getFileContent($fileName);
    }

    /**
     * Returns a resource for reading file from archive
     *
     * @param string $fileName File name in archive
     * @return resource
     * @throws NonExistentArchiveFileException
     */
    public function getFileResource($fileName)
    {
        if (!in_array($fileName, $this->files, true)) {
            throw new NonExistentArchiveFileException('File ' . $fileName . ' does not exist in archive');
        }

        return $this->archive->getFileResource($fileName);
    }

    /**
     * Unpacks files to disk
     *
     * @param string $outputFolder Extraction output dir
     * @param string|array|null $files One file or files list or null to extract all content.
     * @param bool $expandFilesList Whether paths like 'src/' should be expanded to all files inside 'src/' dir or not.
     * @return int Number of extracted files
     * @throws EmptyFileListException
     * @throws ArchiveExtractionException
     */
    public function extractFiles($outputFolder, $files = null, $expandFilesList = false)
    {
        if ($files !== null) {
            if (is_string($files)) {
                $files = [$files];
            }

            if ($expandFilesList) {
                $files = static::expandFileList($this->files, $files);
            }

            if (empty($files)) {
                throw new EmptyFileListException('Files list is empty!');
            }

            return $this->archive->extractFiles($outputFolder, $files);
        }

        return $this->archive->extractArchive($outputFolder);
    }

    /**
     * Updates existing archive by removing files from it
     *
     * Only 7zip and zip types support deletion.
     * @param string|string[] $fileOrFiles
     * @param bool $expandFilesList
     *
     * @return bool|int
     * @throws EmptyFileListException
     * @throws UnsupportedOperationException
     * @throws ArchiveModificationException
     */
    public function deleteFiles($fileOrFiles, $expandFilesList = false)
    {
        $fileOrFiles = is_string($fileOrFiles) ? [$fileOrFiles] : $fileOrFiles;

        if ($expandFilesList && $fileOrFiles !== null) {
            $fileOrFiles = static::expandFileList($this->files, $fileOrFiles);
        }

        if (empty($fileOrFiles)) {
            throw new EmptyFileListException('Files list is empty!');
        }

        $result = $this->archive->deleteFiles($fileOrFiles);
        $this->scanArchive();

        return $result;
    }

    /**
     * Updates existing archive by adding new files
     *
     * @param string[] $fileOrFiles See [[archiveFiles]] method for file list format.
     * @return int|bool Number of added files
     * @throws ArchiveModificationException
     * @throws EmptyFileListException
     * @throws UnsupportedOperationException
     */
    public function addFiles($fileOrFiles)
    {
        $files_list = static::createFilesList($fileOrFiles);

        if (empty($files_list))
            throw new EmptyFileListException('Files list is empty!');

        $result = $this->archive->addFiles($files_list);
        $this->scanArchive();
        return $result;
    }

    /**
     * Adds file into archive
     *
     * @param string $file File name to be added
     * @param string|null $inArchiveName If not passed, full path will be preserved.
     * @return bool
     * @throws ArchiveModificationException
     * @throws EmptyFileListException
     * @throws UnsupportedOperationException
     */
    public function addFile($file, $inArchiveName = null)
    {
        if (!is_file($file))
            throw new InvalidArgumentException($file.' is not a valid file to add in archive');

        return ($inArchiveName !== null
                ? $this->addFiles([$file => $inArchiveName])
                : $this->addFiles([$file])) === 1;
    }

    /**
     * @param string $inArchiveName
     * @param string $content
     * @return bool
     * @throws ArchiveModificationException
     * @throws UnsupportedOperationException
     */
    public function addFileFromString($inArchiveName, $content)
    {
        $result = $this->archive->addFileFromString($inArchiveName, $content);
        $this->scanArchive();
        return $result;
    }

    /**
     * Adds directory contents to archive
     *
     * @param string $directory
     * @param string|null $inArchivePath If not passed, full paths will be preserved.
     * @return bool
     * @throws ArchiveModificationException
     * @throws EmptyFileListException
     * @throws UnsupportedOperationException
     */
    public function addDirectory($directory, $inArchivePath = null)
    {
        if (!is_dir($directory) || !is_readable($directory))
            throw new InvalidArgumentException($directory.' is not a valid directory to add in archive');

        return ($inArchivePath !== null
                ? $this->addFiles([$directory => $inArchivePath])
                : $this->addFiles([$inArchivePath])) > 0;
    }

    public function getDriverType()
    {
        return get_class($this->archive);
    }

    public function getDriver()
    {
        return $this->archive;
    }

    /**
     * Prepare files list for archiving
     *
     * @param string $fileOrFiles File of list of files. See [[archiveFiles]] for details.
     * @param string $archiveName File name of archive. See [[archiveFiles]] for details.
     * @return array An array containing entries:
     * - totalSize (int) - size in bytes for all files
     * - numberOfFiles (int) - quantity of files
     * - files (array) - list of files prepared for archiving
     * - type (string) - prepared format for archive. One of class constants
     * @throws EmptyFileListException
     * @throws UnsupportedArchiveException
     */
    public static function prepareForArchiving($fileOrFiles, $archiveName)
    {
        $archiveType = Formats::detectArchiveFormat($archiveName, false);

        if ($archiveType === false)
            throw new UnsupportedArchiveException('Could not detect archive type for name "'.$archiveName.'"');

        $files_list = static::createFilesList($fileOrFiles);

        if (empty($files_list))
            throw new EmptyFileListException('Files list is empty!');

        $totalSize = 0;
        foreach ($files_list as $fn) {
            $totalSize += filesize($fn);
        }

        return [
            'totalSize' => $totalSize,
            'numberOfFiles' => count($files_list),
            'files' => $files_list,
            'type' => $archiveType,
        ];
    }

    /**
     * Creates an archive with passed files list
     *
     * @param string|string[]|array<string,string> $fileOrFiles List of files. Can be one of three formats:
     *                             1. A string containing path to file or directory.
     *                                  File will have it's basename.
     *                                  `UnifiedArchive::archiveFiles('/etc/php.ini', 'archive.zip)` will store
     * file with 'php.ini' name.
     *                                  Directory contents will be stored in archive root.
     *                                  `UnifiedArchive::archiveFiles('/var/log/', 'archive.zip')` will store all
     * directory contents in archive root.
     *                             2. An array with strings containing pats to files or directories.
     *                                  Files and directories will be stored with full paths.
     *                                  `UnifiedArchive::archiveFiles(['/etc/php.ini', '/var/log/'], 'archive.zip)`
     * will preserve full paths.
     *                             3. An array with strings where keys are strings.
     *                                  Files will have name from key.
     *                                  Directories contents will have prefix from key.
     *                                  `UnifiedArchive::archiveFiles(['doc.txt' => 'very_long_name_of_document.txt',
     *  'static' => '/var/www/html/static/'], 'archive.zip')`
     *
     * @param string $archiveName File name of archive. Type of archive will be determined by it's name.
     * @param int $compressionLevel Level of compression
     * @return int Count of stored files is returned.
     * @throws FileAlreadyExistsException
     * @throws UnsupportedOperationException
     */
    public static function archiveFiles($fileOrFiles, $archiveName, $compressionLevel = BasicDriver::COMPRESSION_AVERAGE)
    {
        if (file_exists($archiveName))
            throw new FileAlreadyExistsException('Archive '.$archiveName.' already exists!');

        $info = static::prepareForArchiving($fileOrFiles, $archiveName);

        if (!Formats::canCreate($info['type']))
            throw new UnsupportedArchiveException('Unsupported archive type: '.$info['type'].' of archive '.$archiveName);

        /** @var BasicDriver $handler_class */
        $driver = Formats::getFormatDriver($info['type'], true);

        return $driver::createArchive($info['files'], $archiveName, $compressionLevel);
    }

    /**
     * Creates an archive with one file
     *
     * @param string $file
     * @param string $archiveName
     * @param int $compressionLevel Level of compression
     * @return bool
     * @throws FileAlreadyExistsException
     * @throws UnsupportedOperationException
     */
    public static function archiveFile($file, $archiveName, $compressionLevel = BasicDriver::COMPRESSION_AVERAGE)
    {
        if (!is_file($file)) {
            throw new InvalidArgumentException($file . ' is not a valid file to archive');
        }

        return static::archiveFiles($file, $archiveName, $compressionLevel) === 1;
    }

    /**
     * Creates an archive with full directory contents
     *
     * @param string $directory
     * @param string $archiveName
     * @param int $compressionLevel Level of compression
     * @return bool
     * @throws FileAlreadyExistsException
     * @throws UnsupportedOperationException
     */
    public static function archiveDirectory($directory, $archiveName, $compressionLevel = BasicDriver::COMPRESSION_AVERAGE)
    {
        if (!is_dir($directory) || !is_readable($directory))
            throw new InvalidArgumentException($directory.' is not a valid directory to archive');

        return static::archiveFiles($directory, $archiveName) > 0;
    }

    /**
     * Expands files list
     * @param $archiveFiles
     * @param $files
     * @return array
     */
    protected static function expandFileList($archiveFiles, $files)
    {
        $newFiles = [];
        foreach ($files as $file) {
            foreach ($archiveFiles as $archiveFile) {
                if (fnmatch($file.'*', $archiveFile)) {
                    $newFiles[] = $archiveFile;
                }
            }
        }
        return $newFiles;
    }

    /**
     * @param string|array $nodes
     * @return array|bool
     */
    protected static function createFilesList($nodes)
    {
        $files = [];

        // passed an extended list
        if (is_array($nodes)) {
            foreach ($nodes as $source => $destination) {
                if (is_numeric($source))
                    $source = $destination;

                $destination = rtrim($destination, '/\\*');

                // if is directory
                if (is_dir($source))
                    static::importFilesFromDir(rtrim($source, '/\\*').'/*',
                        !empty($destination) ? $destination.'/' : null, true, $files);
                else if (is_file($source))
                    $files[$destination] = $source;
            }

        } else if (is_string($nodes)) { // passed one file or directory
            // if is directory
            if (is_dir($nodes))
                static::importFilesFromDir(rtrim($nodes, '/\\*').'/*', null, true,
                    $files);
            else if (is_file($nodes))
                $files[basename($nodes)] = $nodes;
        }

        return $files;
    }

    /**
     * @param string $source
     * @param string|null $destination
     * @param bool $recursive
     * @param array $map
     */
    protected static function importFilesFromDir($source, $destination, $recursive, &$map)
    {
        // $map[$destination] = rtrim($source, '/*');
        // do not map root archive folder

        if ($destination !== null)
            $map[$destination] = null;

        foreach (glob($source, GLOB_MARK) as $node) {
            if (in_array(substr($node, -1), ['/', '\\'], true) && $recursive) {
                static::importFilesFromDir(str_replace('\\', '/', $node).'*',
                    $destination.basename($node).'/', $recursive, $map);
            } elseif (is_file($node) && is_readable($node)) {
                $map[$destination.basename($node)] = $node;
            }
        }
    }

    /**
     * Checks whether archive can be opened with current system configuration
     *
     * @param string $fileName Archive filename
     * @deprecated See {UnifiedArchive::canOpen()}
     * @return bool
     */
    public static function canOpenArchive($fileName)
    {
        return static::canOpen($fileName);
    }

    /**
     * Checks whether specific archive type can be opened with current system configuration
     *
     * @deprecated See {{Formats::canOpen()}}
     * @param string $type One of predefined archive types (class constants)
     * @return bool
     */
    public static function canOpenType($type)
    {
        return Formats::canOpen($type);
    }

    /**
     * Checks whether specified archive can be created
     *
     * @deprecated See {{Formats::canCreate()}}
     * @param string $type One of predefined archive types (class constants)
     * @return bool
     */
    public static function canCreateType($type)
    {
        return Formats::canCreate($type);
    }

    /**
     * Returns type of archive
     *
     * @deprecated See {{UnifiedArchive::getArchiveFormat()}}
     * @return string One of class constants
     */
    public function getArchiveType()
    {
        return $this->getArchiveFormat();
    }

    /**
     * Detect archive type by its filename or content
     *
     * @deprecated See {{Formats::detectArchiveFormat()}}
     * @param string $fileName Archive filename
     * @param bool $contentCheck Whether archive type can be detected by content
     * @return string|bool One of UnifiedArchive type constants OR false if type is not detected
     */
    public static function detectArchiveType($fileName, $contentCheck = true)
    {
        return Formats::detectArchiveFormat($fileName, $contentCheck);
    }
}
