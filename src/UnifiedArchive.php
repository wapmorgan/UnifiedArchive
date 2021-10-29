<?php
namespace wapmorgan\UnifiedArchive;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use Iterator;
use wapmorgan\UnifiedArchive\Drivers\BasicDriver;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveModificationException;
use wapmorgan\UnifiedArchive\Exceptions\EmptyFileListException;
use wapmorgan\UnifiedArchive\Exceptions\FileAlreadyExistsException;
use wapmorgan\UnifiedArchive\Exceptions\NonExistentArchiveFileException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedArchiveException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;

/**
 * Class which represents archive in one of supported formats.
 */
class UnifiedArchive implements ArrayAccess, Iterator, Countable
{
    const VERSION = '1.1.3';

    /** @var string Type of current archive */
    protected $format;

    /** @var BasicDriver Adapter for current archive */
    protected $archive;

    /** @var array List of files in current archive */
    protected $files;

    /**
     * @var int
     */
    protected $filesIterator = 0;

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
     */
    public static function open($fileName, $password = null)
    {
        if (!file_exists($fileName) || !is_readable($fileName)) {
            throw new InvalidArgumentException('Could not open file: ' . $fileName.' is not readable');
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
     */
    public function __construct($fileName, $format, $password = null)
    {
        $driver = Formats::getFormatDriver($format);
        if ($driver === false) {
            throw new \RuntimeException('Driver for '.$format.' ('.$fileName.') is not found');
        }

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
     */
    public function getPclZipInterface()
    {
        return new PclzipZipInterface($this);
    }

    /**
     * @return string
     */
    public function getDriverType()
    {
        return get_class($this->archive);
    }

    /**
     * @return BasicDriver
     */
    public function getDriver()
    {
        return $this->archive;
    }

    /**
     * Returns size of archive file in bytes
     *
     * @return int
     */
    public function getSize()
    {
        return $this->archiveSize;
    }

    /**
     * Returns type of archive
     *
     * @return string One of Format class constants
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * Returns mime type of archive
     *
     * @return string|false Mime Type
     */
    public function getMimeType()
    {
        return Formats::getFormatMimeType($this->format);
    }

    /**
     * @return string|null
     */
    public function getComment()
    {
        return $this->archive->getComment();
    }

    /**
     * @param string|null $comment
     * @return string|null
     */
    public function setComment($comment)
    {
        return $this->archive->setComment($comment);
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
     * * Counts cumulative size of all uncompressed data (bytes)
     * @return int
     */
    public function getOriginalSize()
    {
        return $this->uncompressedFilesSize;
    }

    /**
     * Counts cumulative size of all compressed data (in bytes)
     * @return int
     */
    public function getCompressedSize()
    {
        return $this->compressedFilesSize;
    }

    /**
     * Checks that file exists in archive
     *
     * @param string $fileName File name in archive
     * @return bool
     */
    public function hasFile($fileName)
    {
        return in_array($fileName, $this->files, true);
    }

    /**
     * Returns list of files, excluding folders.
     *
     * Paths is present in unix-style (with forward slash - /).
     *
     * @param string|null $filter
     * @return array List of files
     */
    public function getFileNames($filter = null)
    {
        if ($filter === null)
            return $this->files;

        $result = [];
        foreach ($this->files as $file) {
            if (fnmatch($filter, $file))
                $result[] = $file;
        }
        return $result;
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
    public function getFileStream($fileName)
    {
        if (!in_array($fileName, $this->files, true)) {
            throw new NonExistentArchiveFileException('File ' . $fileName . ' does not exist in archive');
        }

        return $this->archive->getFileStream($fileName);
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
                ? $this->addFiles([$inArchiveName => $file])
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
                ? $this->addFiles([$inArchivePath => $directory])
                : $this->addFiles([$inArchivePath])) > 0;
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
     * @param null $password
     * @return int Count of stored files is returned.
     * @throws FileAlreadyExistsException
     * @throws UnsupportedOperationException
     */
    public static function archiveFiles($fileOrFiles, $archiveName, $compressionLevel = BasicDriver::COMPRESSION_AVERAGE, $password = null)
    {
        if (file_exists($archiveName))
            throw new FileAlreadyExistsException('Archive '.$archiveName.' already exists!');

        $info = static::prepareForArchiving($fileOrFiles, $archiveName);

        if (!Formats::canCreate($info['type']))
            throw new UnsupportedArchiveException('Unsupported archive type: '.$info['type'].' of archive '.$archiveName);

        if ($password !== null && !Formats::canEncrypt($info['type']))
            throw new UnsupportedOperationException('Archive type '.$info['type'].' can not be encrypted');

        /** @var BasicDriver $driver */
        $driver = Formats::getFormatDriver($info['type'], true);

        return $driver::createArchive($info['files'], $archiveName, $compressionLevel, $compressionLevel, $password);
    }

    /**
     * Creates an archive with one file
     *
     * @param string $file
     * @param string $archiveName
     * @param int $compressionLevel Level of compression
     * @param null $password
     * @return bool
     * @throws FileAlreadyExistsException
     * @throws UnsupportedOperationException
     */
    public static function archiveFile($file, $archiveName, $compressionLevel = BasicDriver::COMPRESSION_AVERAGE, $password = null)
    {
        if (!is_file($file)) {
            throw new InvalidArgumentException($file . ' is not a valid file to archive');
        }

        return static::archiveFiles($file, $archiveName, $compressionLevel, $password) === 1;
    }

    /**
     * Creates an archive with full directory contents
     *
     * @param string $directory
     * @param string $archiveName
     * @param int $compressionLevel Level of compression
     * @param null $password
     * @return bool
     * @throws FileAlreadyExistsException
     * @throws UnsupportedOperationException
     */
    public static function archiveDirectory($directory, $archiveName, $compressionLevel = BasicDriver::COMPRESSION_AVERAGE, $password = null)
    {
        if (!is_dir($directory) || !is_readable($directory))
            throw new InvalidArgumentException($directory.' is not a valid directory to archive');

        return static::archiveFiles($directory, $archiveName, $compressionLevel, $password) > 0;
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
            foreach ($nodes as $destination => $source) {
                // new format
                if (is_numeric($destination))
                    $destination = $source;
                else {
                    // old format
                    if (!file_exists($source)) {
                        list($destination, $source) = [$source, $destination];
                    }
                }

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
        return $this->getFormat();
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

    /**
     * Returns a resource for reading file from archive
     *
     * @deprecated See {{UnifiedArchive::getFileStream}}
     * @param string $fileName File name in archive
     * @return resource
     * @throws NonExistentArchiveFileException
     */
    public function getFileResource($fileName)
    {
        return $this->getFileStream($fileName);
    }

    /**
     * Returns type of archive
     *
     * @deprecated See {{UnifiedArchive::getFormat}}
     * @return string One of class constants
     */
    public function getArchiveFormat()
    {
        return $this->getFormat();
    }

    /**
     * Checks that file exists in archive
     *
     * @deprecated See {{UnifiedArchive::hasFile}}
     * @param string $fileName File name in archive
     * @return bool
     */
    public function isFileExists($fileName)
    {
        return $this->hasFile($fileName);
    }

    /**
     * Returns size of archive file in bytes
     *
     * @deprecated See {{UnifiedArchive::getSize}}
     * @return int
     */
    public function getArchiveSize()
    {
        return $this->getSize();
    }

    /**
     * Counts cumulative size of all compressed data (in bytes)
     *
     * @deprecated See {{UnifiedArchive::getCompressedSize}}
     * @return int
     */
    public function countCompressedFilesSize()
    {
        return $this->getCompressedSize();
    }

    /**
     * Counts cumulative size of all uncompressed data (bytes)
     *
     * @deprecated See {{UnifiedArchive::getOriginalSize}}
     * @return int
     */
    public function countUncompressedFilesSize()
    {
        return $this->getOriginalSize();
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return $this->hasFile($offset);
    }

    /**
     * @param mixed $offset
     * @return mixed|string
     * @throws NonExistentArchiveFileException
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->getFileData($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @return bool|void
     * @throws ArchiveModificationException
     * @throws UnsupportedOperationException
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        return $this->addFileFromString($offset, $value);
    }

    /**
     * @param mixed $offset
     * @return bool|int|void
     * @throws ArchiveModificationException
     * @throws UnsupportedOperationException
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        return $this->deleteFiles($offset);
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->files[$this->filesIterator];
    }

    /**
     * @throws NonExistentArchiveFileException
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->getFileData($this->files[$this->filesIterator]);
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        $this->filesIterator++;
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        return $this->filesIterator < $this->filesQuantity;
    }

    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->filesIterator = 0;
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        return $this->filesQuantity;
    }
}
