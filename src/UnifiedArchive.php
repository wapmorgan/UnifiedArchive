<?php
namespace wapmorgan\UnifiedArchive;
use Archive7z\Archive7z;
use Exception;
use ZipArchive;

/**
 * Class which represents archive in one of supported formats.
 */
class UnifiedArchive extends AbstractArchive
{
    const VERSION = '0.1.x';

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

    /**
     * Creates instance with right type.
     * @param  string $fileName Filename
     * @return AbstractArchive|null Returns AbstractArchive in case of successful
     * parsing of the file
     * @throws \Archive7z\Exception
     * @throws Exception
     */
    public static function open($fileName)
    {
        // determine archive type
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext == 'zip' && extension_loaded('zip'))
            return new self($fileName, self::ZIP);
        if ($ext == '7z' && class_exists('\Archive7z\Archive7z'))
            return new self($fileName, self::SEVEN_ZIP);
        if ($ext == 'rar' && extension_loaded('rar'))
            return new self($fileName, self::RAR);
        if ((in_array($ext, ['tar', 'tgz', 'tbz2', 'txz']) || preg_match('~\.tar\.(gz|bz2|xz|Z)$~', $fileName))
            && ($archive = TarArchive::open($fileName)) !== null)
            return $archive;
        if ($ext == 'gz' && extension_loaded('zlib'))
            return new self($fileName, self::GZIP);
        if ($ext == 'bz2' && extension_loaded('bz2'))
            return new self($fileName, self::BZIP);
        if ($ext == 'xz' && extension_loaded('xz'))
            return new self($fileName, self::LZMA);
        if ($ext == 'iso' && class_exists('\CISOFile'))
            return new self($fileName, self::ISO);
        if ($ext == 'cab' && class_exists('\CabArchive'))
            return new self($fileName, self::CAB);
        return null;
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
        $this->type = $type;
        $this->archiveSize = filesize($fileName);

        switch ($this->type) {
            case self::ZIP:
                $this->zip = new ZipArchive;
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
                    break;
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

        throw new Exception(basename(__FILE__).', line '.__LINE.' : PclZip-like interface IS'.
         'NOT available for '.$this->type.' archive format');
    }

    /**
     * Closes archive.
     */
    public function __destruct()
    {
        switch ($this->type) {
            case self::ZIP:
                // $this->zip->close();
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
     * Counts size of all uncompressed data
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
     * Counts size of all compressed data
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
     * Retrieves file data
     * @return ArchiveEntry|bool
     */
    public function getFileData($fileName)
    {
        switch ($this->type) {
            case self::ZIP:
                if (!in_array($fileName, $this->files)) return false;
                $index = array_search($fileName, $this->files);
                $stat = $this->zip->statIndex($index);
                return new ArchiveEntry($fileName, $stat['comp_size'], $stat['size'], $stat['mtime'],
                    $stat['comp_method'] != 0);

            case self::SEVEN_ZIP:
                if (!in_array($fileName, $this->files)) return false;
                $entry = $this->seven_zip->getEntry($fileName);

                $size = $entry->getSize();

                return new ArchiveEntry($fileName, $size, ceil($size * ($this->compressedFilesSize / $this->uncompressedFilesSize)),
                    strtotime($entry->getModified()), $this->compressedFilesSize != $this->uncompressedFilesSize);

            case self::RAR:
                if (!in_array($fileName, $this->files)) return false;
                $entry = $this->rar->getEntry($fileName);

                return new ArchiveEntry($fileName, $entry->getPackedSize(), $entry->getUnpackedSize(),
                    strtotime($entry->getFileTime()), $entry->getMethod() != 48);

            case self::GZIP:
            case self::BZIP:
            case self::LZMA:
                if (!in_array($fileName, $this->files)) return false;

                return new ArchiveEntry($fileName, $this->archiveSize,
                    $this->type === self::GZIP ? $this->gzipStat['size'] : $this->archiveSize,
                    $this->type === self::GZIP ? $this->gzipStat['mtime'] : ($this->type === self::BZIP ? $this->bzipStat['mtime'] : 0),
                    true);

            case self::ISO:
                if (!in_array($fileName, $this->files)) return false;
                if (!isset($this->isoFilesData[$fileName])) return false;
                $data = $this->isoFilesData[$fileName];

                return new ArchiveEntry($fileName, $data['size'], $data['size'], $data['mtime'], false);

            case self::CAB:
                if (!in_array($fileName, $this->files)) return false;
                $data = $this->cab->getFileData($fileName);
                return new ArchiveEntry($fileName, $data->packedSize, $data->size, $data->unixtime, $data->is_compressed);
        }
    }

    /**
     * Extracts file content
     * @param $filename
     * @return bool|string
     * @throws \Archive7z\Exception
     */
    public function getFileContent($filename)
    {
        switch ($this->type) {
            case self::ZIP:
                if (!in_array($filename, $this->files)) return false;
                $index = array_search($filename, $this->files);

                return $this->zip->getFromIndex($index);
            break;

            case self::SEVEN_ZIP:
                if (!in_array($filename, $this->files)) return false;
                $entry = $this->seven_zip->getEntry($filename);
                return $entry->getContent();
            break;

            case self::RAR:
                if (!in_array($filename, $this->files)) return false;
                $entry = $this->rar->getEntry($filename);
                if ($entry->isDirectory()) return false;
                // create temp file
                $tmpname = tempnam(sys_get_temp_dir(), 'RarFile');
                $entry->extract(dirname(__FILE__), $tmpname);
                $data = file_get_contents($tmpname);
                unlink($tmpname);

                return $data;
            break;

            case self::GZIP:
                if (!in_array($filename, $this->files)) return false;
                return gzdecode(file_get_contents($this->gzipFilename));
            break;

            case self::BZIP:
                if (!in_array($filename, $this->files)) return false;
                return bzdecompress(file_get_contents($this->bzipFilename));
            break;

            case self::LZMA:
                if (!in_array($filename, $this->files)) return false;
                $fp = xzopen($this->lzmaFilename, 'r');
                ob_start();
                xzpassthru($fp);
                $content = ob_get_flush();
                xzclose($fp);
                return $content;
            break;

            case self::ISO:
                if (!in_array($filename, $this->files)) return false;
                $Location = array_search($filename, $this->files);
                if (!isset($this->isoFilesData[$filename])) return false;
                $data = $this->isoFilesData[$filename];
                $Location_Real = $Location * $this->isoBlockSize;
                if ($this->iso->Seek($Location_Real, SEEK_SET) === false)
                    return false;
                return $this->iso->Read($data['size']);
            break;

            default:
                return false;
        }
    }

    /**
     * Returns hierarchy
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
    public function extractFiles($outputFolder, $files = null)
    {
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
                } else {
                    return false;
                }
            break;

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
            break;

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
                if ($files === null) {
                    $dir = rtrim($outputFolder, '/').'/';
                    if (!is_dir($dir)) mkdir($dir);
                    if (file_put_contents($dir.
                        basename($this->gzipFilename, '.gz'),
                        gzdecode(file_get_contents($this->gzipFilename)))
                        !== false)
                        return 1;
                    else
                        return false;
                } else {
                    return 0;
                }
            break;

            case self::BZIP:
                if ($files === null) {
                    $dir = rtrim($outputFolder, '/').'/';
                    if (!is_dir($dir)) mkdir($dir);
                    if (file_put_contents($dir.
                        basename($this->bzipFilename, '.bz2'),
                        bzdecompress(file_get_contents($this->bzipFilename)))
                        !== false)
                        return 1;
                    else
                        return false;
                } else {
                    return 0;
                }
            break;

            case self::LZMA:
                if ($files === null) {
                    $dir = rtrim($outputFolder, '/').'/';
                    if (!is_dir($dir)) mkdir($dir);
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
                } else {
                    return 0;
                }
            break;

            default:
                return false;
        }
    }

    /**
     * Updates existing archive by removing files from it.
     * @param string|string[] $fileOrFiles
     * @return bool|int
     */
    public function deleteFiles($fileOrFiles)
    {
        $files = is_string($fileOrFiles) ? array($fileOrFiles) : $fileOrFiles;
        foreach ($files as $i => $file) {
            if (!in_array($file, $this->files)) unset($files[$i]);
        }

        switch ($this->type) {
            case self::ZIP:
                $count = 0;
                foreach ($files as $file) {
                    $index = array_search($file, $this->files);
                    $stat = $this->zip->statIndex($index);
                    if ($this->zip->deleteIndex($index))
                        $count++;
                }
            break;

            case self::SEVEN_ZIP:
                foreach ($files as $file) {
                    $this->seven_zip->delEntry($file);
                    unset($this->files[array_search($file, $this->files)]);
                }

                $this->seven_zip->numFiles = count($this->files);
            break;

            default:
                return false;
        }

        $this->scanArchive();

        return isset($count) ? $count : false;
    }

    /**
     * Updates existing archive by adding new files.
     * @param string[] $fileOrFiles
     * @return int|bool
     * @throws \Archive7z\Exception
     */
    public function addFiles($fileOrFiles)
    {
        $files_list = self::createFilesList($fileOrFiles);

        switch ($this->type) {
            case self::ZIP:
                foreach ($files_list as $localname => $filename) {
                    if (is_null($filename)) {
                        if ($this->zip->addEmptyDir($localname) === false)
                            return false;
                    } else {
                        if ($this->zip->addFile($filename, $localname) === false)
                            return false;
                    }
                }

                $this->files = array();
                $this->compressedFilesSize =
                $this->uncompressedFilesSize = 0;
                for ($i = 0; $i < $this->zip->numFiles; $i++) {
                    $file = $this->zip->statIndex($i);
                    $this->files[$i] = $file['name'];
                    $this->compressedFilesSize += $file['comp_size'];
                    $this->uncompressedFilesSize += $file['size'];
                }
            break;

            case self::SEVEN_ZIP:
                foreach ($files_list as $localname => $filename) {
                    if (!is_null($filename)) {
                        $this->seven_zip->addEntry($filename, false, $localname);
                    }
                }

                $this->files = array();
                $this->compressedFilesSize =
                $this->uncompressedFilesSize = 0;
                foreach ($this->seven_zip->getEntries() as $entry) {
                    $this->files[] = $entry->getPath();
                    $this->compressedFilesSize += $entry->getPackedSize();
                    $this->uncompressedFilesSize += $entry->getSize();
                }
                $this->seven_zip->numFiles = count($this->files);
            break;

            default:
                return false;
        }

        $this->scanArchive();

        return count($this->files);
    }

    /**
     * Creates an archive.
     * @param array $filesOrFiles
     * @param $archiveName
     * @param bool $fake
     * @return array|bool|int
     * @throws Exception
     */
    public static function archiveFiles($filesOrFiles, $archiveName, $fake = false)
    {
        $ext = strtolower(pathinfo($archiveName, PATHINFO_EXTENSION));
        if ($ext == 'zip') $atype = self::ZIP;
        else if ($ext == '7z') $atype = self::SEVEN_ZIP;
        else if ($ext == 'rar') $atype = self::RAR;
        else if (in_array($ext, ['tar', 'tgz', 'tbz2', 'txz'], true) || preg_match('~\.tar\.(gz|bz2|xz|Z)$~i', $archiveName))
            return TarArchive::archiveFiles($filesOrFiles, $archiveName, $fake);
        else if ($ext == 'gz') $atype = self::GZIP;
        else if ($ext == 'bz2') $atype = self::BZIP;
        else if ($ext == 'xz') $atype = self::LZMA;
        else return false;

        $files_list = self::createFilesList($filesOrFiles);

        // fake creation: return archive data
        if ($fake) {
            $totalSize = 0;
            foreach ($files_list as $fn) $totalSize += filesize($fn);

            return array(
                'totalSize' => $totalSize,
                'numberOfFiles' => count($files_list),
                'files' => $files_list,
            );
        }

        switch ($atype) {
            case self::ZIP:
                $zip = new ZipArchive;
                $result = $zip->open($archiveName, ZipArchive::CREATE);
                if ($result !== true)
                    throw new Exception('ZipArchive error: '.$result);
                foreach ($files_list as $localname => $filename) {
                    /*echo "added ".$filename.PHP_EOL;
                    echo number_format(filesize($filename)).PHP_EOL;
                    */
                    if (is_null($filename)) {
                        if ($zip->addEmptyDir($localname) === false)
                            return false;
                    } else {
                        if ($zip->addFile($filename, $localname) === false)
                            return false;
                    }
                }
                $zip->close();

                return count($files_list);
            break;

            case self::SEVEN_ZIP:
                $seven_zip = new Archive7z($archiveName);
                foreach ($files_list as $localname => $filename) {
                    if (!is_null($filename)) {
                        $seven_zip->addEntry($filename, false, $localname);
                    }
                }
                unset($seven_zip);
                return count($files_list);
            break;

            case self::GZIP:
                if (count($files_list) > 1) return false;
                $filename = array_shift($files_list);
                if (is_null($filename)) return false; // invalid list
                if (file_put_contents($archiveName,
                    gzencode(file_get_contents($filename))) !== false)
                    return 1;
                else
                    return false;
            break;

            case self::BZIP:
                if (count($files_list) > 1) return false;
                $filename = array_shift($files_list);
                if (is_null($filename)) return false; // invalid list
                if (file_put_contents($archiveName,
                    bzcompress(file_get_contents($filename))) !== false)
                    return 1;
                else
                    return false;
            break;

            case self::LZMA:
                if (count($files_list) > 1) return false;
                $filename = array_shift($files_list);
                if (is_null($filename)) return false; // invalid list
                $fp = xzopen($archiveName, 'w');
                $r = xzwrite($fp, file_get_contents($filename));
                xzclose($fp);
                if ($r !== false)
                    return 1;
                else
                    return false;
            break;

            default:
                return false;
        }
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
}
