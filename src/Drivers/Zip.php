<?php
namespace wapmorgan\UnifiedArchive\Drivers;

use Exception;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Drivers\BasicDriver;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveCreationException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveModificationException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\Formats;
use wapmorgan\UnifiedArchive\PclzipZipInterface;
use ZipArchive;

/**
 * Class Zip
 *
 * @package wapmorgan\UnifiedArchive\Formats
 * @requires ext-zip
 */
class Zip extends BasicDriver
{
    /** @var ZipArchive */
    protected $zip;

    /**
     * @return array
     */
    public static function getSupportedFormats()
    {
        return [
            Formats::ZIP
        ];
    }

    /**
     * @param $format
     * @return bool
     */
    public static function checkFormatSupport($format)
    {
        switch ($format) {
            case Formats::ZIP:
                return extension_loaded('zip');
        }
    }

    public static function getDescription()
    {
        return 'adapter for ext-zip'.(extension_loaded('zip') && defined('\ZipArchive::LIBZIP_VERSION') ? ' ('. ZipArchive::LIBZIP_VERSION.')' : null);
    }

    public static function getInstallationInstruction()
    {
        return !extension_loaded('zip')
            ? 'install `zip` extension'
            : null;
    }

    /**
     * @inheritDoc
     */
    public function __construct($archiveFileName, $format, $password = null)
    {
        $this->open($archiveFileName);
        if ($password !== null)
            $this->zip->setPassword($password);
    }

    /**
     * @param string $archiveFileName
     * @throws UnsupportedOperationException
     */
    protected function open($archiveFileName)
    {
        $this->zip = new ZipArchive();
        $open_result = $this->zip->open($archiveFileName);
        if ($open_result !== true) {
            throw new UnsupportedOperationException('Could not open Zip archive: '.$open_result);
        }
    }

    /**
     * Zip format destructor
     */
    public function __destruct()
    {
        unset($this->zip);
    }

    /**
     * @return ArchiveInformation
     */
    public function getArchiveInformation()
    {
        $information = new ArchiveInformation();
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $file = $this->zip->statIndex($i);
            // skip directories
            if (in_array(substr($file['name'], -1), ['/', '\\'], true))
                continue;
            $information->files[$i] = $file['name'];
            $information->compressedFilesSize += $file['comp_size'];
            $information->uncompressedFilesSize += $file['size'];
        }
        return $information;
    }

    /**
     * @return false|string|null
     */
    public function getComment()
    {
        return $this->zip->getArchiveComment();
    }

    /**
     * @param string|null $comment
     * @return bool|null
     */
    public function setComment($comment)
    {
        return $this->zip->setArchiveComment($comment);
    }

    /**
     * @return array
     */
    public function getFileNames()
    {
        $files = [];
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $file_name = $this->zip->getNameIndex($i);
            // skip directories
            if (in_array(substr($file_name, -1), ['/', '\\'], true))
                continue;
            $files[] = $file_name;
        }
        return $files;
    }

    /**
     * @param string $fileName
     *
     * @return bool
     */
    public function isFileExists($fileName)
    {
        return $this->zip->statName($fileName) !== false;
    }

    /**
     * @param string $fileName
     *
     * @return ArchiveEntry
     */
    public function getFileData($fileName)
    {
        $stat = $this->zip->statName($fileName);
        return new ArchiveEntry($fileName, $stat['comp_size'], $stat['size'], $stat['mtime'],
            $stat['comp_method'] != 0, $this->zip->getCommentName($fileName));
    }

    /**
     * @param string $fileName
     *
     * @return string|false
     * @throws \Exception
     */
    public function getFileContent($fileName)
    {
        $result = $this->zip->getFromName($fileName);
        if ($result === false)
            throw new Exception('Could not get file information: '.$result.'. May use password?');
        return $result;
    }

    /**
     * @param $fileName
     * @return false|resource
     */
    public function getFileStream($fileName)
    {
        return $this->zip->getStream($fileName);
    }

    /**
     * @param string $outputFolder
     * @param array $files
     * @return int Number of extracted files
     * @throws ArchiveExtractionException
     */
    public function extractFiles($outputFolder, array $files)
    {
        if ($this->zip->extractTo($outputFolder, $files) === false)
            throw new ArchiveExtractionException($this->zip->getStatusString(), $this->zip->status);

        return count($files);
    }

    /**
     * @param string $outputFolder
     * @return int Number of extracted files
     *@throws ArchiveExtractionException
     */
    public function extractArchive($outputFolder)
    {
        if ($this->zip->extractTo($outputFolder) === false)
            throw new ArchiveExtractionException($this->zip->getStatusString(), $this->zip->status);

        return $this->zip->numFiles;
    }

    /**
     * @param array $files
     * @return int
     * @throws ArchiveModificationException
     * @throws UnsupportedOperationException
     */
    public function deleteFiles(array $files)
    {
        $count = 0;
        foreach ($files as $file) {
            if ($this->zip->deleteName($file) === false)
                throw new ArchiveModificationException($this->zip->getStatusString(), $this->zip->status);
            $count++;
        }

        // reopen archive to save changes
        $archive_filename = $this->zip->filename;
        $this->zip->close();
        $this->open($archive_filename);

        return $count;
    }

    /**
     * @param array $files
     * @return int
     * @throws ArchiveModificationException
     * @throws UnsupportedOperationException
     */
    public function addFiles(array $files)
    {
        $added_files = 0;
        foreach ($files as $localName => $fileName) {
            if (is_null($fileName)) {
                if ($this->zip->addEmptyDir($localName) === false)
                    throw new ArchiveModificationException($this->zip->getStatusString(), $this->zip->status);
            } else {
                if ($this->zip->addFile($fileName, $localName) === false)
                    throw new ArchiveModificationException($this->zip->getStatusString(), $this->zip->status);
                $added_files++;
            }
        }

        // reopen archive to save changes
        $archive_filename = $this->zip->filename;
        $this->zip->close();
        $this->open($archive_filename);

        return $added_files;
    }

    /**
     * @param string $inArchiveName
     * @param string $content
     * @return bool
     */
    public function addFileFromString($inArchiveName, $content)
    {
        return $this->zip->addFromString($inArchiveName, $content);
    }

    /**
     * @param array $files
     * @param string $archiveFileName
     * @param int $archiveFormat
     * @param int $compressionLevel
     * @param null $password
     * @return int
     * @throws ArchiveCreationException
     * @throws UnsupportedOperationException
     */
    public static function createArchive(array $files, $archiveFileName, $archiveFormat, $compressionLevel = self::COMPRESSION_AVERAGE, $password = null)
    {
        static $compressionLevelMap = [
            self::COMPRESSION_NONE => ZipArchive::CM_STORE,
            self::COMPRESSION_WEAK => ZipArchive::CM_DEFLATE,
            self::COMPRESSION_AVERAGE => ZipArchive::CM_DEFLATE,
            self::COMPRESSION_STRONG => ZipArchive::CM_DEFLATE,
            self::COMPRESSION_MAXIMUM => ZipArchive::CM_DEFLATE64,
        ];

        $zip = new ZipArchive();
        $result = $zip->open($archiveFileName, ZipArchive::CREATE);

        if ($result !== true)
            throw new ArchiveCreationException('ZipArchive error: '.$result);

        $can_set_compression_level = method_exists($zip, 'setCompressionName');
        $can_encrypt = static::canEncrypt(Formats::ZIP);

        if ($password !== null && !$can_encrypt) {
            throw new ArchiveCreationException('Encryption is not supported on current platform');
        }

        foreach ($files as $localName => $fileName) {
            if ($fileName === null) {
                if ($zip->addEmptyDir($localName) === false)
                    throw new ArchiveCreationException('Could not archive directory "'.$localName.'": '.$zip->getStatusString(), $zip->status);
            } else {
                if ($zip->addFile($fileName, $localName) === false)
                    throw new ArchiveCreationException('Could not archive file "'.$fileName.'": '.$zip->getStatusString(), $zip->status);
                if ($can_set_compression_level) {
                    $zip->setCompressionName($localName, $compressionLevelMap[$compressionLevel]);
                }
                if ($password !== null && $can_encrypt) {
                    $zip->setEncryptionName($localName, ZipArchive::EM_AES_256, $password);
                }
            }
        }
        $zip->close();

        return count($files);
    }

    /**
     * @inheritDoc
     */
    public static function canCreateArchive($format)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public static function canAddFiles($format)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public static function canDeleteFiles($format)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public static function canEncrypt($format)
    {
        return method_exists('\ZipArchive', 'setEncryptionName');
    }

    /**
     * @inheritDoc
     */
    public static function canStream($format)
    {
        return true;
    }
}
