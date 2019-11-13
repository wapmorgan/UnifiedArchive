<?php
namespace wapmorgan\UnifiedArchive;

use Exception;
use InvalidArgumentException;
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
    const VERSION = '0.1.2';

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

    /** @var array List of archive format handlers */
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

    /** @var array List of archive formats with support state */
    static protected $enabledTypes = [];

    /** @var string Type of current archive */
    protected $type;

    /** @var BasicFormat Adapter for current archive */
    protected $archive;

    /** @var array List of files in current archive */
    protected $files;

    /** @var int Number of files */
    protected $filesQuantity;

    /** @var int Size of uncompressed files */
    protected $uncompressedFilesSize;

    /** @var int Size of compressed files */
    protected $compressedFilesSize;

    /** @var int Size of archive */
    protected $archiveSize;

    /**
     * Creates instance with right type
     *
     * @param  string $fileName Filename
     * @return UnifiedArchive|null Returns UnifiedArchive in case of successful reading of the file
     * @throws \Exception
     */
    public static function open($fileName)
    {
        self::checkRequirements();

        if (!file_exists($fileName) || !is_readable($fileName))
            throw new Exception('Could not open file: '.$fileName);

        $type = self::detectArchiveType($fileName);
        if (!self::canOpenType($type)) {
            return null;
        }

        return new self($fileName, $type);
    }

    /**
     * Checks whether archive can be opened with current system configuration
     *
     * @param string $fileName
     * @return boolean
     */
    public static function canOpenArchive($fileName)
    {
        self::checkRequirements();

        $type = self::detectArchiveType($fileName);

        return $type !== false && self::canOpenType($type);
    }

    /**
     * Checks whether specific archive type can be opened with current system configuration
     *
     * @param string $type One of predefined archive types
     * @return boolean
     */
    public static function canOpenType($type)
    {
        self::checkRequirements();

        return isset(self::$enabledTypes[$type])
            ? self::$enabledTypes[$type]
            : false;
    }

    public static function canCreateType($type)
    {
        self::checkRequirements();
        return isset(self::$enabledTypes[$type])
            ? static::$formatHandlers[$type]::canCreateArchive()
            : false;
    }

    /**
     * Detect archive type by its filename or content
     *
     * @param string $fileName
     * @param bool $contentCheck
     * @return string|boolean One of UnifiedArchive type constants OR false if type is not detected
     */
    public static function detectArchiveType($fileName, $contentCheck = true)
    {
        // by file name
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // by file name
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

        // by content
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
     * @param string $fileName Filename
     * @param string $type Archive type.
     * @throws Exception If archive can not be opened
     */
    public function __construct($fileName, $type)
    {
        self::checkRequirements();

        $this->type = $type;
        $this->archiveSize = filesize($fileName);

        if (!isset(static::$formatHandlers[$type]))
            throw new Exception('Unsupported archive type: '.$type.' of archive '.$fileName);

        $handler_class = static::$formatHandlers[$type];

        $this->archive = new $handler_class($fileName);
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
     * @throws Exception
     */
    public function getPclZipInterface()
    {
        if ($this->type !== self::ZIP)
            throw new UnsupportedOperationException('Format '.$this->type.' does not support PclZip-interface');

        return new $this->archive->getPclZip();
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
     * Counts size of all uncompressed data (bytes)
     *
     * @return int
     */
    public function countUncompressedFilesSize()
    {
        return $this->uncompressedFilesSize;
    }

    /**
     * Returns size of archive
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
     * @return string
     */
    public function getArchiveType()
    {
        return $this->type;
    }

    /**
     * Counts size of all compressed data (in bytes)
     *
     * @return int
     */
    public function countCompressedFilesSize()
    {
        return $this->compressedFilesSize;
    }

    /**
     * Returns list of files
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
     * @param string $fileName
     * @return bool
     */
    public function isFileExists($fileName)
    {
        return in_array($fileName, $this->files, true);
    }

    /**
     * Returns file metadata
     *
     * @param string $fileName
     * @return ArchiveEntry|bool
     */
    public function getFileData($fileName)
    {
        if (!in_array($fileName, $this->files, true))
            return false;

        return $this->archive->getFileData($fileName);
    }

    /**
     * Returns file content
     *
     * @param string $fileName
     * @return bool|string
     * @throws \Exception
     */
    public function getFileContent($fileName)
    {
        if (!in_array($fileName, $this->files, true))
            return false;

        return $this->archive->getFileContent($fileName);
    }

    /**
     * Returns a resource for reading file from archive
     *
     * @param string $fileName
     * @return bool|resource
     */
    public function getFileResource($fileName)
    {
        if (!in_array($fileName, $this->files, true))
            return false;

        return $this->archive->getFileResource($fileName);
    }

    /**
     * Unpacks files to disk
     *
     * @param string $outputFolder Extraction output dir.
     * @param string|array|null $files One files or list of files or null to extract all content.
     * @param bool $expandFilesList Should be expanded paths like 'src/' to all files inside 'src/' dir or not.
     * @return false|int
     * @throws Exception If files can not be extracted
     */
    public function extractFiles($outputFolder, $files = null, $expandFilesList = false)
    {
        if ($files !== null) {
            if (is_string($files)) $files = [$files];

            if ($expandFilesList)
                $files = self::expandFileList($this->files, $files);

            return $this->archive->extractFiles($outputFolder, $files);
        } else {
            return $this->archive->extractArchive($outputFolder);
        }
    }

    /**
     * Updates existing archive by removing files from it
     *
     * Only 7zip and zip types support deletion.
     * @param string|string[] $fileOrFiles
     * @param bool $expandFilesList
     *
     * @return bool|int
     * @throws Exception
     */
    public function deleteFiles($fileOrFiles, $expandFilesList = false)
    {
        $fileOrFiles = is_string($fileOrFiles) ? [$fileOrFiles] : $fileOrFiles;

        if ($expandFilesList && $fileOrFiles !== null)
            $fileOrFiles = self::expandFileList($this->files, $fileOrFiles);

        $result = $this->archive->deleteFiles($fileOrFiles);
        $this->scanArchive();
        return $result;
    }

    /**
     * Updates existing archive by adding new files
     *
     * @param string[] $fileOrFiles See [[archiveFiles]] method for file list format.
     * @return int|bool False if failed, number of added files if success
     * @throws Exception
     */
    public function addFiles($fileOrFiles)
    {
        $files_list = self::createFilesList($fileOrFiles);
        if (empty($files_list))
            throw new InvalidArgumentException('Files list is empty!');
        $result = $this->archive->addFiles($files_list);
        $this->scanArchive();
        return $result;
    }

    /**
     * Adds file into archive
     *
     * @param string $file
     * @param string|null $inArchiveName If not passed, full path will be preserved.
     * @return bool
     * @throws Exception
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
     * @throws Exception
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
     * @param string $archiveName File name of archive. Type of archive will be determined via it's name.
     * @param bool $emulate If true, emulation mode is performed instead of real archiving.
     *
     * @return array|bool|int Count of stored files is returned.
     * @throws Exception
     */
    public static function archiveFiles($fileOrFiles, $archiveName, $emulate = false)
    {
        if (file_exists($archiveName))
            throw new Exception('Archive '.$archiveName.' already exists!');

        self::checkRequirements();

        $archiveType = self::detectArchiveType($archiveName, false);
        //        if (in_array($archiveType, [TarArchive::TAR, TarArchive::TAR_GZIP, TarArchive::TAR_BZIP, TarArchive::TAR_LZMA, TarArchive::TAR_LZW], true))
        //            return TarArchive::archiveFiles($fileOrFiles, $archiveName, $emulate);
        if ($archiveType === false)
            return false;

        $files_list = self::createFilesList($fileOrFiles);
        if (empty($files_list))
            throw new InvalidArgumentException('Files list is empty!');

        // fake creation: return archive data
        if ($emulate) {
            $totalSize = 0;
            foreach ($files_list as $fn) $totalSize += filesize($fn);

            return array(
                'totalSize' => $totalSize,
                'numberOfFiles' => count($files_list),
                'files' => $files_list,
                'type' => $archiveType,
            );
        }

        if (!isset(static::$formatHandlers[$archiveType]))
            throw new Exception('Unsupported archive type: '.$archiveType.' of archive '.$archiveName);

        $handler_class = static::$formatHandlers[$archiveType];

        return $handler_class::createArchive($files_list, $archiveName);
    }

    /**
     * Creates an archive with one file
     *
     * @param string $file
     * @param string $archiveName
     * @return bool
     * @throws \Exception
     */
    public static function archiveFile($file, $archiveName)
    {
        if (!is_file($file))
            throw new InvalidArgumentException($file.' is not a valid file to archive');

        return static::archiveFiles($file, $archiveName) === 1;
    }

    /**
     * Creates an archive with full directory contents
     *
     * @param string $directory
     * @param string $archiveName
     * @return bool
     * @throws Exception
     */
    public static function archiveDirectory($directory, $archiveName)
    {
        if (!is_dir($directory) || !is_readable($directory))
            throw new InvalidArgumentException($directory.' is not a valid directory to archive');

        return static::archiveFiles($directory, $archiveName) > 0;
    }

    /**
     * Tests system configuration
     */
    protected static function checkRequirements()
    {
        if (empty(self::$enabledTypes)) {
            self::$enabledTypes[self::ZIP] = extension_loaded('zip');
            self::$enabledTypes[self::SEVEN_ZIP] = class_exists('\Archive7z\Archive7z');
            self::$enabledTypes[self::RAR] = extension_loaded('rar');
            self::$enabledTypes[self::GZIP] = extension_loaded('zlib');
            self::$enabledTypes[self::BZIP] = extension_loaded('bz2');
            self::$enabledTypes[self::LZMA] = extension_loaded('xz');
            self::$enabledTypes[self::ISO] = class_exists('\CISOFile');
            self::$enabledTypes[self::CAB] = class_exists('\CabArchive');
            self::$enabledTypes[self::TAR] = class_exists('\Archive_Tar') || class_exists('\PharData');
            self::$enabledTypes[self::TAR_GZIP] = (class_exists('\Archive_Tar') || class_exists('\PharData')) && extension_loaded('zlib');
            self::$enabledTypes[self::TAR_BZIP] = (class_exists('\Archive_Tar') || class_exists('\PharData')) && extension_loaded('bz2');
            self::$enabledTypes[self::TAR_LZMA] = class_exists('\Archive_Tar') && extension_loaded('lzma2');
            self::$enabledTypes[self::TAR_LZW] = class_exists('\Archive_Tar') && LzwStreamWrapper::isBinaryAvailable();
        }
    }

    /**
     * Deprecated method for extracting files
     *
     * @param string $outputFolder
     * @param string|array|null $files
     * @deprecated 0.1.0
     * @see extractFiles()
     * @return bool|int
     * @throws Exception
     */
    public function extractNode($outputFolder, $files = null)
    {
        return $this->extractFiles($outputFolder, $files);
    }

    /**
     * Deprecated method for archiving files
     *
     * @param string|array $filesOrFiles
     * @param string $archiveName
     * @deprecated 0.1.0
     * @see archiveFiles()
     * @return mixed
     * @throws Exception
     */
    public static function archiveNodes($filesOrFiles, $archiveName)
    {
        return static::archiveFiles($filesOrFiles, $archiveName);
    }

    /**
     * Expands files list
     *
     * @param $archiveFiles
     * @param $files
     * @return array
     */
    protected static function expandFileList($archiveFiles, $files)
    {
        $newFiles = [];
        foreach ($files as $file) {
            foreach ($archiveFiles as $archiveFile) {
                if (fnmatch($file.'*', $archiveFile))
                    $newFiles[] = $archiveFile;
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
                    self::importFilesFromDir(rtrim($source, '/\\*').'/*',
                        !empty($destination) ? $destination.'/' : null, true, $files);
                else if (is_file($source))
                    $files[$destination] = $source;
            }

        } else if (is_string($nodes)) { // passed one file or directory
            // if is directory
            if (is_dir($nodes))
                self::importFilesFromDir(rtrim($nodes, '/\\*').'/*', null, true,
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
                self::importFilesFromDir(str_replace('\\', '/', $node).'*',
                    $destination.basename($node).'/', $recursive, $map);
            } elseif (is_file($node) && is_readable($node)) {
                $map[$destination.basename($node)] = $node;
            }
        }
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
}
