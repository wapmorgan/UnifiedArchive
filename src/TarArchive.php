<?php
namespace wapmorgan\UnifiedArchive;

use Archive_Tar;
use Exception;
use FilesystemIterator;
use Phar;
use PharData;
use RecursiveIteratorIterator;

class TarArchive extends BasicArchive
{
    const TAR = 'tar';
    const TAR_GZIP = 'tgz';
    const TAR_BZIP = 'tbz2';
    const TAR_LZMA = 'txz';
    const TAR_LZW = 'tar.z';

    /** @var string */
    protected $path;

    /** @var Archive_Tar|PharData */
    protected $tar;

    /** @var bool */
    static protected $enabledPearTar;

    /** @var bool */
    static protected $enabledPharData;

    /** @var int */
    protected $numberOfFiles;

    /** @var array */
    protected $files;

    /** @var int */
    protected $uncompressedFilesSize;

    /** @var int */
    protected $compressedFilesSize;

    /** @var int */
    protected $archiveSize;

    /** @var float */
    protected $compressionRatio;

    const PHAR_FLAGS = FilesystemIterator::UNIX_PATHS;

    /**
     * @param $fileName
     * @return null|TarArchive
     * @throws Exception
     */
    public static function open($fileName)
    {
        self::checkRequirements();

        if (!file_exists($fileName) || !is_readable($fileName))
            throw new Exception('Count not open file: '.$fileName);

        $type = self::detectArchiveType($fileName);

        if (!self::canOpenType($type)) {
            return null;
        }

        return new self($fileName, $type);
    }

    /**
     * Checks whether archive can be opened with current system configuration
     * @return boolean
     */
    public static function canOpenArchive($fileName)
    {
        self::checkRequirements();

        $type = self::detectArchiveType($fileName);
        if ($type !== false) {
            return self::canOpenType($type);
        }

        return false;
    }

    /**
     * Detect archive type by its filename or content.
     * @return string|boolean One of TarArchive type constants OR false if type is not detected
     */
    public static function detectArchiveType($fileName, $contentCheck = true)
    {
        // by file name
        if (preg_match('~\.(?<ext>tar|tgz|tbz2|txz|tar\.(gz|bz2|xz|z))$~', strtolower($fileName), $match)) {
            switch ($match['ext']) {
                case 'tar':
                    return self::TAR;
                case 'tgz':
                case 'tar.gz':
                    return self::TAR_GZIP;
                case 'tbz2':
                case 'tar.bz2':
                    return self::TAR_BZIP;
                case 'txz':
                case 'tar.xz':
                    return self::TAR_LZMA;
                case 'tar.z':
                    return self::TAR_LZW;
            }
        }

        // by content
        if ($contentCheck) {
            $mime_type = mime_content_type($fileName);
            switch ($mime_type) {
                case 'application/x-tar':
                    return self::TAR;
                case 'application/x-gtar':
                    return self::TAR_GZIP;
            }
        }

        return false;
    }

    /**
     * Checks whether specific archive type can be opened with current system configuration
     * @return boolean
     */
    public static function canOpenType($type)
    {
        self::checkRequirements();
        switch ($type) {
            case self::TAR:
            case self::TAR_GZIP:
            case self::TAR_BZIP:
                return self::$enabledPearTar || self::$enabledPharData;

            case self::TAR_LZMA:
            case self::TAR_LZW:
                return self::$enabledPearTar;
        }

        return false;
    }

    /**
     * TarArchive constructor.
     * @param $fileName
     * @param null $type
     * @throws Exception
     */
    public function __construct($fileName, $type = null)
    {
        self::checkRequirements();

        $this->path = realpath($fileName);

        if (!self::$enabledPharData && !self::$enabledPearTar)
            throw new Exception('Archive_Tar nor PharData not available');

        $this->openArchive($type);
        $this->scanArchive();
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->tar = null;
    }

    /**
     * @return array
     */
    public function getFileNames()
    {
        return $this->files;
    }



    /**
     * @param $fileName
     * @return bool|ArchiveEntry
     */
    public function getFileData($fileName)
    {
        if (!in_array($fileName, $this->files, true))
            return false;

        if ($this->tar instanceof Archive_Tar) {
            $index = array_search($fileName, $this->files, true);

            $Content = $this->tar->listContent();
            $data = $Content[$index];
            unset($Content);

            return new ArchiveEntry($fileName, $data['size'] / $this->tarCompressionRatio,
                $data['size'], $data['mtime'], in_array(strtolower(pathinfo($this->tar->path,
                    PATHINFO_EXTENSION)), array('gz', 'bz2', 'xz', 'Z')));
        } else {
            /** @var \PharFileInfo $entry_info */
            $entry_info = $this->tar[$fileName];
            return new ArchiveEntry($fileName, $entry_info->getSize(), filesize($entry_info->getPathname()),
                0, $entry_info->isCompressed());
        }
    }

    /**
     * @param $fileName
     * @return string
     */
    public function getFileContent($fileName)
    {
        if (!in_array($fileName, $this->files, true))
            return false;
        if ($this->tar instanceof Archive_Tar)
            return $this->tar->extractInString($fileName);
        else
            return $this->tar[$fileName]->getContent();
    }

    /**
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
     * @param                   $outputFolder
     * @param string|array|null $files
     * @param bool              $expandFilesList
     *
     * @return bool|int
     */
    public function extractFiles($outputFolder, $files = null, $expandFilesList = false)
    {
        if ($expandFilesList && $files !== null)
            $files = self::expandFileList($this->files, $files);

        $list = array();
        if ($files === null) {
            $list = array_values($this->files);
        } else {
            foreach ($this->files as $fname) {
                if (strpos($fname, $files) === 0) {
                    $list[] = $fname;
                }
            }
        }

        if ($this->tar instanceof Archive_Tar) {
            $result = $this->tar->extractList($list, $outputFolder);
        } else {
            $result = $this->tar->extractTo($outputFolder, $list, true);
        }

        if ($result === true) {
            return count($list);
        } else {
            return false;
        }
    }

    /**
     * @param string|array $fileOrFiles
     * @param bool         $expandFilesList
     *
     * @return bool|int
     */
    public function deleteFiles($fileOrFiles, $expandFilesList = false)
    {
        if ($this->tar instanceof Archive_Tar)
            return false;

        if ($expandFilesList && $fileOrFiles !== null)
            $fileOrFiles = self::expandFileList($this->files, $fileOrFiles);

        $files = is_string($fileOrFiles) ? array($fileOrFiles) : $fileOrFiles;
        $deleted = 0;

        foreach ($files as $i => $file) {
            if (!in_array($file, $this->files, true))
                continue;

            if ($this->tar->delete($file))
                $deleted++;
        }

        $this->tar = null;
        $this->openArchive();
        $this->scanArchive();

        return $deleted;
    }

    /**
     * @param $fileOrFiles
     *
     * @return int|bool
     * @throws \Exception
     */
    public function addFiles($fileOrFiles)
    {
        $fileOrFiles = self::createFilesList($fileOrFiles);

        $added_files = 0;

        if ($this->tar instanceof Archive_Tar) {
            foreach ($fileOrFiles as $localname => $filename) {
                $remove_dir = dirname($filename);
                $add_dir = dirname($localname);
                if (is_null($filename)) {
                    if ($this->tar->addString($localname, "") === false)
                        return false;
                } else {
                    if ($this->tar->addModify($filename, $add_dir, $remove_dir) === false)
                        return false;
                    $added_files++;
                }
            }
        } else {
            try {
                foreach ($fileOrFiles as $localname => $filename) {
                    if (is_null($filename)) {
                        $this->tar->addEmptyDir($localname);
                    } else {
                        $this->tar->addFile($filename, $localname);
                        $added_files++;
                    }
                }
            } catch (Exception $e) {
                return false;
            }
            $this->tar = null;
            // reopen to refresh files list properly
            $this->openArchive();
        }


        $this->scanArchive();

        return $added_files;
    }

    /**
     * @return int
     */
    public function countFiles()
    {
        return $this->numberOfFiles;
    }

    /**
     * @return int
     */
    public function getArchiveSize()
    {
        return filesize($this->path);
    }

    /**
     * @return string
     */
    public function getArchiveType()
    {
        return 'tar';
    }

    /**
     * @return int
     */
    public function countCompressedFilesSize()
    {
        return $this->compressedFilesSize;
    }

    /**
     * @return int
     */
    public function countUncompressedFilesSize()
    {
        return $this->uncompressedFilesSize;
    }

    /**
     * @param string|string[]|array[] $fileOrFiles
     * @param $archiveName
     * @param bool $emulate
     * @return array|bool
     * @throws Exception
     */
    public static function archiveFiles($fileOrFiles, $archiveName, $emulate = false)
    {
        self::checkRequirements();

        if (file_exists($archiveName))
            throw new Exception('Archive '.$archiveName.' already exists!');

        $fileOrFiles = self::createFilesList($fileOrFiles);

        // fake creation: return archive data
        if ($emulate) {
            $totalSize = 0;
            foreach ($fileOrFiles as $fn) $totalSize += filesize($fn);

            return array(
                'totalSize' => $totalSize,
                'numberOfFiles' => count($fileOrFiles),
                'files' => $fileOrFiles,
                'type' => 'tar',
            );
        }

        if (self::$enabledPearTar) {
            $compression = null;
            switch (strtolower(pathinfo($archiveName, PATHINFO_EXTENSION))) {
                case 'gz':
                case 'tgz':
                    $compression = 'gz';
                    break;
                case 'bz2':
                case 'tbz2':
                    $compression = 'bz2';
                    break;
                case 'xz':
                    $compression = 'lzma2';
                    break;
                case 'z':
                    $tar_aname = 'compress.lzw://' . $archiveName;
                    break;
            }

            if (isset($tar_aname))
                $tar = new Archive_Tar($tar_aname, $compression);
            else
                $tar = new Archive_Tar($archiveName, $compression);

            foreach ($fileOrFiles as $localname => $filename) {
                $remove_dir = dirname($filename);
                $add_dir = dirname($localname);

                if (is_null($filename)) {
                    if ($tar->addString($localname, "") === false)
                        return false;
                } else {
                    if ($tar->addModify($filename, $add_dir, $remove_dir)
                        === false) return false;
                }
            }
            $tar = null;
        } else if (self::$enabledPharData) {
            if (preg_match('~^(.+)\.(tar\.(gz|bz2))$~i', $archiveName, $match)) {
                $ext = $match[2];
                $basename = $match[1];
            } else {
                $ext = pathinfo($archiveName, PATHINFO_EXTENSION);
                $basename = dirname($archiveName).'/'.basename($archiveName, '.'.$ext);
            }
            $tar = new PharData($basename.'.tar', 0, null, Phar::TAR);

            try {
                foreach ($fileOrFiles as $localname => $filename) {
                    if (is_null($filename)) {
                        if (!in_array($localname, ['/', ''], true)) {
                            if ($tar->addEmptyDir($localname) === false) {
                                return false;
                            }
                        }
                    } else {
                        if ($tar->addFile($filename, $localname) === false) {
                            return false;
                        }
                    }
                }
            } catch (Exception $e) {
                return false;
            }

            switch (strtolower(pathinfo($archiveName, PATHINFO_EXTENSION))) {
                case 'gz':
                case 'tgz':
                    $tar->compress(Phar::GZ, $ext);
                    break;
                case 'bz2':
                case 'tbz2':
                    $tar->compress(Phar::BZ2, $ext);
                    break;
            }
            $tar = null;
        } else {
            throw new Exception('Archive_Tar nor PharData not available');
        }

        return count($fileOrFiles);
    }

    /**
     * Rescans array
     */
    protected function scanArchive()
    {
        $this->files = [];
        $this->compressedFilesSize =
        $this->uncompressedFilesSize = 0;

        if ($this->tar instanceof Archive_Tar) {
            $Content = $this->tar->listContent();
            $this->numberOfFiles = count($Content);
            foreach ($Content as $i => $file) {
                // BUG workaround: http://pear.php.net/bugs/bug.php?id=20275
                if ($file['filename'] === 'pax_global_header') {
                    $this->numberOfFiles--;
                    continue;
                }
                $this->files[$i] = $file['filename'];
                $this->uncompressedFilesSize += $file['size'];
            }

            $this->compressedFilesSize = $this->archiveSize;
            $this->compressionRatio = $this->uncompressedFilesSize != 0 ? ceil($this->archiveSize
                / $this->uncompressedFilesSize) : 1;
        } else {
            $this->numberOfFiles = $this->tar->count();
            $stream_path_length = strlen('phar://'.$this->path.'/');
            foreach (new RecursiveIteratorIterator($this->tar) as $i => $file) {
                $this->files[$i] = substr($file->getPathname(), $stream_path_length);
                $this->compressedFilesSize += $file->getCompressedSize();
                $this->uncompressedFilesSize += filesize($file->getPathname());
            }
        }
    }

    protected static function checkRequirements()
    {
        if (self::$enabledPharData === null || self::$enabledPearTar === null) {
            self::$enabledPearTar = class_exists('\Archive_Tar');
            self::$enabledPharData = class_exists('\PharData');
        }
    }

    /**
     * Checks that file exists in archive
     * @param string $fileName Name of file
     * @return boolean
     */
    public function isFileExists($fileName)
    {
        return in_array($fileName, $this->files, true);
    }

    /**
     * Returns a resource for reading file from archive
     * @param string $fileName
     * @return resource|false
     */
    public function getFileResource($fileName)
    {
        if (!in_array($fileName, $this->files, true))
            return false;

        $resource = fopen('php://temp', 'r+');
        if ($this->tar instanceof Archive_Tar)
            fwrite($resource, $this->tar->extractInString($fileName));
        else
            fwrite($resource, $this->tar[$fileName]->getContent());

        rewind($resource);
        return $resource;
    }

    /**
     * @param $type
     *
     * @throws \Exception
     */
    private function openArchive($type = null)
    {
        if ($type === null) {
            $type = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
        }

        switch ($type) {
            case 'gz':
            case 'tgz':
                if (self::$enabledPharData) {
                    $this->tar = new PharData($this->path, self::PHAR_FLAGS);
                } else {
                    $this->tar = new Archive_Tar($this->path, 'gz');
                }
                break;
            case 'bz2':
            case 'tbz2':
                if (self::$enabledPharData) {
                    $this->tar = new PharData($this->path, self::PHAR_FLAGS);
                } else {
                    $this->tar = new Archive_Tar($this->path, 'bz2');
                }
                break;
            case 'xz':
                if (!self::$enabledPharData) {
                    throw new Exception('Archive_Tar not available');
                }
                $this->tar = new Archive_Tar($this->path, 'lzma2');
                break;
            case 'z':
                if (!self::$enabledPharData) {
                    throw new Exception('Archive_Tar not available');
                }
                $this->tar = new Archive_Tar('compress.lzw://' . $this->path);
                break;
            default:
                if (self::$enabledPharData) {
                    $this->tar = new PharData($this->path, self::PHAR_FLAGS, null, Phar::TAR);
                } else {
                    $this->tar = new Archive_Tar($this->path);
                }
                break;
        }
    }
}
