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
use wapmorgan\UnifiedArchive\Formats\BasicFormat;
use wapmorgan\UnifiedArchive\Formats\Bzip;
use wapmorgan\UnifiedArchive\Formats\Cab;
use wapmorgan\UnifiedArchive\Formats\Gzip;
use wapmorgan\UnifiedArchive\Formats\Iso;
use wapmorgan\UnifiedArchive\Formats\Lzma;
use wapmorgan\UnifiedArchive\Formats\Rar;
use wapmorgan\UnifiedArchive\Formats\SevenZip;
use wapmorgan\UnifiedArchive\Formats\Tar;
use wapmorgan\UnifiedArchive\Formats\Zip;

/**
 * Class which represents archive in one of supported formats.
 */
class UnifiedArchive
{
    const VERSION = '1.0.1';

    const ZIP = 'zip';
    const SEVEN_ZIP = '7zip';
    const RAR = 'rar';
    const GZIP = 'gzip';
    const BZIP = 'bzip2';
    const LZMA = 'lzma2';
    const ISO = 'iso';
    const CAB = 'cab';
    const TAR = 'tar';
    const TAR_GZIP = 'tgz';
    const TAR_BZIP = 'tbz2';
    const TAR_LZMA = 'txz';
    const TAR_LZW = 'tar.z';

    /** @var array<string, string> List of archive format handlers */
    protected static $formatHandlers = [
        self::ZIP => Zip::class,
        self::SEVEN_ZIP => SevenZip::class,
        self::RAR => Rar::class,
        self::GZIP => Gzip::class,
        self::BZIP => Bzip::class,
        self::LZMA => Lzma::class,
        self::ISO => Iso::class,
        self::CAB => Cab::class,
        self::TAR => Tar::class,
        self::TAR_GZIP => Tar::class,
        self::TAR_BZIP => Tar::class,
        self::TAR_LZMA => Tar::class,
        self::TAR_LZW => Tar::class,
    ];

    public static $formatOptions = [

    ];

    /** @var array List of archive types with support-state */
    static protected $enabledTypes = [];

    /** @var string Type of current archive */
    protected $type;

    /** @var BasicFormat Adapter for current archive */
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
     */
    public static function open($fileName, $password = null)
    {
        static::checkRequirements();

        if (!file_exists($fileName) || !is_readable($fileName)) {
            throw new InvalidArgumentException('Could not open file: ' . $fileName);
        }

        $type = static::detectArchiveType($fileName);
        if (!static::canOpenType($type)) {
            return null;
        }

        return new static($fileName, $type, $password);
    }

    /**
     * Checks whether archive can be opened with current system configuration
     *
     * @param string $fileName Archive filename
     * @return bool
     */
    public static function canOpenArchive($fileName)
    {
        static::checkRequirements();

        $type = static::detectArchiveType($fileName);

        return $type !== false && static::canOpenType($type);
    }

    /**
     * Checks whether specific archive type can be opened with current system configuration
     *
     * @param string $type One of predefined archive types (class constants)
     * @return bool
     */
    public static function canOpenType($type)
    {
        static::checkRequirements();

        return isset(static::$enabledTypes[$type])
            ? static::$enabledTypes[$type]
            : false;
    }

    /**
     * Checks whether specified archive can be created
     *
     * @param string $type One of predefined archive types (class constants)
     * @return bool
     */
    public static function canCreateType($type)
    {
        static::checkRequirements();

        return isset(static::$enabledTypes[$type])
            ? call_user_func([static::$formatHandlers[$type], 'canCreateArchive'])
            : false;
    }

    /**
     * Detect archive type by its filename or content
     *
     * @param string $fileName Archive filename
     * @param bool $contentCheck Whether archive type can be detected by content
     * @return string|bool One of UnifiedArchive type constants OR false if type is not detected
     */
    public static function detectArchiveType($fileName, $contentCheck = true)
    {
        // by file name
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (stripos($fileName, '.tar.') !== false && preg_match('~\.(?<ext>tar\.(gz|bz2|xz|z))$~', strtolower($fileName), $match)) {
            switch ($match['ext']) {
                case 'tar.gz':
                    return self::TAR_GZIP;
                case 'tar.bz2':
                    return self::TAR_BZIP;
                case 'tar.xz':
                    return self::TAR_LZMA;
                case 'tar.z':
                    return self::TAR_LZW;
            }
        }

        switch ($ext) {
            case 'zip':
                return self::ZIP;
            case '7z':
                return self::SEVEN_ZIP;
            case 'rar':
                return self::RAR;
            case 'gz':
                return self::GZIP;
            case 'bz2':
                return self::BZIP;
            case 'xz':
                return self::LZMA;
            case 'iso':
                return self::ISO;
            case 'cab':
                return self::CAB;
            case 'tar':
                return self::TAR;
            case 'tgz':
                return self::TAR_GZIP;
            case 'tbz2':
                return self::TAR_BZIP;
            case 'txz':
                return self::TAR_LZMA;
        }

        // by file content
        if ($contentCheck) {
            $mime_type = mime_content_type($fileName);
            switch ($mime_type) {
                case 'application/zip':
                    return self::ZIP;
                case 'application/x-7z-compressed':
                    return self::SEVEN_ZIP;
                case 'application/x-rar':
                    return self::RAR;
                case 'application/zlib':
                case 'application/gzip':
                case 'application/x-gzip':
                    return self::GZIP;
                case 'application/x-bzip2':
                    return self::BZIP;
                case 'application/x-lzma':
                    return self::LZMA;
                case 'application/x-iso9660-image':
                    return self::ISO;
                case 'application/vnd.ms-cab-compressed':
                    return self::CAB;
                case 'application/x-tar':
                    return self::TAR;
                case 'application/x-gtar':
                    return self::TAR_GZIP;

            }
        }

        return false;
    }

    /**
     * Opens the file as one of supported formats
     *
     * @param string $fileName Archive filename
     * @param string $type Archive type
     * @param string|null $password
     */
    public function __construct($fileName, $type, $password = null)
    {
        static::checkRequirements();

        if (!isset(static::$formatHandlers[$type]))
            throw new UnsupportedArchiveException('Unsupported archive type: '.$type.' of archive '.$fileName);

        $this->type = $type;
        $this->archiveSize = filesize($fileName);
        $this->password = $password;

        $handler_class = static::$formatHandlers[$type];

        /** @var BasicFormat archive */
        $this->archive = new $handler_class($fileName);
        if ($password !== null)
            $this->archive->setPasssword($password);
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
    public function getArchiveType()
    {
        return $this->type;
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
        $archiveType = static::detectArchiveType($archiveName, false);

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
    public static function archiveFiles($fileOrFiles, $archiveName, $compressionLevel = BasicFormat::COMPRESSION_AVERAGE)
    {
        if (file_exists($archiveName))
            throw new FileAlreadyExistsException('Archive '.$archiveName.' already exists!');

        static::checkRequirements();

        $info = static::prepareForArchiving($fileOrFiles, $archiveName);

        if (!isset(static::$formatHandlers[$info['type']]))
            throw new UnsupportedArchiveException('Unsupported archive type: '.$info['type'].' of archive '.$archiveName);

        /** @var BasicFormat $handler_class */
        $handler_class = static::$formatHandlers[$info['type']];

        return $handler_class::createArchive($info['files'], $archiveName, $compressionLevel);
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
    public static function archiveFile($file, $archiveName, $compressionLevel = BasicFormat::COMPRESSION_AVERAGE)
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
    public static function archiveDirectory($directory, $archiveName, $compressionLevel = BasicFormat::COMPRESSION_AVERAGE)
    {
        if (!is_dir($directory) || !is_readable($directory))
            throw new InvalidArgumentException($directory.' is not a valid directory to archive');

        return static::archiveFiles($directory, $archiveName) > 0;
    }

    /**
     * @return bool
     */
    public function canAddFiles()
    {
        return call_user_func([static::$formatHandlers[$this->type], 'canAddFiles']);
    }

    /**
     * @return bool
     */
    public function canDeleteFiles()
    {
        return call_user_func([static::$formatHandlers[$this->type], 'canDeleteFiles']);
    }

    /**
     * @return bool
     */
    public function canUsePassword()
    {
        return call_user_func([static::$formatHandlers[$this->type], 'canUsePassword']);
    }

    /**
     * Tests system configuration
     */
    protected static function checkRequirements()
    {
        if (empty(static::$enabledTypes)) {
            static::$enabledTypes[self::ZIP] = extension_loaded('zip');
            static::$enabledTypes[self::SEVEN_ZIP] = class_exists('\Archive7z\Archive7z');
            static::$enabledTypes[self::RAR] = extension_loaded('rar');
            static::$enabledTypes[self::GZIP] = extension_loaded('zlib');
            static::$enabledTypes[self::BZIP] = extension_loaded('bz2');
            static::$enabledTypes[self::LZMA] = extension_loaded('xz');
            static::$enabledTypes[self::ISO] = class_exists('\CISOFile');
            static::$enabledTypes[self::CAB] = class_exists('\CabArchive');
            static::$enabledTypes[self::TAR] = class_exists('\Archive_Tar') || class_exists('\PharData');
            static::$enabledTypes[self::TAR_GZIP] = (class_exists('\Archive_Tar') || class_exists('\PharData')) && extension_loaded('zlib');
            static::$enabledTypes[self::TAR_BZIP] = (class_exists('\Archive_Tar') || class_exists('\PharData')) && extension_loaded('bz2');
            static::$enabledTypes[self::TAR_LZMA] = class_exists('\Archive_Tar') && extension_loaded('lzma2');
            static::$enabledTypes[self::TAR_LZW] = class_exists('\Archive_Tar') && LzwStreamWrapper::isBinaryAvailable();
        }
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
}
