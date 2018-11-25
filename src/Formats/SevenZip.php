<?php
namespace wapmorgan\UnifiedArchive\Formats;

use Archive7z\Archive7z;
use Exception;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;

class SevenZip extends BasicFormat
{
    /** @var \Archive7z\Archive7z */
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
            $this->sevenZip = new Archive7z($archiveFileName);
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
            $information->files[] = $entry->getPath();
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
     * @param array  $files
     *
     * @return false|int
     * @throws \Archive7z\Exception
     * @throws \Exception
     */
    public function extractFiles($outputFolder, array $files)
    {
        $this->sevenZip->setOutputDirectory($outputFolder);
        $count = 0;
        try {

            foreach ($files as $file) {
                $this->sevenZip->extractEntry($file);
                $count++;
            }
            return $count;
        } catch (Exception $e) {
            throw new Exception('Could not extract archive: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param string $outputFolder
     *
     * @return false|bool
     * @throws \Exception
     */
    public function extractArchive($outputFolder)
    {
        $this->sevenZip->setOutputDirectory($outputFolder);
        try {
            $this->sevenZip->extract();
            return true;
        } catch (Exception $e) {
            throw new Exception('Could not extract archive: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array $files
     *
     * @return false|int
     * @throws \Exception
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
            throw new Exception('Could not modify archive: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array $files
     *
     * @return false|int
     * @throws \Exception
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
        } catch (Exception $e) {
            throw new Exception('Could not modify archive: '.$e->getMessage(), $e->getCode(), $e);
        }
        return $added_files;
    }

    /**
     * @param array  $files
     * @param string $archiveFileName
     *
     * @return false|int
     * @throws \Archive7z\Exception
     * @throws \Exception
     */
    public static function createArchive(array $files, $archiveFileName) {
        $seven_zip = new Archive7z($archiveFileName);
        try {
            foreach ($files as $localName => $filename) {
                if ($filename !== null) {
                    $seven_zip->addEntry($filename, true);
                    $seven_zip->renameEntry($filename, $localName);
                }
            }
            unset($seven_zip);
        } catch (Exception $e) {
            throw new Exception('Could not create archive: '.$e->getMessage(), $e->getCode(), $e);
        }
        return count($files);
    }
}