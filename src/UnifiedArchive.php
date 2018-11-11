<?php
namespace wapmorgan\UnifiedArchive;

use Archive7z\Archive7z;
use Exception;
use wapmorgan\UnifiedArchive\Formats\SevenZip;
use wapmorgan\UnifiedArchive\Formats\Zip;
use ZipArchive;

/**
 * Class which represents archive in one of supported formats.
 */
class UnifiedArchive extends BasicArchive
{
    const VERSION = '0.1.1';

    const ZIP = 'zip';
    const SEVEN_ZIP = '7zip';
    const RAR = 'rar';
    const GZIP = 'gzip';
    const BZIP = 'bzip2';
    const LZMA = 'lzma2';
    const ISO = 'iso';
    const CAB = 'cab';

    /** @var string */
    protected $type;

    /** @var \wapmorgan\UnifiedArchive\Formats\BasicFormat */
    protected $archive;

    /** @var array */
    protected $files;

    /** @var int Number of files */
    protected $filesQuantity;

    /** @var array List of archive format handlers */
    protected static $formatHandlers = [
        self::ZIP => 'Zip',
        self::SEVEN_ZIP => 'SevenZip',
        self::RAR => 'Rar',
        self::GZIP => 'Gzip',
        self::BZIP => 'Bzip',
        self::LZMA => 'Lzma',
        self::ISO => 'Iso',
        self::CAB => 'Cab',
    ];

    /** @var int */
    protected $uncompressedFilesSize;

    /** @var int */
    protected $compressedFilesSize;

    /** @var int */
    protected $archiveSize;

    /** @var ZipArchive */
    protected $zip;

    /** @var \Archive7z\Archive7z */
    protected $seven_zip;

    /** @var \RarArchive */
    protected $rar;

    /** @var array|null */
    protected $gzipStat;

    /** @var string */
    protected $gzipFilename;

    /** @var array */
    protected $bzipStat;

    /** @var string */
    protected $bzipFilename;

    /** @var string */
    protected $lzmaFilename;

    /** @var \CISOFile */
    protected $iso;

    /** @var int */
    protected $isoBlockSize;

    /** @var mixed */
    protected $isoFilesData;

    /** @var \CabArchive */
    protected $cab;

    /** @var array List of archive formats with support state */
    static protected $enabledTypes = [];

    /**
     * Creates instance with right type.
     * @param  string $fileName Filename
     * @return AbstractArchive|null Returns AbstractArchive in case of successful
     * parsing of the file
     * @throws \Exception
     */
    public static function open($fileName)
    {
        self::checkRequirements();

        if (!file_exists($fileName) || !is_readable($fileName))
            throw new Exception('Could not open file: '.$fileName);

        $type = self::detectArchiveType($fileName);
        if (!self::canOpenType($type, true)) {
            if (TarArchive::canOpenType($type)) {
                return TarArchive::open($fileName);
            }
            return null;
        }

        return new self($fileName, $type);
    }

    /**
     * Checks whether archive can be opened with current system configuration
     * @param string $fileName
     * @return boolean
     */
    public static function canOpenArchive($fileName)
    {
        self::checkRequirements();

        $type = self::detectArchiveType($fileName);
        if ($type !== false) {
            if (self::canOpenType($type, true)) {
                return true;
            } else if (TarArchive::canOpenType($type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether specific archive type can be opened with current system configuration
     *
     * @param $type
     *
     * @param bool $onOwn
     * @return boolean
     */
    public static function canOpenType($type, $onOwn = false)
    {
        self::checkRequirements();

        return (isset(self::$enabledTypes[$type]))
            ? self::$enabledTypes[$type]
            : ($onOwn ? false : TarArchive::canOpenType($type));
    }

    /**
     * Detect archive type by its filename or content.
     *
     * @param string $fileName
     * @param bool $contentCheck
     * @return string|boolean One of UnifiedArchive type constants OR false if type is not detected
     */
    public static function detectArchiveType($fileName, $contentCheck = true)
    {
        // by file name
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($ext, ['tar', 'tgz', 'tbz2', 'txz']) || preg_match('~\.tar\.(gz|bz2|xz|z)$~', strtolower($fileName))) {
            return TarArchive::detectArchiveType($fileName);
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
            }

            if ($type = TarArchive::detectArchiveType($fileName))
                return $type;
        }

        return false;
    }

    /**
     * Opens the file as one of supported formats.
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

        $handler_class = __NAMESPACE__.'\\Formats\\'.static::$formatHandlers[$type];

        $this->archive = new $handler_class($fileName);

        switch ($this->type) {
            case self::ISO:
                // load php-iso-files
                $this->iso = new \CISOFile;
                $this->iso->open($fileName);
                $this->iso->ISOInit();
                $size = 0;

                $usedDesc =
                    $this->iso->GetDescriptor(SUPPLEMENTARY_VOLUME_DESC);
                if (!$usedDesc)
                    $usedDesc = $this->iso->GetDescriptor(PRIMARY_VOLUME_DESC);
                $this->isoBlockSize = $usedDesc->iBlockSize;
                $directories = $usedDesc->LoadMPathTable($this->iso);
                foreach ($directories as $Directory) {
                    $directory = $Directory->GetFullPath($directories, false);
                    $directory = trim($directory, '/');
                    if ($directory != '') {
                        $directory .= '/';
                        $this->files[$Directory->Location] = $directory;
                    }
                    $this->isoCatalogsStructure[$Directory->Location]
                        = $directory;

                    $files = $Directory->LoadExtents($this->iso,
                        $usedDesc->iBlockSize, true);
                    if ($files) {
                        foreach ($files as $file) {
                            if (in_array($file->strd_FileId, ['.', '..']))
                                continue;
                            $this->files[$file->Location]
                                = $directory . $file->strd_FileId;
                            $size += $file->DataLen;

                            $this->isoFilesData[$directory . $file->strd_FileId] =
                                [
                                    'size' => $file->DataLen,
                                    'mtime' =>
                                        strtotime((string)$file->isoRecDate),
                                ];
                        }
                    }
                    // break;
                }
                $this->uncompressedFilesSize = $this->compressedFilesSize = $size;

                break;

            case self::CAB:
                try {
                    $this->cab = new \CabArchive($fileName);
                } catch (Exception $e) {
                    throw new Exception('Could not open Cab archive: '.$e->getMessage(), $e->getCode(), $e);
                }
                foreach ($this->cab->getFileNames() as $file) {
                    $this->files[] = $file;
                    $file_info = $this->cab->getFileData($file);
                    $this->uncompressedFilesSize += $file_info->size;
                    $this->compressedFilesSize += $file_info->packedSize;
                }
                break;

            default:

        }

        $this->scanArchive();
    }

    /**
     * Returns an instance of class implementing PclZipOriginalInterface
     * interface.
     *
     * @return PclZipOriginalInterface Returns an instance of a class
     * implementing PclZipOriginalInterface
     * @throws Exception
     */
    public function getPclZipInterface()
    {
        switch ($this->type) {
            case 'zip':
                return new PclZipLikeZipArchiveInterface($this->zip);
        }

        throw new Exception('PclZip-like interface IS NOT available for '.$this->type.' archive format');
    }

    /**
     * Closes archive.
     */
    public function __destruct()
    {
        switch ($this->type) {

            case self::ISO:
                $this->iso->close();
            break;

            case self::CAB:
                unset($this->cab);
            break;
        }
    }

    /**
     * Counts number of files
     * @return int
     */
    public function countFiles()
    {
        switch ($this->type) {
            case self::ZIP:
                return $this->zip->numFiles;

            case self::SEVEN_ZIP:
                return $this->seven_zip->numFiles;

            case self::RAR:
                return $this->rar->numberOfFiles;

            case self::ISO:
                return count($this->files);

            case self::CAB:
                return $this->cab->filesCount;
        }
    }

    /**
     * Counts size of all uncompressed data (bytes)
     * @return int
     */
    public function countUncompressedFilesSize()
    {
        return $this->uncompressedFilesSize;
    }

    /**
     * Returns size of archive
     * @return int
     */
    public function getArchiveSize()
    {
        return $this->archiveSize;
    }

    /**
     * Returns type of archive
     * @return string
     */
    public function getArchiveType()
    {
        return $this->type;
    }

    /**
     * Counts size of all compressed data (in bytes)
     * @return int
     */
    public function countCompressedFilesSize()
    {
        return $this->compressedFilesSize;
    }

    /**
     * Returns list of files
     * @return array List of files
     */
    public function getFileNames()
    {
        return array_values($this->files);
    }

    /**
     * Checks that file exists in archive
     * @param string $fileName
     * @return bool
     */
    public function isFileExists($fileName)
    {
        return in_array($fileName, $this->files, true);
    }

    /**
     * Returns file metadata
     * @param string $fileName
     * @return ArchiveEntry|bool
     */
    public function getFileData($fileName)
    {
        if (!in_array($fileName, $this->files, true))
            return false;

        return $this->archive->getFileData($fileName);

        switch ($this->type) {
            case self::CAB:
                $data = $this->cab->getFileData($fileName);

                return new ArchiveEntry($fileName, $data->packedSize, $data->size, $data->unixtime, $data->is_compressed);

            default:
                return false;
        }
    }

    /**
     * Returns file content
     *
     * @param $fileName
     *
     * @return bool|string
     * @throws \Archive7z\Exception
     * @throws \Exception
     */
    public function getFileContent($fileName)
    {
        if (!in_array($fileName, $this->files, true))
            return false;

        switch ($this->type) {
            case self::ISO:
                $Location = array_search($fileName, $this->files, true);
                if (!isset($this->isoFilesData[$fileName])) return false;
                $data = $this->isoFilesData[$fileName];
                $Location_Real = $Location * $this->isoBlockSize;
                if ($this->iso->Seek($Location_Real, SEEK_SET) === false)
                    return false;

                return $this->iso->Read($data['size']);

            case self::CAB:
                return $this->cab->getFileContent($fileName);

            default:
                return false;
        }
    }

    /**
     * Returns a resource for reading file from archive
     * @param string $fileName
     * @return bool|resource|string
     * @throws \Archive7z\Exception
     */
    public function getFileResource($fileName)
    {
        if (!in_array($fileName, $this->files, true))
            return false;

        switch ($this->type) {
            case self::ZIP:

            case self::SEVEN_ZIP:


            case self::RAR:


            case self::GZIP:
                return gzopen($this->gzipFilename, 'rb');

            case self::BZIP:


            case self::LZMA:


            case self::ISO:
                $Location = array_search($fileName, $this->files, true);
                if (!isset($this->isoFilesData[$fileName])) return false;
                $data = $this->isoFilesData[$fileName];
                $Location_Real = $Location * $this->isoBlockSize;
                if ($this->iso->Seek($Location_Real, SEEK_SET) === false)
                    return false;

                $resource = fopen('php://temp', 'r+');
                fwrite($resource, $this->iso->Read($data['size']));
                rewind($resource);
                return $resource;

            case self::CAB:
                $resource = fopen('php://temp', 'r+');
                fwrite($resource, $this->cab->getFileContent($fileName));
                rewind($resource);
                return $resource;

            default:
                return false;
        }
    }

    /**
     * Returns hierarchy
     * @return array
     */
    public function getHierarchy()
    {
        $tree = array(DIRECTORY_SEPARATOR);
        foreach ($this->files as $filename) {
            if (in_array(substr($filename, -1), array('/', '\\')))
                $tree[] = DIRECTORY_SEPARATOR.$filename;
        }

        return $tree;
    }

    /**
     * Unpacks files to disk.
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
     * Updates existing archive by removing files from it.
     * Only 7zip and zip types support deletion.
     * @param string|string[] $fileOrFiles
     * @param bool $expandFilesList
     *
     * @return bool|int
     * @throws Exception
     */
    public function deleteFiles($fileOrFiles, $expandFilesList = false)
    {
        if ($expandFilesList && $fileOrFiles !== null)
            $fileOrFiles = self::expandFileList($this->files, is_string($fileOrFiles) ? [$fileOrFiles] : $fileOrFiles);

        $files = is_string($fileOrFiles) ? array($fileOrFiles) : $fileOrFiles;
        foreach ($files as $i => $file) {
            if (!in_array($file, $this->files, true)) unset($files[$i]);
        }

        $result = $this->archive->deleteFiles($files);
        $this->scanArchive();
        return $result;
    }

    /**
     * Updates existing archive by adding new files.
     *
     * @param string[] $fileOrFiles
     *
     * @return int|bool False if failed, number of added files if success
     * @throws \Archive7z\Exception
     * @throws Exception
     */
    public function addFiles($fileOrFiles)
    {
        $files_list = self::createFilesList($fileOrFiles);
        $result = $this->archive->addFiles($files_list);
        $this->scanArchive();
        return $result;
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
     * Creates an archive.
     * @param string|string[]|array $fileOrFiles
     * @param $archiveName
     * @param bool $emulate
     * @return array|bool|int
     * @throws Exception
     */
    public static function archiveFiles($fileOrFiles, $archiveName, $emulate = false)
    {
        if (file_exists($archiveName))
            throw new Exception('Archive '.$archiveName.' already exists!');

        self::checkRequirements();

        $archiveType = self::detectArchiveType($archiveName, false);
        if (in_array($archiveType, [TarArchive::TAR, TarArchive::TAR_GZIP, TarArchive::TAR_BZIP, TarArchive::TAR_LZMA, TarArchive::TAR_LZW], true))
            return TarArchive::archiveFiles($fileOrFiles, $archiveName, $emulate);
        if ($archiveType === false)
            return false;

        $files_list = self::createFilesList($fileOrFiles);

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

        $handler_class = __NAMESPACE__.'\\Formats\\'.static::$formatHandlers[$archiveType];

        return $handler_class::createArchive($files_list, $archiveName);
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

            if (class_exists('\RarException'))
                \RarException::setUsingExceptions(true);
        }
    }
}
