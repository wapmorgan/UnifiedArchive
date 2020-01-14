<?php
namespace wapmorgan\UnifiedArchive\Formats;

use Exception;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveCreationException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveModificationException;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;
use wapmorgan\UnifiedArchive\PclzipZipInterface;
use ZipArchive;

/**
 * Class Zip
 *
 * @package wapmorgan\UnifiedArchive\Formats
 * @requires ext-zip
 */
class Zip extends BasicFormat
{
    /** @var ZipArchive */
    protected $zip;

    /**
     * BasicFormat constructor.
     *
     * @param string $archiveFileName
     * @throws \Exception
     */
    public function __construct($archiveFileName)
    {
        $this->open($archiveFileName);
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
            $stat['comp_method'] != 0);
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
            throw new Exception('Could not get file information: '.$result);
        return $result;
    }

    /**
     * @param string $fileName
     *
     * @return bool|resource|string
     */
    public function getFileResource($fileName)
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
     * @param array $files
     * @param string $archiveFileName
     * @return int
     * @throws ArchiveCreationException
     */
    public static function createArchive(array $files, $archiveFileName){
        $zip = new ZipArchive();
        $result = $zip->open($archiveFileName, ZipArchive::CREATE);

        if ($result !== true)
            throw new ArchiveCreationException('ZipArchive error: '.$result);

        foreach ($files as $localName => $fileName) {
            if ($fileName === null) {
                if ($zip->addEmptyDir($localName) === false)
                    throw new ArchiveCreationException('Could not archive directory "'.$localName.'": '.$zip->getStatusString(), $zip->status);
            } else {
                if ($zip->addFile($fileName, $localName) === false)
                    throw new ArchiveCreationException('Could not archive file "'.$fileName.'": '.$zip->getStatusString(), $zip->status);
            }
        }
        $zip->close();

        return count($files);
    }

    /**
     * @return PclzipZipInterface
     */
    public function getPclZip()
    {
        return new PclzipZipInterface($this->zip);
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
        return true;
    }
}