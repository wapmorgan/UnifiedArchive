<?php
namespace wapmorgan\UnifiedArchive\Drivers;

use Exception;
use FilesystemIterator;
use Phar;
use PharData;
use PharFileInfo;
use RecursiveIteratorIterator;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicDriver;
use wapmorgan\UnifiedArchive\Drivers\Basic\BasicExtensionDriver;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveCreationException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveModificationException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\Formats;

class TarByPhar extends BasicExtensionDriver
{
    const EXTENSION_NAME = 'phar';

    public static $disabled = false;

    /**
     * @var PharData
     */
    protected $tar;

    /**
     * @var float
     */
    protected $compressRatio;

    protected $pureFilesNumber;

    /**
     * @var int Flags for iterator
     */
    const PHAR_FLAGS = FilesystemIterator::UNIX_PATHS;

    /**
     * @inheritDoc
     */
    public static function getInstallationInstruction()
    {
        return 'install `phar` extension and optionally php-extensions (zlib, bz2)';
    }

    /**
     * @inheritDoc
     */
    public static function getDescription()
    {
        return 'adapter for ext-phar';
    }

    /**
     * @return array
     */
    public static function getSupportedFormats()
    {
        return [
            Formats::TAR,
            Formats::TAR_GZIP,
            Formats::TAR_BZIP,
            Formats::ZIP,
        ];
    }

    /**
     * @param $format
     * @return array
     */
    public static function checkFormatSupport($format)
    {
        if (static::$disabled || !static::isInstalled()) {
            return [];
        }

        $abilities = [
            BasicDriver::OPEN,
            BasicDriver::EXTRACT_CONTENT,
            BasicDriver::STREAM_CONTENT,
            BasicDriver::APPEND,
            BasicDriver::DELETE,
            BasicDriver::CREATE,
        ];

        switch ($format) {
            case Formats::TAR:
            case Formats::ZIP:
                return $abilities;

            case Formats::TAR_GZIP:
                return extension_loaded('zlib')
                    ? $abilities
                    : [];
            case Formats::TAR_BZIP:
                return extension_loaded('bz2')
                    ? $abilities
                    : [];
        }
    }

    /**
     * @inheritDoc
     */
    public function __construct($archiveFileName, $format, $password = null)
    {
        parent::__construct($archiveFileName, $format);
        $this->open();
    }

    /**
     *
     */
    protected function open()
    {
        $this->tar = new PharData($this->fileName, self::PHAR_FLAGS);
    }

    /**
     * @inheritDoc
     */
    public function getArchiveInformation()
    {
        $information = new ArchiveInformation();
        $stream_path_length = strlen('phar://'.$this->fileName.'/');
        $information->compressedFilesSize = filesize($this->fileName);
        /**
         * @var string $i
         * @var PharFileInfo $file
         */
        foreach (new RecursiveIteratorIterator($this->tar) as $i => $file) {
            $information->files[] = substr($file->getPathname(), $stream_path_length);
            $information->uncompressedFilesSize += $file->getSize();
        }
        $this->compressRatio = $information->compressedFilesSize > 0
            ? $information->uncompressedFilesSize / $information->compressedFilesSize
            : 0;
        $this->pureFilesNumber = count($information->files);
        return $information;
    }

    /**
     * @inheritDoc
     */
    public function getFileNames()
    {
        $files = [];

        $stream_path_length = strlen('phar://'.$this->fileName.'/');
        foreach (new RecursiveIteratorIterator($this->tar) as $i => $file) {
            $files[] = substr($file->getPathname(), $stream_path_length);
        }

        return $files;
    }

    /**
     * @inheritDoc
     */
    public function isFileExists($fileName)
    {
        try {
            $this->tar->offsetGet($fileName);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function getFileData($fileName)
    {
        /** @var \PharFileInfo $entry_info */
        $entry_info = $this->tar->offsetGet($fileName);
        return new ArchiveEntry(
            $fileName,
            (
//                $entry_info->getCompressedSize() > $entry_info->getSize()
                $this->compressRatio > 1
                    ? floor($entry_info->getSize() / $this->compressRatio)
                    : (
                            $entry_info->getCompressedSize() > 0
                                ? $entry_info->getCompressedSize()
                                : 0
                    )
            ), //$entry_info->getCompressedSize(),
            $entry_info->getSize(),
            $entry_info->getMTime(),
            $entry_info->isCompressed());
    }

    /**
     * @inheritDoc
     */
    public function getFileContent($fileName)
    {
        return $this->tar->offsetGet($fileName)->getContent();
    }

    /**
     * @inheritDoc
     */
    public function getFileStream($fileName)
    {
        return fopen('phar://'.$this->fileName . '/' . $fileName, 'rb');
    }

    /**
     * @inheritDoc
     */
    public function extractFiles($outputFolder, array $files)
    {
        $result = $this->tar->extractTo($outputFolder, $files, true);
        if ($result === false) {
            throw new ArchiveExtractionException('Error when extracting from '.$this->fileName);
        }
        return count($files);
    }

    /**
     * @inheritDoc
     */
    public function extractArchive($outputFolder)
    {
        $result = $this->tar->extractTo($outputFolder, null, true);
        if ($result === false) {
            throw new ArchiveExtractionException('Error when extracting from '.$this->fileName);
        }

        return $this->pureFilesNumber;
    }

    /**
     * @inheritDoc
     */
    public function deleteFiles(array $files)
    {
        $deleted = 0;

        foreach ($files as $i => $file) {
            if ($this->tar->delete($file))
                $deleted++;
        }

        $this->tar = null;
        $this->open();

        return $deleted;
    }

    /**
     * @inheritDoc
     */
    public function addFiles(array $files)
    {
        $added = 0;
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
        $this->open();
        return $added;
    }

    /**
     * @param array $files
     * @param string $archiveFileName
     * @param int $archiveFormat
     * @param int $compressionLevel
     * @param null $password
     * @param $fileProgressCallable
     * @return int
     * @throws ArchiveCreationException
     * @throws UnsupportedOperationException
     */
    public static function createArchive(
        array $files,
        $archiveFileName,
        $archiveFormat,
        $compressionLevel = self::COMPRESSION_AVERAGE,
        $password = null,
        $fileProgressCallable = null
    )
    {
        if ($password !== null) {
            throw new UnsupportedOperationException('Driver (' . __CLASS__ . ') could not encrypt an archive');
        }

        if ($fileProgressCallable !== null && !is_callable($fileProgressCallable)) {
            throw new ArchiveCreationException('File progress callable is not callable');
        }

        if (preg_match('~^(.+)\.(tar\.(gz|bz2))$~i', $archiveFileName, $match)) {
            $ext = $match[2];
            $basename = $match[1];
        } else {
            $ext = pathinfo($archiveFileName, PATHINFO_EXTENSION);
            $basename = dirname($archiveFileName).'/'.basename($archiveFileName, '.'.$ext);
        }

        $compression = null;
        switch ($ext) {
            case 'tar.gz':
            case 'tgz':
                $compression = Phar::GZ;
                break;
            case 'tar.bz2':
            case 'tbz2':
                $compression = Phar::BZ2;
                break;
        }

        $destination_file = $basename . '.' . ($archiveFormat === Formats::ZIP ? 'zip' : 'tar');
        // if compression used and there is .tar archive with that's name,
        // use temp file
        if ($compression !== null && file_exists($basename . '.' . ($archiveFormat === Formats::ZIP ? 'zip' : 'tar'))) {
            $temp_basename = tempnam(sys_get_temp_dir(), 'tar-archive');
            unlink($temp_basename);
            $destination_file = $temp_basename. '.' . ($archiveFormat === Formats::ZIP ? 'zip' : 'tar');
        }

        $tar = new PharData(
            $destination_file,
            0, null, $archiveFormat === Formats::ZIP ? Phar::ZIP : Phar::TAR
        );

        try {
            $current_file = 0;
            $total_files = count($files);

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
                if ($fileProgressCallable !== null) {
                    call_user_func_array($fileProgressCallable, [$current_file++, $total_files, $filename, $localName]);
                }
            }
        } catch (Exception $e) {
            throw new ArchiveCreationException('Error when creating archive: '.$e->getMessage(), $e->getCode(), $e);
        }

        switch ($compression) {
            case Phar::GZ:
                $tar->compress(Phar::GZ, $ext);
                break;
            case Phar::BZ2:
                $tar->compress(Phar::BZ2, $ext);
                break;
        }
        $tar = null;

        // if compression used and original .tar file exist, clean it
        if ($compression !== null && file_exists($destination_file)) {
            unlink($destination_file);
        }

        // it temp file was used, rename it to destination archive name
        if (isset($temp_basename)) {
            rename($temp_basename . '.' . $ext, $archiveFileName);
        }

        return count($files);
    }

    /**
     * @param string $inArchiveName
     * @param string $content
     * @return bool
     */
    public function addFileFromString($inArchiveName, $content)
    {
        $this->tar->addFromString($inArchiveName, $content);
        return true;
    }
}
