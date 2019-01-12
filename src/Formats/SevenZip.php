<?php
namespace wapmorgan\UnifiedArchive\Formats;

use Exception;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveCreationException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveExtractionException;
use wapmorgan\UnifiedArchive\Exceptions\ArchiveModificationException;

class SevenZip extends BasicFormat
{
    /** @var Archive7z */
    protected $sevenZip;

    /**
     * BasicFormat constructor.
     *
     * @param string $archiveFileName
     *
     * @throws \Exception
     */
    public function __construct($archiveFileName)
    {
        try {
            $this->sevenZip = new Archive7z($archiveFileName, null, null);
        } catch (\Archive7z\Exception $e) {
            throw new Exception('Could not open 7Zip archive: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return ArchiveInformation
     */
    public function getArchiveInformation()
    {
        $information = new ArchiveInformation();
        foreach ($this->sevenZip->getEntries() as $entry) {
            $information->files[] = method_exists($entry, 'getUnixPath')
                ? $entry->getUnixPath()
                : str_replace('\\', '/', $entry->getPath());
            $information->compressedFilesSize += (int)$entry->getPackedSize();
            $information->uncompressedFilesSize += (int)$entry->getSize();
        }
        return $information;
    }

    /**
     * @return array
     */
    public function getFileNames()
    {
        $files = [];
        foreach ($this->sevenZip->getEntries() as $entry)
            $files[] = $entry->getPath();
        return $files;
    }

    /**
     * @param string $fileName
     *
     * @return bool
     */
    public function isFileExists($fileName)
    {
        return $this->sevenZip->getEntry($fileName) !== null;
    }

    /**
     * @param string $fileName
     *
     * @return ArchiveEntry|false
     */
    public function getFileData($fileName)
    {
        $entry = $this->sevenZip->getEntry($fileName);
        return new ArchiveEntry($fileName, $entry->getPackedSize(), $entry->getSize(),
            strtotime($entry->getModified()));
    }

    /**
     * @param string $fileName
     *
     * @return string|false
     */
    public function getFileContent($fileName)
    {
        $entry = $this->sevenZip->getEntry($fileName);
        return $entry->getContent();
    }

    /**
     * @param string $fileName
     *
     * @return bool|resource|string
     */
    public function getFileResource($fileName)
    {
        $resource = fopen('php://temp', 'r+');
        $entry = $this->sevenZip->getEntry($fileName);

        fwrite($resource, $entry->getContent());
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
        $count = 0;
        try {
            $this->sevenZip->setOutputDirectory($outputFolder);

            foreach ($files as $file) {
                $this->sevenZip->extractEntry($file);
                $count++;
            }
            return $count;
        } catch (Exception $e) {
            throw new ArchiveExtractionException('Could not extract archive: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param string $outputFolder
     *
     * @return bool
     * @throws ArchiveExtractionException
     */
    public function extractArchive($outputFolder)
    {
        try {
            $this->sevenZip->setOutputDirectory($outputFolder);
            $this->sevenZip->extract();
            return true;
        } catch (Exception $e) {
            throw new ArchiveExtractionException('Could not extract archive: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array $files
     * @return int Number of deleted files
     * @throws ArchiveModificationException
     */
    public function deleteFiles(array $files)
    {
        $count = 0;
        try {
            foreach ($files as $file) {
                $this->sevenZip->delEntry($file);
                $count++;
            }
            return $count;
        } catch (Exception $e) {
            throw new ArchiveModificationException('Could not modify archive: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array $files
     *
     * @return int
     * @throws ArchiveModificationException
     */
    public function addFiles(array $files)
    {
        $added_files = 0;
        try {
            foreach ($files as $localName => $filename) {
                if (!is_null($filename)) {
                    $this->sevenZip->addEntry($filename);
                    $this->sevenZip->renameEntry($filename, $localName);
                    $added_files++;
                }
            }
            return $added_files;
        } catch (Exception $e) {
            throw new ArchiveModificationException('Could not modify archive: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array $files
     * @param string $archiveFileName
     * @return int
     * @throws ArchiveCreationException
     */
    public static function createArchive(array $files, $archiveFileName) {
        try {
            $seven_zip = new Archive7z($archiveFileName);
            foreach ($files as $localName => $filename) {
                if ($filename !== null) {
                    $seven_zip->addEntry($filename, true);
                    $seven_zip->renameEntry($filename, $localName);
                }
            }
            unset($seven_zip);
        } catch (Exception $e) {
            throw new ArchiveCreationException('Could not create archive: '.$e->getMessage(), $e->getCode(), $e);
        }
        return count($files);
    }

    /**
     * @return bool
     * @throws \Archive7z\Exception
     */
    public static function canCreateArchive()
    {
        return static::canAddFiles();
    }

    /**
     * @return bool
     * @throws \Archive7z\Exception
     */
    public static function canAddFiles()
    {
        $version = Archive7z::getBinaryVersion();
        return $version !== false && version_compare('9.30', $version, '<=');
    }

    /**
     * @return bool
     */
    public static function canDeleteFiles()
    {
        return true;
    }
}