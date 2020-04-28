<?php
namespace wapmorgan\UnifiedArchive\Formats;

use Exception;
use wapmorgan\UnifiedArchive\ArchiveEntry;
use wapmorgan\UnifiedArchive\ArchiveInformation;
use wapmorgan\UnifiedArchive\Exceptions\UnsupportedOperationException;

class Rar extends BasicFormat
{
    /** @var \RarArchive */
    protected $rar;

    /**
     * BasicFormat constructor.
     *
     * @param string $archiveFileName
     *
     * @throws \Exception
     */
    public function __construct($archiveFileName)
    {
        \RarException::setUsingExceptions(true);
        $this->open($archiveFileName);
    }

    /**
     * @param $archiveFileName
     *
     * @throws \Exception
     */
    protected function open($archiveFileName)
    {
        $this->rar = \RarArchive::open($archiveFileName);
        if ($this->rar === false) {
            throw new Exception('Could not open Rar archive');
        }
    }

    /**
     * Rar format destructor
     */
    public function __destruct()
    {
        $this->rar->close();
    }

    /**
     * @return ArchiveInformation
     */
    public function getArchiveInformation()
    {
        $information = new ArchiveInformation();
        foreach ($this->rar->getEntries() as $i => $entry) {
            if ($entry->isDirectory()) continue;
            $information->files[] = $entry->getName();
            $information->compressedFilesSize += $entry->getPackedSize();
            $information->uncompressedFilesSize += $entry->getUnpackedSize();
        }
        return $information;
    }

    /**
     * @return array
     */
    public function getFileNames()
    {
        $files = [];
        foreach ($this->rar->getEntries() as $i => $entry) {
            if ($entry->isDirectory()) continue;
            $files[] = $entry->getName();
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
        return $this->rar->getEntry($fileName) !== false;
    }

    /**
     * @param string $fileName
     *
     * @return ArchiveEntry|false
     */
    public function getFileData($fileName)
    {
        $entry = $this->rar->getEntry($fileName);
        return new ArchiveEntry($fileName, $entry->getPackedSize(), $entry->getUnpackedSize(),
            strtotime($entry->getFileTime()), $entry->getMethod() != 48);
    }

    /**
     * @param string $fileName
     *
     * @return string|false
     */
    public function getFileContent($fileName)
    {
        $entry = $this->rar->getEntry($fileName);
        if ($entry->isDirectory()) return false;
        return stream_get_contents($entry->getStream());
    }

    /**
     * @param string $fileName
     *
     * @return bool|resource|string
     */
    public function getFileResource($fileName)
    {
        $entry = $this->rar->getEntry($fileName);
        if ($entry->isDirectory()) return false;
        return $entry->getStream();
    }

    /**
     * @param string $outputFolder
     * @param array  $files
     *
     * @return false|int
     */
    public function extractFiles($outputFolder, array $files)
    {
        $count = 0;
        foreach ($files as $file) {
            if ($this->rar->getEntry($file)->extract($outputFolder)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param string $outputFolder
     *
     * @return false|resource
     */
    public function extractArchive($outputFolder)
    {
        return $this->extractFiles($outputFolder, $this->getFileNames());
    }

    /**
     * @param array $files
     * @throws UnsupportedOperationException
     */
    public function deleteFiles(array $files)
    {
        throw new UnsupportedOperationException();
    }

    /**
     * @param array $files
     * @throws UnsupportedOperationException
     */
    public function addFiles(array $files)
    {
        throw new UnsupportedOperationException();
    }

    /**
     * @param array  $files
     * @param string $archiveFileName
     * @throws UnsupportedOperationException
     */
    public static function createArchive(array $files, $archiveFileName){
        throw new UnsupportedOperationException();
    }
}