<?php
namespace wapmorgan\UnifiedArchive;
use Archive7z\Archive7z;
use Exception;
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

    /** @var array */
    protected $files;

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
            throw new Exception('Count not open file: '.$fileName);

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
     * @throws Exception
     */
    public function __construct($fileName, $type)
    {
        self::checkRequirements();

        $this->type = $type;
        $this->archiveSize = filesize($fileName);

        switch ($this->type) {
            case self::ZIP:
                $this->zip = new ZipArchive();
                $open_result = $this->zip->open($fileName);
                if ($open_result !== true) {
                    throw new Exception('Could not open Zip archive: '.$open_result);
                }
                break;

            case self::SEVEN_ZIP:
                try {
                    $this->seven_zip = new Archive7z($fileName);
                } catch (\Archive7z\Exception $e) {
                    throw new Exception('Could not open 7Zip archive: '.$e->getMessage(), $e->getCode(), $e);
                }
                break;

            case self::RAR:
                $this->rar = \RarArchive::open($fileName);
                if ($this->rar === false) {
                    throw new Exception('Could not open Rar archive');
                }
                $Entries = @$this->rar->getEntries();
                if ($Entries === false) {
                    $this->rar->numberOfFiles =
                    $this->compressedFilesSize =
                    $this->uncompressedFilesSize = 0;
                } else {
                    $this->rar->numberOfFiles = count($Entries); # rude hack
                    foreach ($Entries as $i => $entry) {
                        $this->files[$i] = $entry->getName();
                        $this->compressedFilesSize += $entry->getPackedSize();
                        $this->uncompressedFilesSize +=
                            $entry->getUnpackedSize();
                    }
                }
                break;

            case self::GZIP:
                $this->files = [basename($fileName, '.gz')];
                $this->gzipFilename = $fileName;
                $this->gzipStat = gzip_stat($fileName);
                if ($this->gzipStat === false) {
                    throw new Exception('Could not open Gzip file');
                }
                $this->compressedFilesSize = $this->archiveSize;
                $this->uncompressedFilesSize = $this->gzipStat['size'];
                break;

            case self::BZIP:
                $this->files = [basename($fileName, '.bz2')];
                $this->bzipFilename = $fileName;
                $this->bzipStat = ['mtime' => filemtime($fileName)];
                $this->compressedFilesSize = $this->archiveSize;
                $this->uncompressedFilesSize = $this->archiveSize;
                break;

            case self::LZMA:
                $this->files = [basename($fileName, '.xz')];
                $this->lzmaFilename = $fileName;
                $this->bzipStat = ['mtime' => filemtime($fileName)];
                $this->compressedFilesSize = $this->archiveSize;
                $this->uncompressedFilesSize = $this->archiveSize;
                break;

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
                throw new Exception('Unsupported archive type: '.$type.' for archive '.$fileName);
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
    public function pclzipInterace()
    {
        switch ($this->type) {
            case 'zip':
                return new PclZipLikeZipArchiveInterface($this->zip);
        }

        throw new Exception(basename(__FILE__).', line '.__LINE__.' : PclZip-like interface IS'.
         'NOT available for '.$this->type.' archive format');
    }

    /**
     * Closes archive.
     */
    public function __destruct()
    {
        switch ($this->type) {
            case self::ZIP:
                unset($this->zip);
            break;

            case self::SEVEN_ZIP:
                unset($this->seven_zip);
            break;

            case self::RAR:
                $this->rar->close();
            break;

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

            case self::GZIP:
            case self::BZIP:
            case self::LZMA:
                return 1;

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

        switch ($this->type) {
            case self::ZIP:
                $index = array_search($fileName, $this->files, true);
                $stat = $this->zip->statIndex($index);
                return new ArchiveEntry($fileName, $stat['comp_size'], $stat['size'], $stat['mtime'],
                    $stat['comp_method'] != 0);

            case self::SEVEN_ZIP:
                $entry = $this->seven_zip->getEntry($fileName);
                $size = $entry->getSize();

                return new ArchiveEntry($fileName, $size, ceil($size * ($this->compressedFilesSize / $this->uncompressedFilesSize)),
                    strtotime($entry->getModified()), $this->compressedFilesSize != $this->uncompressedFilesSize);

            case self::RAR:
                $entry = $this->rar->getEntry($fileName);

                return new ArchiveEntry($fileName, $entry->getPackedSize(), $entry->getUnpackedSize(),
                    strtotime($entry->getFileTime()), $entry->getMethod() != 48);

            case self::GZIP:
            case self::BZIP:
            case self::LZMA:

                return new ArchiveEntry($fileName, $this->archiveSize,
                    $this->type === self::GZIP ? $this->gzipStat['size'] : $this->archiveSize,
                    $this->type === self::GZIP ? $this->gzipStat['mtime'] : ($this->type === self::BZIP ? $this->bzipStat['mtime'] : 0),
                    true);

            case self::ISO:
                if (!isset($this->isoFilesData[$fileName]))
                    return false;
                $data = $this->isoFilesData[$fileName];

                return new ArchiveEntry($fileName, $data['size'], $data['size'], $data['mtime'], false);

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
            case self::ZIP:
                $index = array_search($fileName, $this->files, true);

                return $this->zip->getFromIndex($index);

            case self::SEVEN_ZIP:
                $entry = $this->seven_zip->getEntry($fileName);

                return $entry->getContent();

            case self::RAR:
                $entry = $this->rar->getEntry($fileName);
                if ($entry->isDirectory()) return false;
                return stream_get_contents($entry->getStream());

            case self::GZIP:
                return gzdecode(file_get_contents($this->gzipFilename));

            case self::BZIP:
                return bzdecompress(file_get_contents($this->bzipFilename));

            case self::LZMA:
                return stream_get_contents(xzopen($this->lzmaFilename, 'r'));

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
                return $this->zip->getStream($fileName);

            case self::SEVEN_ZIP:
                $resource = fopen('php://temp', 'r+');
                $entry = $this->seven_zip->getEntry($fileName);

                fwrite($resource, $entry->getContent());
                rewind($resource);
                return $resource;

            case self::RAR:
                $entry = $this->rar->getEntry($fileName);
                if ($entry->isDirectory()) return false;
                return $entry->getStream();

            case self::GZIP:
                return gzopen($this->gzipFilename, 'rb');

            case self::BZIP:
                return bzopen($this->bzipFilename, 'r');

            case self::LZMA:
                return xzopen($this->lzmaFilename, 'r');

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
     * Unpacks node with its content to disk. Pass any node from getHierarchy()
     * method.
     * @param $outputFolder
     * @param string|array|null $files
     * @return bool|int
     * @throws \Archive7z\Exception
     */
    public function extractFiles($outputFolder, $files = null, $expandFilesList = false)
    {
        if ($expandFilesList && $files !== null)
            $files = self::expandFileList($this->files, is_string($files) ? [$files] : $files);

        switch ($this->type) {
            case self::ZIP:
                $entries = array();
                if ($files === null) {
                    $entries = array_values($this->files);
                } else {
                    foreach ($this->files as $fname) {
                        if (strpos($fname, $files) === 0) {
                            $entries[] = $fname;
                        }
                    }
                }
                $result = $this->zip->extractTo($outputFolder, $entries);
                if ($result === true) {
                    return count($entries);
                }
                return false;

            case self::SEVEN_ZIP:
                if (!is_dir($outputFolder))
                    mkdir($outputFolder);
                $this->seven_zip->setOutputDirectory($outputFolder);
                $count = 0;
                if ($files === null) {
                    try {
                        $this->seven_zip->extract();
                        return $this->seven_zip->numFiles;
                    } catch (Exception $e) {
                        return false;
                    }
                } else {
                    foreach ($this->files as $fname) {
                        if (strpos($fname, $files) === 0) {
                            if ($this->seven_zip->extractEntry($fname))
                                $count++;
                        }
                    }
                }
                return $count;

            case self::RAR:
                $count = 0;
                foreach ($this->files as $fname) {
                    if ($files === null || strpos($fname, $files) === 0) {
                        if ($this->rar->getEntry($fname)
                            ->extract($outputFolder)) {
                            $count++;
                        }
                    }
                }
                return $count;
            break;

            case self::GZIP:
                if ($files === null || $files === $this->files) {
                    $dir = rtrim($outputFolder, '/').'/';
                    if (!is_dir($dir) && !mkdir($dir))
                        return false;
                    if (file_put_contents($dir.
                        basename($this->gzipFilename, '.gz'),
                        gzdecode(file_get_contents($this->gzipFilename)))
                        !== false)
                        return 1;
                    else
                        return false;
                }
                return 0;

            case self::BZIP:
                if ($files === null || $files === $this->files) {
                    $dir = rtrim($outputFolder, '/').'/';
                    if (!is_dir($dir) && !mkdir($dir))
                        return false;
                    if (file_put_contents($dir.
                        basename($this->bzipFilename, '.bz2'),
                        bzdecompress(file_get_contents($this->bzipFilename)))
                        !== false)
                        return 1;
                    else
                        return false;
                }
                return 0;

            case self::LZMA:
                if ($files === null || $files === $this->files) {
                    $dir = rtrim($outputFolder, '/').'/';
                    if (!is_dir($dir) && !mkdir($dir))
                        return false;
                    $fp = xzopen($this->lzmaFilename, 'r');
                    ob_start();
                    xzpassthru($fp);
                    $content = ob_get_flush();
                    xzclose($fp);
                    if (file_put_contents($dir.
                        basename($this->lzmaFilename, '.xz'),
                        $content)
                        !== false)
                        return 1;
                    else
                        return false;
                }
                return 0;

            default:
                return false;
        }
    }

    /**
     * Updates existing archive by removing files from it.
     *
     * @param string|string[] $fileOrFiles
     * @param bool            $expandFilesList
     *
     * @return bool|int
     */
    public function deleteFiles($fileOrFiles, $expandFilesList = false)
    {
        if ($expandFilesList && $fileOrFiles !== null)
            $fileOrFiles = self::expandFileList($this->files, is_string($fileOrFiles) ? [$fileOrFiles] : $fileOrFiles);

        $files = is_string($fileOrFiles) ? array($fileOrFiles) : $fileOrFiles;
        foreach ($files as $i => $file) {
            if (!in_array($file, $this->files, true)) unset($files[$i]);
        }

        $count = 0;

        switch ($this->type) {
            case self::ZIP:
                $count = 0;
                foreach ($files as $file) {
                    $index = array_search($file, $this->files, true);
                    if ($this->zip->deleteIndex($index))
                        $count++;
                }
            break;

            case self::SEVEN_ZIP:
                try {
                    foreach ($files as $file) {
                        $this->seven_zip->delEntry($file);
                        unset($this->files[array_search($file, $this->files, true)]);
                        $count++;
                    }
                } catch (Exception $e) {
                    return false;
                }
            break;

            default:
                return false;
        }

        $this->scanArchive();

        return $count;
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

        $added_files = 0;

        switch ($this->type) {
            case self::ZIP:
                foreach ($files_list as $localname => $filename) {
                    if (is_null($filename)) {
                        if ($this->zip->addEmptyDir($localname) === false)
                            return false;
                    } else {
                        if ($this->zip->addFile($filename, $localname) === false)
                            return false;
                        $added_files++;
                    }
                }

                // reopen archive to save changes
                $archive_filename = $this->zip->filename;
                $this->zip->close();
                $open_result = $this->zip->open($archive_filename);
                if ($open_result !== true) {
                    throw new Exception('Could not open Zip archive: '.$open_result);
                }
            break;

            case self::SEVEN_ZIP:
                foreach ($files_list as $localname => $filename) {
                    if (!is_null($filename)) {
                        try {
                            $this->seven_zip->addEntry($filename);
                            $this->seven_zip->renameEntry($filename, $localname);
                            $added_files++;
                        } catch (Exception $e) {
                            return false;
                        }
                    }
                }
            break;

            default:
                return false;
        }

        $this->scanArchive();

        return $added_files;
    }

    /**
     * Rescans array after modification
     */
    protected function scanArchive()
    {
        switch ($this->type) {
            case self::ZIP:
                $this->compressedFilesSize = $this->uncompressedFilesSize = $this->zip->numFiles = 0;
                $this->files = [];
                for ($i = 0; $i < $this->zip->numFiles; $i++) {
                    $file = $this->zip->statIndex($i);
                    $this->files[$i] = $file['name'];
                    $this->compressedFilesSize += $file['comp_size'];
                    $this->uncompressedFilesSize += $file['size'];
                }
                break;

            case self::SEVEN_ZIP:
                $this->compressedFilesSize = $this->uncompressedFilesSize = 0;
                $this->files = [];
                foreach ($this->seven_zip->getEntries() as $entry) {
                    $this->files[] = $entry->getPath();
                    $this->compressedFilesSize += (int)$entry->getPackedSize();
                    $this->uncompressedFilesSize += (int)$entry->getSize();
                }
                $this->seven_zip->numFiles = count($this->files);
                break;
        }
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

        $atype = self::detectArchiveType($archiveName, false);
        if (in_array($atype, [TarArchive::TAR, TarArchive::TAR_GZIP, TarArchive::TAR_BZIP, TarArchive::TAR_LZMA, TarArchive::TAR_LZW], true))
            return TarArchive::archiveFiles($fileOrFiles, $archiveName, $emulate);
        if ($atype === false)
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
                'type' => $atype,
            );
        }

        switch ($atype) {
            case self::ZIP:
                $zip = new ZipArchive;
                $result = $zip->open($archiveName, ZipArchive::CREATE);
                if ($result !== true)
                    throw new Exception('ZipArchive error: '.$result);
                foreach ($files_list as $localname => $filename) {
                    if ($filename === null) {
                        if ($zip->addEmptyDir($localname) === false)
                            return false;
                    } else {
                        if ($zip->addFile($filename, $localname) === false)
                            return false;
                    }
                }
                $zip->close();

                return count($files_list);

            case self::SEVEN_ZIP:
                $seven_zip = new Archive7z($archiveName);
                try {
                    foreach ($files_list as $localname => $filename) {
                        if ($filename !== null) {
                            $seven_zip->addEntry($filename, false);
                            $seven_zip->renameEntry($filename, $localname);
                        }
                    }
                    unset($seven_zip);
                } catch (Exception $e) {
                    return false;
                }

                return count($files_list);

            case self::GZIP:
                if (count($files_list) > 1) return false;
                $filename = array_shift($files_list);
                if (is_null($filename)) return false; // invalid list
                if (file_put_contents($archiveName,
                        gzencode(file_get_contents($filename))) !== false)
                    return 1;

                return false;

            case self::BZIP:
                if (count($files_list) > 1) return false;
                $filename = array_shift($files_list);
                if (is_null($filename)) return false; // invalid list
                if (file_put_contents($archiveName,
                        bzcompress(file_get_contents($filename))) !== false)
                    return 1;

                return false;

            case self::LZMA:
                if (count($files_list) > 1) return false;
                $filename = array_shift($files_list);
                if (is_null($filename)) return false; // invalid list
                $fp = xzopen($archiveName, 'w');
                $r = xzwrite($fp, file_get_contents($filename));
                xzclose($fp);
                if ($r !== false)
                    return 1;

                return false;

            default:
                return false;
        }
    }

    /**
     *
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
        }
    }
}
