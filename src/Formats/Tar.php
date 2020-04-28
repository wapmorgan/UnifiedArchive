<?php
namespace wapmorgan\UnifiedArchive\Formats;

use Archive_Tar;
use Exception;
use FilesystemIterator;
use Phar;
use PharData;
use RecursiveIteratorIterator;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveCreationException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveModificationException;
use wapmorgan\UnifiedArchive\Exceptions\NonExistentArchiveFileException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedArchiveException;
use wapmorgan\UnifiedArchive\LzwStreamWrapper;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;

/**
 * Tar format handler
 * @package wapmorgan\UnifiedArchive\Formats
 */
class Tar extends BasicFormat
{
    const TAR = 'tar';
    const TAR_GZIP = 'tgz';
    const TAR_BZIP = 'tbz2';
    const TAR_LZMA = 'txz';
    const TAR_LZW = 'tar.z';

    /** @var bool */
    static protected $enabledPearTar;

    /** @var bool */
    static protected $enabledPharData;
    /**
     * Checks system configuration for available Tar-manipulation libraries
     */
    protected static function checkRequirements()
    {
        if (self::$enabledPharData === null || self::$enabledPearTar === null) {
            self::$enabledPearTar = class_exists('\Archive_Tar');
            self::$enabledPharData = class_exists('\PharData');
        }
    }

    /**
     * Checks whether archive can be opened with current system configuration
     * @param $archiveFileName
     * @return boolean
     */
//    public static function canOpenArchive($archiveFileName)
//    {
//        self::checkRequirements();
//
//        $type = self::detectArchiveType($archiveFileName);
//        if ($type !== false) {
//            return self::canOpenType($type);
//        }
//
//        return false;
//    }

    /**
     * Detect archive type by its filename or content.
     * @param $archiveFileName
     * @param bool $contentCheck
     * @return string|boolean One of TarArchive type constants OR false if type is not detected
     */
    public static function detectArchiveType($archiveFileName, $contentCheck = true)
    {
        // by file name
        if (preg_match('~\.(?<ext>tar|tgz|tbz2|txz|tar\.(gz|bz2|xz|z))$~', strtolower($archiveFileName), $match)) {
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
            $mime_type = mime_content_type($archiveFileName);
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
     * @param $type
     * @return boolean
     */
//    public static function canOpenType($type)
//    {
//        self::checkRequirements();
//        switch ($type) {
//            case self::TAR:
//                return self::$enabledPearTar || self::$enabledPharData;
//
//            case self::TAR_GZIP:
//                return (self::$enabledPearTar || self::$enabledPharData) && extension_loaded('zlib');
//
//            case self::TAR_BZIP:
//                return (self::$enabledPearTar || self::$enabledPharData) && extension_loaded('bz2');
//
//
//            case self::TAR_LZMA:
//                return self::$enabledPearTar && extension_loaded('lzma2');
//
//            case self::TAR_LZW:
//                return self::$enabledPearTar && LzwStreamWrapper::isBinaryAvailable();
//        }
//
//        return false;
//    }

    /**
     * @param array $files
     * @param string $archiveFileName
     * @return false|int
     * @throws Exception
     */
    public static function createArchive(array $files, $archiveFileName)
    {
        static::checkRequirements();

        if (static::$enabledPharData)
            return static::createArchiveForPhar($files, $archiveFileName);

        if (static::$enabledPearTar)
            return static::createArchiveForPear($files, $archiveFileName);

        throw new UnsupportedOperationException('Archive_Tar nor PharData not available');
    }

    /**
     * Creates an archive via Pear library
     * @param array $files
     * @param $archiveFileName
     * @return int
     * @throws ArchiveCreationException
     */
    protected static function createArchiveForPear(array $files, $archiveFileName)
    {
        $compression = null;
        switch (strtolower(pathinfo($archiveFileName, PATHINFO_EXTENSION))) {
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
                $tar_aname = 'compress.lzw://' . $archiveFileName;
                break;
        }

        if (isset($tar_aname))
            $tar = new Archive_Tar($tar_aname, $compression);
        else
            $tar = new Archive_Tar($archiveFileName, $compression);

        foreach ($files  as $localName => $filename) {
            $remove_dir = dirname($filename);
            $add_dir = dirname($localName);

            if (is_null($filename)) {
                if ($tar->addString($localName, '') === false)
                    throw new ArchiveCreationException('Error when adding directory '.$localName.' to archive');
            } else {
                if ($tar->addModify($filename, $add_dir, $remove_dir) === false)
                    throw new ArchiveCreationException('Error when adding file '.$filename.' to archive');
            }
        }
        $tar = null;

        return count($files);
    }

    /**
     * Creates an archive via Phar library
     * @param array $files
     * @param $archiveFileName
     * @return bool
     * @throws ArchiveCreationException
     */
    protected static function createArchiveForPhar(array $files, $archiveFileName)
    {
        if (preg_match('~^(.+)\.(tar\.(gz|bz2))$~i', $archiveFileName, $match)) {
            $ext = $match[2];
            $basename = $match[1];
        } else {
            $ext = pathinfo($archiveFileName, PATHINFO_EXTENSION);
            $basename = dirname($archiveFileName).'/'.basename($archiveFileName, '.'.$ext);
        }
        $tar = new PharData($basename.'.tar', 0, null, Phar::TAR);

        try {
            foreach ($files as $localName => $filename) {
                if (is_null($filename)) {
                    if (!in_array($localName, ['/', ''], true)) {
                        if ($tar->addEmptyDir($localName) === false) {
                            throw new ArchiveCreationException('Error when adding directory '.$localName.' to archive');
                        }
                    }
                } else {
                    if ($tar->addFile($filename, $localName) === false) {
                        throw new ArchiveCreationException('Error when adding file '.$localName.' to archive');
                    }
                }
            }
        } catch (Exception $e) {
            throw new ArchiveCreationException('Error when creating archive: '.$e->getMessage(), $e->getCode(), $e);
        }

        switch (strtolower(pathinfo($archiveFileName, PATHINFO_EXTENSION))) {
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

        return count($files);
    }

    /** @var string Full path to archive */
    protected $archiveFileName;

    /** @var string Full path to archive */
    protected $archiveType;

    /** @var Archive_Tar|PharData */
    protected $tar;

    /** @var float Overall compression ratio of Tar archive when Archive_Tar is used */
    protected $pearCompressionRatio;

    /** @var array<string, integer> List of files and their index in listContent() result */
    protected $pearFilesIndex;

    /** @var int Flags for iterator */
    const PHAR_FLAGS = FilesystemIterator::UNIX_PATHS;

    /**
     * Tar format constructor.
     *
     * @param string $archiveFileName
     * @throws Exception
     */
    public function __construct($archiveFileName)
    {
        static::checkRequirements();

        $this->archiveFileName = realpath($archiveFileName);
        $this->archiveType = static::detectArchiveType($this->archiveFileName);

        if ($this->archiveType === false)
            throw new UnsupportedArchiveException('Could not detect type for archive '.$this->archiveFileName);

        $this->open($this->archiveType);
    }

    /**
     * Tar destructor
     */
    public function __destruct()
    {
        $this->tar = null;
    }

    /**
     * @param string $archiveType
     * @throws UnsupportedArchiveException
     */
    protected function open($archiveType)
    {
        switch ($archiveType) {
            case self::TAR_GZIP:
                if (self::$enabledPharData) {
                    $this->tar = new PharData($this->archiveFileName, self::PHAR_FLAGS);
                } else {
                    $this->tar = new Archive_Tar($this->archiveFileName, 'gz');
                }
                break;

            case self::TAR_BZIP:
                if (self::$enabledPharData) {
                    $this->tar = new PharData($this->archiveFileName, self::PHAR_FLAGS);
                } else {
                    $this->tar = new Archive_Tar($this->archiveFileName, 'bz2');
                }
                break;

            case self::TAR_LZMA:
                if (!self::$enabledPearTar) {
                    throw new UnsupportedArchiveException('Archive_Tar not available');
                }
                $this->tar = new Archive_Tar($this->archiveFileName, 'lzma2');
                break;

            case self::TAR_LZW:
                if (!self::$enabledPearTar) {
                    throw new UnsupportedArchiveException('Archive_Tar not available');
                }

                LzwStreamWrapper::registerWrapper();
                $this->tar = new Archive_Tar('compress.lzw://' . $this->archiveFileName);
                break;

            default:
                if (self::$enabledPharData) {
                    $this->tar = new PharData($this->archiveFileName, self::PHAR_FLAGS);
                } else {
                    $this->tar = new Archive_Tar($this->archiveFileName);
                }
                break;
        }
    }

    /**
     * @return ArchiveInformation
     */
    public function getArchiveInformation()
    {
        $information = new ArchiveInformation();
        if ($this->tar instanceof Archive_Tar) {
            $this->pearFilesIndex = [];

            foreach ($this->tar->listContent() as $i => $file) {
                // BUG workaround: http://pear.php.net/bugs/bug.php?id=20275
                if ($file['filename'] === 'pax_global_header') {
                    continue;
                }
                $information->files[] = $file['filename'];
                $information->uncompressedFilesSize += $file['size'];
                $this->pearFilesIndex[$file['filename']] = $i;
            }

            $information->uncompressedFilesSize = filesize($this->archiveFileName);
            $this->pearCompressionRatio = $information->uncompressedFilesSize != 0
                ? ceil($information->compressedFilesSize / $information->uncompressedFilesSize)
                : 1;
        } else {
            $stream_path_length = strlen('phar://'.$this->archiveFileName.'/');
            foreach (new RecursiveIteratorIterator($this->tar) as $i => $file) {
                $information->files[] = substr($file->getPathname(), $stream_path_length);
                $information->compressedFilesSize += $file->getCompressedSize();
                $information->uncompressedFilesSize += filesize($file->getPathname());
            }
        }

        return $information;
    }

    /**
     * @return array
     */
    public function getFileNames()
    {
        return $this->tar instanceof Archive_Tar
            ? $this->getFileNamesForPear()
            : $this->getFileNamesForPhar();
    }

    /**
     * @param string $fileName
     * @return bool
     */
    public function isFileExists($fileName)
    {
        if ($this->tar instanceof Archive_Tar)
            return isset($this->pearFilesIndex[$fileName]);

        try {
            $this->tar->offsetGet($fileName);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param string $fileName
     * @return ArchiveEntry
     * @throws NonExistentArchiveFileException
     */
    public function getFileData($fileName)
    {
        if ($this->tar instanceof Archive_Tar) {
            if (!isset($this->pearFilesIndex[$fileName]))
                throw new NonExistentArchiveFileException('File '.$fileName.' is not found in archive files list');

            $index = $this->pearFilesIndex[$fileName];

            $files_list = $this->tar->listContent();
            if (!isset($files_list[$index]))
                throw new NonExistentArchiveFileException('File '.$fileName.' is not found in Tar archive');

            $data = $files_list[$index];
            unset($files_list);

            return new ArchiveEntry($fileName, $data['size'] / $this->pearCompressionRatio,
                $data['size'], $data['mtime'], in_array(strtolower(pathinfo($this->archiveFileName,
                    PATHINFO_EXTENSION)), array('gz', 'bz2', 'xz', 'Z')));
        }

        /** @var \PharFileInfo $entry_info */
        $entry_info = $this->tar->offsetGet($fileName);
        return new ArchiveEntry($fileName, $entry_info->getSize(), filesize($entry_info->getPathname()),
            0, $entry_info->isCompressed());
    }

    /**
     * @param string $fileName
     * @return string
     * @throws NonExistentArchiveFileException
     */
    public function getFileContent($fileName)
    {
        if ($this->tar instanceof Archive_Tar) {
            if (!isset($this->pearFilesIndex[$fileName]))
                throw new NonExistentArchiveFileException('File '.$fileName.' is not found in archive files list');

            return $this->tar->extractInString($fileName);
        }

        return $this->tar->offsetGet($fileName)->getContent();
    }

    /**
     * @param string $fileName
     * @return resource
     * @throws NonExistentArchiveFileException
     */
    public function getFileResource($fileName)
    {
        $resource = fopen('php://temp', 'r+');
        if ($this->tar instanceof Archive_Tar) {
            if (!isset($this->pearFilesIndex[$fileName]))
                throw new NonExistentArchiveFileException('File '.$fileName.' is not found in archive files list');

            fwrite($resource, $this->tar->extractInString($fileName));
        } else
            fwrite($resource, $this->tar->offsetGet($fileName)->getContent());

        rewind($resource);
        return $resource;
    }

    /**
     * @param string $outputFolder
     * @param array $files
     * @return int
     * @throws ArchiveExtractionException
     */
    public function extractFiles($outputFolder, array $files)
    {
        if ($this->tar instanceof Archive_Tar) {
            $result = $this->tar->extractList($files, $outputFolder);
        } else {
            $result = $this->tar->extractTo($outputFolder, $files, true);
        }

        if ($result === false) {
            throw new ArchiveExtractionException('Error when extracting from '.$this->archiveFileName);
        }

        return count($files);
    }

    /**
     * @param string $outputFolder
     * @return false|int
     * @throws ArchiveExtractionException
     */
    public function extractArchive($outputFolder)
    {
        if ($this->tar instanceof Archive_Tar) {
            $result = $this->tar->extract($outputFolder);
        } else {
            $result = $this->tar->extractTo($outputFolder, null, true);
        }

        if ($result === false) {
            throw new ArchiveExtractionException('Error when extracting from '.$this->archiveFileName);
        }

        return 1;
    }

    /**
     * @param array $files
     * @return int
     * @throws UnsupportedOperationException
     */
    public function deleteFiles(array $files)
    {
        if ($this->tar instanceof Archive_Tar)
            throw new UnsupportedOperationException();

        $deleted = 0;

        foreach ($files as $i => $file) {
            if ($this->tar->delete($file))
                $deleted++;
        }

        $this->tar = null;
        $this->open($this->archiveType);

        return $deleted;
    }

    /**
     * @param array $files
     * @return false|int
     * @throws ArchiveModificationException
     */
    public function addFiles(array $files)
    {
        $added = 0;

        if ($this->tar instanceof Archive_Tar) {
            foreach ($files as $localName => $filename) {
                $remove_dir = dirname($filename);
                $add_dir = dirname($localName);
                if (is_null($filename)) {
                    if ($this->tar->addString($localName, "") === false) {
                        throw new ArchiveModificationException('Could not add directory "'.$filename.'": '.$this->tar->error_object->message, $this->tar->error_object->code);
                    }
                } else {
                    if ($this->tar->addModify($filename, $add_dir, $remove_dir) === false) {
                        throw new ArchiveModificationException('Could not add file "'.$filename.'": '.$this->tar->error_object->message, $this->tar->error_object->code);
                    }
                    $added++;
                }
            }
        } else {
            try {
                foreach ($files as $localName => $filename) {
                    if (is_null($filename)) {
                        $this->tar->addEmptyDir($localName);
                    } else {
                        $this->tar->addFile($filename, $localName);
                        $added++;
                    }
                }
            } catch (Exception $e) {
                throw new ArchiveModificationException('Could not add file "'.$filename.'": '.$e->getMessage(), $e->getCode());
            }
            $this->tar = null;
            // reopen to refresh files list properly
            $this->open($this->archiveType);
        }

        return $added;
    }

    /**
     * @return array
     */
    protected function getFileNamesForPear()
    {
        $files = [];

        $Content = $this->tar->listContent();
        foreach ($Content as $i => $file) {
            // BUG workaround: http://pear.php.net/bugs/bug.php?id=20275
            if ($file['filename'] === 'pax_global_header') {
                continue;
            }
            $files[] = $file['filename'];
        }

        return $files;
    }

    /**
     * @return array
     */
    protected function getFileNamesForPhar()
    {
        $files = [];

        $stream_path_length = strlen('phar://'.$this->archiveFileName.'/');
        foreach (new RecursiveIteratorIterator($this->tar) as $i => $file) {
            $files[] = substr($file->getPathname(), $stream_path_length);
        }

        return $files;
    }

    /**
     * @return bool
     */
    public static function canCreateArchive()
    {
        return true;
    }

    /**
     * @return bool
     */
    public static function canAddFiles()
    {
        return true;
    }

    /**
     * @return bool
     */
    public static function canDeleteFiles()
    {
        static::checkRequirements();
        return self::$enabledPharData;
    }
}
