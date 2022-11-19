<?php
namespace wapmorgan\UnifiedArchive;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use Iterator;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver;
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
    const VERSION = '1.1.8';

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

    /** @var BasicDriver */
    protected $driver;

    /** @var string|null */
    private $password;

    /**
     * Creates a UnifiedArchive instance for passed archive
     *
     * @param string $fileName Archive filename
     * @param array|null|string $abilities List of supported abilities by driver. If passed string, used as password.
     * @param string|null $password Password to open archive
     * @return UnifiedArchive|null Returns UnifiedArchive in case of successful reading of the file
     */
    public static function open($fileName, $abilities = [], $password = null)
    {
        if (!file_exists($fileName) || !is_readable($fileName)) {
            throw new InvalidArgumentException('Could not open file: ' . $fileName . ' is not readable');
        }

        $format = Formats::detectArchiveFormat($fileName);
        if ($format === false) {
            return null;
        }

        if (!empty($abilities) && is_string($abilities)) {
            $password = $abilities;
            $abilities = [];
        }

        if (empty($abilities)) {
            $abilities = [BasicDriver::OPEN];
            if (!empty($password)) {
                $abilities[] = BasicDriver::OPEN_ENCRYPTED;
            }
        }
        $driver = Formats::getFormatDriver($format, $abilities);
        if ($driver === null) {
            return null;
        }

        return new static($fileName, $format, $driver, $password);
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
     * Prepare files list for archiving
     *
     * @param string|array $fileOrFiles File of list of files. See [[archiveFiles]] for details.
     * @param string|null $archiveName File name of archive. See [[archiveFiles]] for details.
     * @return array An array containing entries:
     * - totalSize (int) - size in bytes for all files
     * - numberOfFiles (int) - quantity of files
     * - files (array) - list of files prepared for archiving
     * - type (string|null) - prepared format for archive. One of class constants
     * @throws EmptyFileListException
     * @throws UnsupportedArchiveException
     */
    public static function prepareForArchiving($fileOrFiles, $archiveName = null)
    {
        if ($archiveName !== null) {
            $archiveType = Formats::detectArchiveFormat($archiveName, false);
            if ($archiveType === false) {
                throw new UnsupportedArchiveException('Could not detect archive type for name "' . $archiveName . '"');
            }
        }

        $files_list = static::createFilesList($fileOrFiles);

        if (empty($files_list)) {
            throw new EmptyFileListException('Files list is empty!');
        }

        $totalSize = 0;
        foreach ($files_list as $fn) {
            if ($fn !== null) {
                $totalSize += filesize($fn);
            }
        }

        return [
            'totalSize' => $totalSize,
            'numberOfFiles' => count($files_list),
            'files' => $files_list,
            'type' => $archiveName !== null ? $archiveType : null,
        ];
    }

    /**
     * Creates an archive with passed files list
     *
     * @param string|string[]|array<string,string>||array<string,string[]> $fileOrFiles List of files.
     *  Can be one of three formats:
     *  1. A string containing path to file or directory.
     *     File will have it's basename.
     *      `UnifiedArchive::create('/etc/php.ini', 'archive.zip)` will store file with 'php.ini' name.
     *     Directory contents will be populated in archive root.
     *      `UnifiedArchive::create('/var/log/', 'archive.zip')` will store all directory contents in archive root.
     *  2. An array with strings containing paths to files or directories.
     *     Files and directories will be stored with full paths (expect leading slash).
     *      `UnifiedArchive::create(['/etc/php.ini', '/var/log/'], 'archive.zip)` will preserve full paths.
     *  3. An array with strings where keys are strings.
     *     Files will be named from key.
     *     Directory contents will be prefixed from key. If prefix is empty string, contents will be populated into
     *      archive root. If value is an array, all folder contents will have the same prefix.
     *      `UnifiedArchive::create([
     *          'doc.txt' => '/home/user/very_long_name_of_document.txt',
     *          'static' => '/var/www/html/static/',
     *          'collection' => ['/var/www/html/collection1/', '/var/www/html/collection2/'],
     *          '' => ['/var/www/html/readme/', '/var/www/html/docs/'], // root contents
     *      ], 'archive.zip')`
     *
     * @param string $archiveName File name of archive. Type of archive will be determined by its name.
     * @param int $compressionLevel Level of compression
     * @param string|null $password
     * @param callable|null $fileProgressCallable
     * @return int Count of stored files is returned.
     * @throws FileAlreadyExistsException
     * @throws UnsupportedOperationException
     */
    public static function create(
        $fileOrFiles,
        $archiveName,
        $compressionLevel = BasicDriver::COMPRESSION_AVERAGE,
        $password = null,
        $fileProgressCallable = null
    )
    {
        if (file_exists($archiveName)) {
            throw new FileAlreadyExistsException('Archive ' . $archiveName . ' already exists!');
        }

        $info = static::prepareForArchiving($fileOrFiles, $archiveName);
        $driver = static::getCreationDriver($info['type'], false, $password !== null);

        return $driver::createArchive(
            $info['files'],
            $archiveName,
            $info['type'],
            $compressionLevel,
            $password,
            $fileProgressCallable
        );
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
     * @param string $archiveFormat
     * @param int $compressionLevel Level of compression
     * @param string|null $password
     * @param callable|null $fileProgressCallable
     * @return int Count of stored files is returned.
     * @throws UnsupportedOperationException
     */
    public static function createInString(
        $fileOrFiles,
        $archiveFormat,
        $compressionLevel = BasicDriver::COMPRESSION_AVERAGE,
        $password = null,
        $fileProgressCallable = null
    )
    {
        $info = static::prepareForArchiving($fileOrFiles, '.' . Formats::getFormatExtension($archiveFormat));
        try {
            $driver = static::getCreationDriver($archiveFormat, true, $password !== null);
        } catch (UnsupportedArchiveException $e) {
            // if there is no driver with ability to create archive in string (in memory), use first driver for format and create it in temp folder
            $driver = static::getCreationDriver($archiveFormat, false, $password !== null);
        }

        return $driver::createArchiveInString(
            $info['files'],
            $info['type'],
            $compressionLevel,
            $password,
            $fileProgressCallable
        );
    }

    /**
     * @throws UnsupportedOperationException
     * @return BasicDriver
     */
    protected static function getCreationDriver($archiveFormat, $inString, $encrypted)
    {
        if (!Formats::canCreate($archiveFormat)) {
            throw new UnsupportedArchiveException('Unsupported archive type: ' . $archiveFormat);
        }

        $abilities = [BasicDriver::CREATE];
        if ($inString) {
            $abilities[] = BasicDriver::CREATE_IN_STRING;
        }

        if ($encrypted) {
            if (!Formats::canEncrypt($archiveFormat)) {
                throw new UnsupportedOperationException('Archive type ' . $archiveFormat . ' can not be encrypted');
            }
            $abilities[] = BasicDriver::CREATE_ENCRYPTED;
        }

        /** @var BasicDriver $driver */
        $driver = Formats::getFormatDriver($archiveFormat, $abilities);
        if ($driver === null) {
            throw new UnsupportedArchiveException('Unsupported archive type: ' . $archiveFormat . ' of archive ');
        }
        return $driver;
    }

    /**
     * Opens the file as one of supported formats
     *
     * @param string $fileName Archive filename
     * @param string $format Archive type
     * @param string|BasicDriver $driver
     * @param string|null $password
     */
    public function __construct($fileName, $format, $driver, $password = null)
    {
        $this->format = $format;
        $this->driver = $driver;
        $this->password = $password;
        $this->archiveSize = filesize($fileName);

        /** @var BasicDriver */
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
     * @return PclZipInterface Returns an instance of a class implementing PclZip-like interface
     */
    public function getPclZipInterface()
    {
        return new PclZipInterface($this);
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
     * @throws UnsupportedOperationException
     */
    public function getComment()
    {
        return $this->archive->getComment();
    }

    /**
     * @param string|null $comment
     * @return string|null
     * @throws UnsupportedOperationException
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
    public function getFiles($filter = null)
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
    public function extract($outputFolder, &$files = null, $expandFilesList = false)
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
    public function delete($fileOrFiles, $expandFilesList = false)
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
     * @return int Number of added files
     * @throws ArchiveModificationException
     * @throws EmptyFileListException
     * @throws UnsupportedOperationException
     */
    public function add($fileOrFiles)
    {
        $files_list = static::createFilesList($fileOrFiles);

        if (empty($files_list)) {
            throw new EmptyFileListException('Files list is empty!');
        }

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
            throw new InvalidArgumentException($file . ' is not a valid file to add in archive');

        return ($inArchiveName !== null
                ? $this->add([$inArchiveName => $file])
                : $this->add([$file])) === 1;
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
            throw new InvalidArgumentException($directory . ' is not a valid directory to add in archive');

        return ($inArchivePath !== null
                ? $this->add([$inArchivePath => $directory])
                : $this->add([$inArchivePath])) > 0;
    }

    /**
     * @param string|null $filter
     * @return true|string[]
     * @throws NonExistentArchiveFileException
     */
    public function test($filter = null)
    {
        $hash_exists = function_exists('hash_update_stream') && in_array('crc32b', hash_algos(), true);
        $failed = [];
        foreach ($this->getFiles($filter) as $fileName) {
            if ($hash_exists) {
                $ctx = hash_init('crc32b');
                hash_update_stream($ctx, $this->getFileStream($fileName));
                $actual_hash = hash_final($ctx);
            } else {
                $actual_hash = dechex(crc32($this->getFileContent($fileName)));
            }
            $expected_hash = strtolower($this->getFileData($fileName)->crc32);
            if ($expected_hash !== $actual_hash) {
                $failed[] = $fileName;
            }
        }
        return !empty($failed) ? $failed : true;
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
                if (fnmatch($file . '*', $archiveFile)) {
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
                    if (is_string($source) && !file_exists($source)) {
                        list($destination, $source) = [$source, $destination];
                    }
                }

                $destination = rtrim($destination, '/\\*');

                // few sources for directories
                if (is_array($source)) {
                    foreach ($source as $sourceItem) {
                        static::importFilesFromDir(
                            rtrim($sourceItem, '/\\*') . '/*',
                            !empty($destination) ? $destination . '/' : null,
                            true,
                            $files
                        );
                    }
                } else if (is_dir($source)) {
                    // one source for directories
                    static::importFilesFromDir(
                        rtrim($source, '/\\*') . '/*',
                        !empty($destination) ? $destination . '/' : null,
                        true,
                        $files
                    );
                } else if (is_file($source)) {
                    $files[$destination] = $source;
                }
            }

        } else if (is_string($nodes)) { // passed one file or directory
            // if is directory
            if (is_dir($nodes))
                static::importFilesFromDir(rtrim($nodes, '/\\*') . '/*', null, true,
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
                static::importFilesFromDir(str_replace('\\', '/', $node) . '*',
                    $destination . basename($node) . '/', $recursive, $map);
            } elseif (is_file($node) && is_readable($node)) {
                $map[$destination . basename($node)] = $node;
            }
        }
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
        return $this->delete($offset);
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

    // deprecated methods

    /**
     * Checks whether archive can be opened with current system configuration
     *
     * @param string $fileName Archive filename
     * @return bool
     * @deprecated See {UnifiedArchive::canOpen()}
     */
    public static function canOpenArchive($fileName)
    {
        return static::canOpen($fileName);
    }

    /**
     * Checks whether specific archive type can be opened with current system configuration
     *
     * @param string $type One of predefined archive types (class constants)
     * @return bool
     * @deprecated Use {{Formats::canOpen()}}
     */
    public static function canOpenType($type)
    {
        return Formats::canOpen($type);
    }

    /**
     * Checks whether specified archive can be created
     *
     * @param string $type One of predefined archive types (class constants)
     * @return bool
     * @deprecated Use {{Formats::canCreate()}}
     */
    public static function canCreateType($type)
    {
        return Formats::canCreate($type);
    }

    /**
     * Detect archive type by its filename or content
     *
     * @param string $fileName Archive filename
     * @param bool $contentCheck Whether archive type can be detected by content
     * @return string|bool One of UnifiedArchive type constants OR false if type is not detected
     * @deprecated Use {{Formats::detectArchiveFormat()}}
     */
    public static function detectArchiveType($fileName, $contentCheck = true)
    {
        return Formats::detectArchiveFormat($fileName, $contentCheck);
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
     * @param string|null $password
     * @param callable|null $fileProgressCallable
     * @return int Count of stored files is returned.
     * @throws FileAlreadyExistsException
     * @throws UnsupportedOperationException
     * @deprecated Use {{UnifiedArchive::create}}
     */
    public static function archiveFiles(
        $fileOrFiles,
        $archiveName,
        $compressionLevel = BasicDriver::COMPRESSION_AVERAGE,
        $password = null,
        $fileProgressCallable = null
    )
    {
        return static::create($fileOrFiles, $archiveName, $compressionLevel, $password, $fileProgressCallable);
    }

    /**
     * Creates an archive with one file
     *
     * @param string $file
     * @param string $archiveName
     * @param int $compressionLevel Level of compression
     * @param string|null $password
     * @return bool
     * @throws FileAlreadyExistsException
     * @throws UnsupportedOperationException
     * @deprecated Use {{UnifiedArchive::create}}
     */
    public static function archiveFile($file, $archiveName, $compressionLevel = BasicDriver::COMPRESSION_AVERAGE, $password = null)
    {
        if (!is_file($file)) {
            throw new InvalidArgumentException($file . ' is not a valid file to archive');
        }

        return static::create($file, $archiveName, $compressionLevel, $password) === 1;
    }

    /**
     * Creates an archive with full directory contents
     *
     * @param string $directory
     * @param string $archiveName
     * @param int $compressionLevel Level of compression
     * @param string|null $password
     * @return bool
     * @throws FileAlreadyExistsException
     * @throws UnsupportedOperationException
     * @deprecated Use {{UnifiedArchive::create}}
     */
    public static function archiveDirectory($directory, $archiveName, $compressionLevel = BasicDriver::COMPRESSION_AVERAGE, $password = null)
    {
        if (!is_dir($directory) || !is_readable($directory))
            throw new InvalidArgumentException($directory . ' is not a valid directory to archive');

        return static::create($directory, $archiveName, $compressionLevel, $password) > 0;
    }

    /**
     * Returns type of archive
     *
     * @return string One of class constants
     * @deprecated Use {{UnifiedArchive::getArchiveFormat()}}
     */
    public function getArchiveType()
    {
        return $this->getFormat();
    }

    /**
     * Returns a resource for reading file from archive
     *
     * @param string $fileName File name in archive
     * @return resource
     * @throws NonExistentArchiveFileException
     * @deprecated Use {{UnifiedArchive::getFileStream}}
     */
    public function getFileResource($fileName)
    {
        return $this->getFileStream($fileName);
    }

    /**
     * Returns type of archive
     *
     * @return string One of class constants
     * @deprecated Use {{UnifiedArchive::getFormat}}
     */
    public function getArchiveFormat()
    {
        return $this->getFormat();
    }

    /**
     * Checks that file exists in archive
     *
     * @param string $fileName File name in archive
     * @return bool
     * @deprecated Use {{UnifiedArchive::hasFile}}
     */
    public function isFileExists($fileName)
    {
        return $this->hasFile($fileName);
    }

    /**
     * Returns size of archive file in bytes
     *
     * @return int
     * @deprecated Use {{UnifiedArchive::getSize}}
     */
    public function getArchiveSize()
    {
        return $this->getSize();
    }

    /**
     * Counts cumulative size of all compressed data (in bytes)
     *
     * @return int
     * @deprecated Use {{UnifiedArchive::getCompressedSize}}
     */
    public function countCompressedFilesSize()
    {
        return $this->getCompressedSize();
    }

    /**
     * Counts cumulative size of all uncompressed data (bytes)
     *
     * @return int
     * @deprecated Use {{UnifiedArchive::getOriginalSize}}
     */
    public function countUncompressedFilesSize()
    {
        return $this->getOriginalSize();
    }

    /**
     * Returns list of files, excluding folders.
     *
     * Paths is present in unix-style (with forward slash - /).
     *
     * @param string|null $filter
     * @return array List of files
     * @deprecated Use {{UnifiedArchive::getFiles}}
     */
    public function getFileNames($filter = null)
    {
        return $this->getFiles($filter);
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
     * @deprecated Use {{UnifiedArchive::extract}}
     */
    public function extractFiles($outputFolder, &$files = null, $expandFilesList = false)
    {
        return $this->extract($outputFolder, $files, $expandFilesList);
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
     * @deprecated Use {{UnifiedArchive::delete}}
     */
    public function deleteFiles($fileOrFiles, $expandFilesList = false)
    {
        return $this->delete($fileOrFiles, $expandFilesList);
    }

    /**
     * Updates existing archive by adding new files
     *
     * @param string[] $fileOrFiles See [[archiveFiles]] method for file list format.
     * @return int|bool Number of added files
     * @throws ArchiveModificationException
     * @throws EmptyFileListException
     * @throws UnsupportedOperationException
     * @deprecated Use {{UnifiedArchive::add}}
     */
    public function addFiles($fileOrFiles)
    {
        return $this->add($fileOrFiles);
    }
}
